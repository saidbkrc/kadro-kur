<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use App\Notifications\MatchPushNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Maç olaylarında web push gönderimi. Alıcı seçimi burada merkezidir;
 * aboneliği olmayan kullanıcıya gönderim sessiz no-op'tur.
 * Push hatası hiçbir kullanıcı aksiyonunu bozmamalı — hepsi try/catch'li.
 */
class PushNotifier
{
    /** Yeni maç açıldı → tüm grup üyeleri (maçı açan hariç): RSVP çağrısı. */
    public function newMatch(FootballMatch $match, ?int $exceptUserId = null): void
    {
        $this->send(
            $this->groupMembers($match, $exceptUserId),
            '⚽ Yeni maç: '.$match->title,
            $match->starts_at->translatedFormat('d F l H:i').' — Geliyor musun? Katılımını bildir.',
            route('matches.show', $match),
            'mac-'.$match->id.'-yeni',
        );
    }

    /** Kadro oylamaya sunuldu → asıl listedeki hesaplı oyuncular (kuran hariç). */
    public function squadVoteOpened(FootballMatch $match, ?int $exceptUserId = null): void
    {
        $userIds = collect($match->squadVoterIds())->reject(fn ($id) => $id === $exceptUserId);

        $this->send(
            User::whereIn('id', $userIds)->get(),
            '🗳️ Kadro oylamada: '.$match->title,
            'Takımlar kuruldu — onayla ya da reddet (%60 kuralı).',
            route('matches.show', $match),
            'mac-'.$match->id.'-kadro',
        );
    }

    /** Skor girildi, MVP + performans penceresi açıldı → asıl kadro (giren hariç). */
    public function resultEntered(FootballMatch $match, ?int $exceptUserId = null): void
    {
        $userIds = $match->mainListRsvps()
            ->pluck('player.user_id')
            ->filter()
            ->reject(fn ($id) => $id === $exceptUserId);

        $this->send(
            User::whereIn('id', $userIds)->get(),
            "📊 Skor: {$match->team_a_score} - {$match->team_b_score}",
            $match->title.' bitti — MVP ve performans oylaması açıldı!',
            route('matches.show', $match),
            'mac-'.$match->id.'-sonuc',
        );
    }

