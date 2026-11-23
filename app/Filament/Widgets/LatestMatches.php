<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MatchResource;
use App\Models\FootballMatch;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestMatches extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Son Maçlar';

    public function table(Table $table): Table
    {
        return $table
            ->query(FootballMatch::query()
                ->with('group')
                ->withCount(['rsvps as going_count' => fn ($q) => $q->where('status', 'going')])
                ->latest('starts_at'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Maç'),
                Tables\Columns\TextColumn::make('group.name')->label('Grup'),
                Tables\Columns\TextColumn::make('starts_at')->label('Tarih')->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'scheduled' => 'Planlandı', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal', default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled' => 'info', 'completed' => 'success', 'cancelled' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('going_count')
                    ->label('Katılım')
                    ->state(fn (FootballMatch $record): string => $record->going_count.' / '.$record->capacity),
                Tables\Columns\TextColumn::make('score')
                    ->label('Skor')
                    ->state(fn (FootballMatch $record): string => $record->team_a_score === null
                        ? '—' : $record->team_a_score.' - '.$record->team_b_score),
            ])
            ->recordUrl(fn (FootballMatch $record): string => MatchResource::getUrl('edit', ['record' => $record]));
    }
}
