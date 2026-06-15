<?php

use App\Services\MatchScheduler;
use Illuminate\Support\Facades\Schedule;

// Haftalık otomatik maçlar: gelecek maçı olmayan gruplara sıradakini açar.
// Local'de çalıştırmak için: php artisan schedule:work
Schedule::call(fn () => app(MatchScheduler::class)->run())
    ->hourly()
    ->name('weekly-matches')
    ->withoutOverlapping();
