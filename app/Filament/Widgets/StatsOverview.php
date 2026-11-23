<?php

namespace App\Filament\Widgets;

use App\Models\FootballMatch;
use App\Models\Goal;
use App\Models\Group;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = -2;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $upcoming = FootballMatch::where('status', 'scheduled')->where('starts_at', '>=', now())->count();
        $completed = FootballMatch::where('status', 'completed')->count();
        $newUsers = User::where('created_at', '>=', now()->subDays(7))->count();
        $goals = (int) Goal::sum('count');

        // Son 8 haftanın haftalık maç sayısı (küçük grafik için)
        $weekly = collect(range(7, 0))->map(fn (int $w) => FootballMatch::whereBetween('starts_at', [
            now()->subWeeks($w)->startOfWeek(), now()->subWeeks($w)->endOfWeek(),
        ])->count())->all();

        return [
            Stat::make('Kullanıcı', User::count())
                ->description('Son 7 günde +'.$newUsers.' · '.User::where('is_admin', true)->count().' yönetici')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Grup', Group::count())
                ->description(Player::count().' oyuncu · '.Player::whereNull('user_id')->count().' misafir')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Yaklaşan Maç', $upcoming)
                ->description($completed.' maç tamamlandı')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart($weekly)
                ->color('warning'),

            Stat::make('Toplam Gol', $goals)
                ->description($completed ? round($goals / $completed, 1).' gol / maç' : 'Henüz maç yok')
                ->descriptionIcon('heroicon-m-fire')
                ->color('danger'),

            Stat::make('Kehanet', Prediction::count())
                ->description('Toplam yapılan tahmin')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),

            Stat::make('İptal Edilen Maç', FootballMatch::where('status', 'cancelled')->count())
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('gray'),
        ];
    }
}
