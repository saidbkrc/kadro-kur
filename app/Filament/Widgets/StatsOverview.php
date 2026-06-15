<?php

namespace App\Filament\Widgets;

use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $upcoming = FootballMatch::where('status', 'scheduled')->where('starts_at', '>=', now())->count();
        $completed = FootballMatch::where('status', 'completed')->count();

        return [
            Stat::make('Kullanıcı', User::count())
                ->description(User::where('is_admin', true)->count().' yönetici')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Grup', Group::count())
                ->description(Player::whereNull('user_id')->count().' misafir oyuncu')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Yaklaşan Maç', $upcoming)
                ->description($completed.' maç tamamlandı')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),
        ];
    }
}
