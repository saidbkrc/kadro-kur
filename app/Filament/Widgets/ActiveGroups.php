<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GroupResource;
use App\Models\Group;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ActiveGroups extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'En Aktif Gruplar';

    public function table(Table $table): Table
    {
        return $table
            ->query(Group::query()
                ->with('owner')
                ->withCount(['members', 'matches', 'matches as completed_count' => fn ($q) => $q->where('status', 'completed')])
                ->withMax('matches', 'starts_at')
                ->orderByDesc('matches_count'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Grup')->weight('bold'),
                Tables\Columns\TextColumn::make('owner.name')->label('Başkan'),
                Tables\Columns\TextColumn::make('members_count')->label('Üye')->badge()->color('success'),
                Tables\Columns\TextColumn::make('matches_count')->label('Maç')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('completed_count')->label('Oynanan')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('matches_max_starts_at')->label('Son maç')->dateTime('d.m.Y')->placeholder('—'),
            ])
            ->recordUrl(fn (Group $record): string => GroupResource::getUrl('edit', ['record' => $record]));
    }
}
