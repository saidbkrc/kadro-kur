<?php

use App\Services\MatchScheduler;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\Schedule;

// Haftalık otomatik maçlar: gelecek maçı olmayan gruplara sıradakini açar.
// Local'de çalıştırmak için: php artisan schedule:work
Schedule::call(fn () => app(MatchScheduler::class)->run())
    ->hourly()
    ->name('weekly-matches')
    ->withoutOverlapping();

// Maç hatırlatma push'u: 24 saat penceresine giren maçlar (maç başına 1 kez).
Schedule::call(fn () => app(PushNotifier::class)->sendDueReminders())
    ->hourly()
    ->name('match-reminders')
    ->withoutOverlapping();

// Maç özeti push'u: maçtan ~24 saat sonra skor + MVP + golcü (maç başına 1 kez) + rozet taraması.
Schedule::call(fn () => app(PushNotifier::class)->sendDueDigests())
    ->hourly()
    ->name('match-digests')
    ->withoutOverlapping();
