<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Group;
use Illuminate\Support\Carbon;

/**
 * Haftalık otomatik maç: başkan grup ayarında günü/saati bir kere seçer,
 * sistem her hafta sıradaki maçı kendisi açar.
 */
class MatchScheduler
{
    /** Gelecek tarihli planlı maç yoksa bir sonrakini oluşturur. */
    public function ensureUpcomingMatch(Group $group): ?FootballMatch
    {
        if (! $group->auto_schedule || ! $group->match_day || ! $group->match_time) {
            return null;
        }

        $hasUpcoming = $group->matches()
            ->where('status', 'scheduled')
            ->where('starts_at', '>=', now())
            ->exists();

        if ($hasUpcoming) {
            return null;
        }

        $next = $this->nextOccurrence($group);

        return $group->matches()->create([
            'created_by' => $group->owner_id,
            'title' => $next->translatedFormat('l H:i').' maçı',
            'location' => $group->default_location,
            'starts_at' => $next,
            'capacity' => $group->capacity,
        ]);
    }

    /** Otomatik maçı açık tüm grupları kontrol eder (scheduler her saat çağırır). */
    public function run(): void
    {
        Group::where('auto_schedule', true)
            ->get()
            ->each(fn (Group $group) => $this->ensureUpcomingMatch($group));
    }

    protected function nextOccurrence(Group $group): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($group->match_time, 0, 5)));

        $candidate = now()->startOfWeek()
            ->addDays($group->match_day - 1)
            ->setTime($hour, $minute);

        while ($candidate->isPast()) {
            $candidate->addWeek();
        }

        return $candidate;
    }
}
