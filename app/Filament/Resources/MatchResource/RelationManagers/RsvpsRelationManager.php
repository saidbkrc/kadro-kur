<?php

namespace App\Filament\Resources\MatchResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Maça katılım listesi (salt okunur): kim geliyor, yedekte kim, hangi takımda. */
class RsvpsRelationManager extends RelationManager
{
    protected static string $relationship = 'rsvps';

    protected static ?string $title = 'Katılım';

    protected static ?string $modelLabel = 'katılım';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('player'))
            ->columns([
                Tables\Columns\TextColumn::make('player.name')
                    ->label('Oyuncu')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'going' => 'Geliyor',
                        'maybe' => 'Belki',
                        'not_going' => 'Gelmiyor',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'going' => 'success',
                        'maybe' => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('waitlist_position')
                    ->label('Yedek sırası')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('team')
                    ->label('Takım')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'A' => 'Turuncu',
                        'B' => 'Yeşil',
                        default => (string) $state,
                    })
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options(['going' => 'Geliyor', 'maybe' => 'Belki', 'not_going' => 'Gelmiyor']),
            ])
            ->defaultSort('status');
    }
}