    /** 24 saat penceresine giren maçlar için hatırlatma (scheduler çağırır, maç başına 1 kez). */
    public function sendDueReminders(): void
    {
        $due = FootballMatch::query()
            ->where('status', 'scheduled')
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addHours(24)])
            ->with('group')
            ->get();

        foreach ($due as $match) {
            // Önce işaretle: gönderim yavaş/yarım kalsa bile ikinci tur spam yapmasın
            $match->update(['reminder_sent_at' => now()]);

            $this->send(
                $this->groupMembers($match),
                '⏰ Maç yaklaşıyor: '.$match->title,
                $match->starts_at->translatedFormat('l H:i').' — Katılımını bildirdin mi?',
                route('matches.show', $match),
                'mac-'.$match->id.'-hatirlatma',
            );
        }
    }

    /** Başkanın elle gönderebileceği hazır hatırlatmalar: [tip => etiket]. */
    public const MANUAL_REMINDERS = [
        'rsvp' => '📋 Katılım hatırlat',
        'squad_vote' => '🗳️ Kadro oylaması hatırlat',
        'squad_announce' => '📣 Maç kadrosunu duyur',
        'mvp' => '🏆 MVP oylaması hatırlat',
        'perf' => '📈 Performans puanı hatırlat',
    ];

    /**
     * Başkanın hazır hatırlatması. Hedef kitle tipe göre daraltılır (işini yapmış
     * olanlara spam gitmez). Gönderilen kişi sayısını döndürür.
     */
    public function manualReminder(FootballMatch $match, string $type, ?int $exceptUserId = null): int
    {
        $title = $match->title;
        $url = route('matches.show', $match);

        [$users, $header, $body] = match ($type) {
            // RSVP: henüz kesin cevap vermemiş üyeler (cevapsız veya "belki")
            'rsvp' => [
                $this->groupMembers($match, $exceptUserId)->reject(function (User $user) use ($match) {
                    $player = $match->group->players()->firstWhere('user_id', $user->id);
                    $status = $player ? $match->rsvps()->firstWhere('player_id', $player->id)?->status : null;

                    return in_array($status, ['going', 'not_going'], true);
                }),
                '📋 Katılımını bekliyoruz',
                $title.' — Geliyor musun? Katılımını bildir.',
            ],
            // Kadro oylaması: oy hakkı olup henüz oy kullanmamışlar
            'squad_vote' => [
                User::whereIn('id', collect($match->squadVoterIds())
                    ->diff($match->squadVotes()->pluck('user_id'))
                    ->reject(fn ($id) => $id === $exceptUserId))->get(),
                '🗳️ Kadro oyunu bekliyor',
                $title.' — Takımlar kuruldu, onayla ya da reddet.',
            ],
            // Kadro duyurusu: asıl listedeki herkes
            'squad_announce' => [
                User::whereIn('id', collect($match->squadVoterIds())->reject(fn ($id) => $id === $exceptUserId))->get(),
                '📣 Maç kadrosu belli oldu',
                $title.' — Takımına ve dizilişe göz at.',
            ],
            // MVP: kadroda olup henüz MVP oyu vermemişler
            'mvp' => [
                User::whereIn('id', $match->mainListRsvps()->pluck('player.user_id')->filter()
                    ->diff($match->mvpVotes()->pluck('voter_id'))
                    ->reject(fn ($id) => $id === $exceptUserId))->get(),
                '🏆 Maçın adamını seç',
                $title.' — MVP oyunu henüz kullanmadın!',
            ],
            // Performans: kadroda olup bu maçta hiç puan vermemişler
            'perf' => [
                User::whereIn('id', $match->mainListRsvps()->pluck('player.user_id')->filter()
                    ->diff($match->performanceRatings()->pluck('rater_id'))
                    ->reject(fn ($id) => $id === $exceptUserId))->get(),
                '📈 Takım arkadaşlarını puanla',
                $title.' — Performans puanların bekleniyor.',
            ],
            default => throw new \InvalidArgumentException("Bilinmeyen hatırlatma tipi: {$type}"),
        };

        $users = $users->values();
        $this->send($users, $header, $body, $url, 'mac-'.$match->id.'-hatirlatma-'.$type);

        return $users->count();
    }

    /**
     * Grubun rozetlerini senkronlar; yeni rozet kazananlara tek bildirim gönderir
     * (oyuncu başına 1 push — birden çok rozet aynı mesajda listelenir).
     */
    public function syncBadgesAndNotify(Group $group): void
    {
        $new = app(PlayerBadges::class)->syncGroup($group);

        if ($new === []) {
            return;
        }

        $players = Player::with('user')->whereIn('id', array_keys($new))->get()->keyBy('id');

        foreach ($new as $playerId => $badges) {
            $player = $players->get($playerId);
            if ($player?->user === null) {
                continue; // misafirin hesabı yok
            }

            $names = collect($badges)->map(fn (array $b) => $b['icon'].' '.$b['name'])->implode(', ');

            $this->send(
                collect([$player->user]),
                count($badges) > 1 ? '🎉 '.count($badges).' yeni rozet kazandın!' : '🎉 Yeni rozet kazandın!',
                $names.' — profilinde seni bekliyor.',
                route('groups.player', [$group, $player]),
                'rozet-'.$player->id,
            );
        }
    }

    /** Maç iptal edildi → tüm grup üyeleri (iptal eden hariç). */
    public function matchCancelled(FootballMatch $match, ?int $exceptUserId = null): void
    {
        $this->send(
            $this->groupMembers($match, $exceptUserId),
            '❌ Maç iptal edildi: '.$match->title,
            $match->starts_at->translatedFormat('d F l H:i').' maçı oynanmayacak.',
            route('matches.show', $match),
            'mac-'.$match->id.'-iptal',
        );
    }

    /** Kehanet: kupon(lar)ı tutan kullanıcıya tek bildirim. */
    public function kehanetWin(User $user, FootballMatch $match, int $net): void
    {
        $this->send(
            collect([$user]),
            '🎉 Kehanetin tuttu!',
            $match->title.' — kazancın +'.number_format($net).' Çim',
            route('groups.kehanet', $match->group_id),
            'kehanet-'.$match->id.'-'.$user->id,
        );
    }

    /** Maç başarı ödülü: MVP / en çok gol / forma golü karşılığı Çim. */
    public function kehanetBonus(?User $user, FootballMatch $match, int $total, array $reasons): void
    {
        if ($user === null) {
            return;
        }

        $this->send(
            collect([$user]),
            '🎁 Maç ödülün: +'.number_format($total).' Çim',
            implode(' · ', $reasons).' — '.$match->title,
            route('groups.kehanet', $match->group_id),
            'odul-'.$match->id.'-'.$user->id,
        );
    }

    /** Nitelik 3 onaya ulaşınca oyuncuya tek bildirim (her onayda değil — spam yok). */
    public function traitMilestone(Player $player, string $traitKey): void
    {
        $trait = \App\Support\PlayerTraits::ALL[$traitKey] ?? null;

        if ($trait === null || $player->user === null) {
            return; // bilinmeyen nitelik ya da misafir (hesabı yok)
        }

        $this->send(
            collect([$player->user]),
            '🏷️ Takım arkadaşların onayladı!',
            $trait['icon'].' '.$trait['name'].' niteliğin 3 onaya ulaştı — profiline göz at.',
            route('groups.player', [$player->group_id, $player->id]),
            'nitelik-'.$player->id.'-'.$traitKey,
        );
    }

    /**
     * Maç özeti: maçtan ~24 saat sonra skor + MVP lideri + golcü tek bildirimde
     * (maç başına 1 kez, digest_sent_at ile). Scheduler her saat çağırır.
     */
    public function sendDueDigests(): void
    {
        $due = FootballMatch::query()
            ->where('status', 'completed')
            ->whereNull('digest_sent_at')
            ->where('starts_at', '<=', now()->subDay())
            ->where('starts_at', '>=', now()->subDays(7)) // eski maçlara geriye dönük özet atma
            ->with(['group', 'goals.player', 'mvpVotes.player'])
            ->get();

        foreach ($due as $match) {
            $match->update(['digest_sent_at' => now()]);

            $parts = ["Turuncu {$match->team_a_score} - {$match->team_b_score} Yeşil"];

            if ($match->mvpVotes->isNotEmpty()) {
                $topId = $match->mvpVotes->countBy('player_id')->sortDesc()->keys()->first();
                $mvpName = $match->mvpVotes->firstWhere('player_id', $topId)?->player?->name;
                if ($mvpName) {
                    $parts[] = '🏆 MVP: '.$mvpName;
                }
            }

            $topGoal = $match->goals->sortByDesc('count')->first();
            if ($topGoal?->player) {
                $parts[] = '⚽ '.$topGoal->player->name.($topGoal->count > 1 ? ' ×'.$topGoal->count : '');
            }

            $this->send(
                $this->groupMembers($match),
                '📊 Maç özeti: '.$match->title,
                implode(' · ', $parts),
                route('matches.show', $match),
                'mac-'.$match->id.'-ozet',
            );

            // Ertesi gün rozetleri de tara (MVP/performans kaynaklı yeni kazanımlar)
            $this->syncBadgesAndNotify($match->group);
        }
    }

    /** Maçın grubundaki tüm hesaplı üyeler (istenirse biri hariç). */
    protected function groupMembers(FootballMatch $match, ?int $exceptUserId = null): Collection
    {
        return $match->group->members()
            ->when($exceptUserId !== null, fn ($q) => $q->where('users.id', '!=', $exceptUserId))
            ->get();
    }

    protected function send(Collection $users, string $title, string $body, string $url, string $tag): void
    {
        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, new MatchPushNotification($title, $body, $url, $tag));
        } catch (\Throwable $e) {
            report($e); // push servisi hatası asıl aksiyonu (maç açma, skor girme) bozmasın
        }
    }
}
