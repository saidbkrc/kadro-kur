<?php

namespace App\Services;

use App\Models\FootballMatch;
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
            $match->starts_at->translatedFormat('d F l H:i').' — Geliyor musun? RSVP ver.',
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
                $match->starts_at->translatedFormat('l H:i').' — RSVP verdin mi?',
                route('matches.show', $match),
                'mac-'.$match->id.'-hatirlatma',
            );
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
