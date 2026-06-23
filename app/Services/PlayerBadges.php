<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * Türetilmiş rozet sistemi: oyuncu rozetleri grup maçlarından anlık hesaplanır,
 * ayrı tablo tutulmaz. Her rozet bir eşik kuralıdır; kazanılmış olup olmadığı ve
 * ilerlemesi (örn. 7/10 maç) istatistiklerden çıkarılır.
 */
class PlayerBadges
{
    /**
     * Çekirdek rozet kataloğu. Her rozet: key, icon, name, desc, group (kategori),
     * goal (eşik) ve stat (hangi istatistiğe bakılacağı).
     *
     * @return list<array{key:string,icon:string,name:string,desc:string,group:string,goal:int,stat:string}>
     */
    public static function catalog(): array
    {
        return [
            // ⚽ Gol
            ['key' => 'first_goal', 'icon' => '🎯', 'name' => 'İlk Gol', 'desc' => 'İlk golünü at', 'group' => 'Gol', 'goal' => 1, 'stat' => 'goals'],
            ['key' => 'scorer', 'icon' => '🔥', 'name' => 'Golcü', 'desc' => 'Toplam 10 gol at', 'group' => 'Gol', 'goal' => 10, 'stat' => 'goals'],
            ['key' => 'goal_king', 'icon' => '👑', 'name' => 'Gol Kralı', 'desc' => 'Toplam 50 gol at', 'group' => 'Gol', 'goal' => 50, 'stat' => 'goals'],
            ['key' => 'hat_trick', 'icon' => '⚡', 'name' => 'Hat-trick', 'desc' => 'Tek maçta 3 gol at', 'group' => 'Gol', 'goal' => 3, 'stat' => 'best_match_goals'],

            // 🏆 MVP
            ['key' => 'mvp', 'icon' => '⭐', 'name' => 'Maçın Adamı', 'desc' => 'Bir maçta MVP seçil', 'group' => 'MVP', 'goal' => 1, 'stat' => 'mvp'],
            ['key' => 'star', 'icon' => '🌟', 'name' => 'Yıldız', 'desc' => '5 kez MVP seçil', 'group' => 'MVP', 'goal' => 5, 'stat' => 'mvp'],

            // 🤝 Katılım
            ['key' => 'first_match', 'icon' => '🐣', 'name' => 'İlk Maç', 'desc' => 'İlk maçına çık', 'group' => 'Katılım', 'goal' => 1, 'stat' => 'played'],
            ['key' => 'regular', 'icon' => '🎖️', 'name' => 'Düzenli', 'desc' => '10 maça çık', 'group' => 'Katılım', 'goal' => 10, 'stat' => 'played'],
            ['key' => 'veteran', 'icon' => '🏅', 'name' => 'Veteran', 'desc' => '50 maça çık', 'group' => 'Katılım', 'goal' => 50, 'stat' => 'played'],
            ['key' => 'streak', 'icon' => '🧲', 'name' => 'Kaçırmayan', 'desc' => 'Üst üste 10 maça çık', 'group' => 'Katılım', 'goal' => 10, 'stat' => 'streak'],
        ];
    }

