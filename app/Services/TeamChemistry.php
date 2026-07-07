<?php

namespace App\Services;

use App\Models\Group;
use Illuminate\Support\Collection;

/**
 * Takım kimyası: aynı takımda oynayan ikililerin birlikte kazanma oranı.
 * Tamamen türetilmiş (tablo yok) — rsvps.team + maç skorlarından hesaplanır.
 */
class TeamChemistry
{
    /** Az ortak maçta oran yanıltıcı olur — ikili en az bu kadar maçta birlikte oynamalı. */
    public const MIN_TOGETHER = 3;

    /**
     * Grubun ikili kimyaları, en iyi orandan başlayarak.
     *
     * @return Collection<int, array{a:\App\Models\Player,b:\App\Models\Player,together:int,wins:int,rate:int}>
     */
    public function pairsForGroup(Group $group, int $minTogether = self::MIN_TOGETHER): Collection
    {
        $matches = $group->matches()
            ->where('status', 'completed')
            ->whereNotNull('team_a_score')
            ->with('rsvps')
            ->get();

        /** @var array<string, array{together:int, wins:int}> $pairs */
        $pairs = [];

        foreach ($matches as $match) {
            $isDraw = $match->team_a_score === $match->team_b_score;
            $winner = $match->team_a_score > $match->team_b_score ? 'A' : 'B';

            foreach (['A', 'B'] as $team) {
                $ids = $match->rsvps
                    ->filter(fn ($r) => $r->status === 'going' && $r->waitlist_position === null && $r->team === $team)
                    ->pluck('player_id')->sort()->values();

                $won = ! $isDraw && $team === $winner;

                foreach ($ids as $i => $a) {
                    foreach ($ids->slice($i + 1) as $b) {
                        $key = $a.'-'.$b;
                        $pairs[$key] ??= ['together' => 0, 'wins' => 0];
                        $pairs[$key]['together']++;
                        $pairs[$key]['wins'] += $won ? 1 : 0;
                    }
                }
            }
        }

        $players = $group->players()->get()->keyBy('id');

        return collect($pairs)
            ->filter(fn (array $p) => $p['together'] >= $minTogether)
            ->map(function (array $p, string $key) use ($players) {
                [$aId, $bId] = explode('-', $key);

                return [
                    'a' => $players->get((int) $aId),
                    'b' => $players->get((int) $bId),
                    'together' => $p['together'],
                    'wins' => $p['wins'],
                    'rate' => (int) round($p['wins'] / $p['together'] * 100),
                ];
            })
            ->filter(fn (array $p) => $p['a'] !== null && $p['b'] !== null)
            ->sortBy([['rate', 'desc'], ['together', 'desc']])
            ->values();
    }

    /** Bir oyuncunun en uyumlu ortağı (profil için). Yoksa null. */
    public function bestPartnerFor(int $playerId, Group $group): ?array
    {
        return $this->pairsForGroup($group)
            ->first(fn (array $p) => $p['a']->id === $playerId || $p['b']->id === $playerId);
    }
}
