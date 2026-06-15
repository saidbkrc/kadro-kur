<?php

use App\Livewire\Dashboard;
use App\Livewire\Groups;
use App\Livewire\Matches;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// Davet linki girişsiz açılabilir: grup görülür, katılmak için kayıt/giriş istenir.
Route::get('davet/{code}', Groups\Join::class)->name('groups.join');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');

    Route::view('profile', 'profile')->name('profile');

    Route::get('gruplar', Groups\Index::class)->name('groups.index');
    Route::get('gruplar/{group}', Groups\Show::class)->name('groups.show');
    Route::get('gruplar/{group}/puanla', Groups\Rate::class)->name('groups.rate');
    Route::get('gruplar/{group}/istatistik', Groups\Stats::class)->name('groups.stats');

    Route::get('maclar/{match}', Matches\Show::class)->name('matches.show');
});

require __DIR__.'/auth.php';