    /**
     * Grubun tüm oyuncuları için ham istatistik toplar.
     * Maçlar kronolojik (eski→yeni) gezilir ki katılım serisi doğru hesaplansın.
     *
     * @return Collection<int, array{played:int,goals:int,mvp:int,best_match_goals:int,streak:int}>
     */
    public function statsForGroup(Group $group): Collection
    {
        $matches = $group->matches()
            ->where('status', 'completed')
            ->with(['rsvps', 'goals', 'mvpVotes'])
            ->orderBy('starts_at')
            ->get();

        /** @var array<int, array{played:int,win:int,draw:int,loss:int,goals:int,mvp:int,best_match_goals:int,streak:int,_run:int}> $stats */
        $stats = [];
        $touch = function (int $playerId) use (&$stats): void {
            $stats[$playerId] ??= [
                'played' => 0, 'win' => 0, 'draw' => 0, 'loss' => 0, 'goals' => 0, 'mvp' => 0,
                'best_match_goals' => 0, 'streak' => 0, '_run' => 0,
            ];
        };

        foreach ($matches as $match) {
            $isDraw = $match->team_a_score === $match->team_b_score;
            $winner = $match->team_a_score > $match->team_b_score ? 'A' : 'B';

            // Bu maçta asıl listede (yedek değil) "geliyorum" diyenler
            $mainGoing = [];
            foreach ($match->rsvps as $rsvp) {
                if ($rsvp->status === 'going' && $rsvp->waitlist_position === null) {
                    $mainGoing[$rsvp->player_id] = true;
                    $touch($rsvp->player_id);
                    $stats[$rsvp->player_id]['played']++;

                    if ($rsvp->team !== null) {
                        if ($isDraw) {
                            $stats[$rsvp->player_id]['draw']++;
                        } elseif ($rsvp->team === $winner) {
                            $stats[$rsvp->player_id]['win']++;
                        } else {
                            $stats[$rsvp->player_id]['loss']++;
                        }
                    }
                }
            }

            // Katılım serisi: bu maçta gelenler için run +1, gelmeyenler için sıfırla
            foreach ($stats as $pid => &$s) {
                if (isset($mainGoing[$pid])) {
                    $s['_run']++;
                    $s['streak'] = max($s['streak'], $s['_run']);
                } else {
                    $s['_run'] = 0;
                }
            }
            unset($s);

            // Goller (bir oyuncunun o maçtaki golleri toplanır → hat-trick için en iyi maç)
            $matchGoals = [];
            foreach ($match->goals as $goal) {
                $matchGoals[$goal->player_id] = ($matchGoals[$goal->player_id] ?? 0) + $goal->count;
            }
            foreach ($matchGoals as $pid => $count) {
                $touch($pid);
                $stats[$pid]['goals'] += $count;
                $stats[$pid]['best_match_goals'] = max($stats[$pid]['best_match_goals'], $count);
            }

            // MVP: oylama kapanmışsa en çok oyu alan(lar)
            if (! $match->mvpOpen() && $match->mvpVotes->isNotEmpty()) {
                $counts = $match->mvpVotes->countBy('player_id');
                $max = $counts->max();
                foreach ($counts->filter(fn ($c) => $c === $max)->keys() as $pid) {
                    $touch($pid);
                    $stats[$pid]['mvp']++;
                }
            }
        }

        return collect($stats)->map(function (array $s) {
            unset($s['_run']);

            return $s;
        });
    }

    /** Hiç maçı olmayan oyuncu için sıfır istatistik. */
    public static function emptyStats(): array
    {
        return [
            'played' => 0, 'win' => 0, 'draw' => 0, 'loss' => 0, 'goals' => 0,
            'mvp' => 0, 'best_match_goals' => 0, 'streak' => 0,
        ];
    }

    /** Tek oyuncunun ham istatistiği (grup içinden). */
    public function statsForPlayer(Player $player): array
    {
        return $this->statsForGroup($player->group)->get($player->id, self::emptyStats());
    }

    /**
     * Tek oyuncunun rozet durumu: her rozet için kazanıldı mı + ilerleme.
     *
     * @return list<array{key:string,icon:string,name:string,desc:string,group:string,goal:int,value:int,earned:bool,progress:float}>
     */
    public function forPlayer(Player $player): array
    {
        return $this->evaluate($this->statsForPlayer($player));
    }

    /**
     * Ham istatistikten rozet listesi türetir (kazanılan önce, sonra ilerlemeye göre).
     *
     * @param  array{played:int,goals:int,mvp:int,best_match_goals:int,streak:int}  $stats
     * @return list<array{key:string,icon:string,name:string,desc:string,group:string,goal:int,value:int,earned:bool,progress:float}>
     */
    public function evaluate(array $stats): array
    {
        $badges = array_map(function (array $b) use ($stats) {
            $value = (int) ($stats[$b['stat']] ?? 0);
            $earned = $value >= $b['goal'];

            return [
                ...$b,
                'value' => $value,
                'earned' => $earned,
                'progress' => $b['goal'] > 0 ? min($value / $b['goal'], 1.0) : 0.0,
            ];
        }, self::catalog());

        usort($badges, fn ($a, $b) => [$b['earned'], $b['progress']] <=> [$a['earned'], $a['progress']]);

        return $badges;
    }
}
