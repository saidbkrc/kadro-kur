<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Üyeler';

    protected static ?string $modelLabel = 'üye';

    public const ROLES = ['owner' => 'Başkan', 'admin' => 'Yönetici', 'member' => 'Üye'];

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('role')
                ->label('Rol')
                ->options(self::ROLES)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::ROLES[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'owner' => 'warning',
                        'admin' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Katıldı')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options(self::ROLES)
                    ->query(fn ($query, array $data) => $data['value']
                        ? $query->wherePivot('role', $data['value'])
                        : $query),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Rolü değiştir'),
                Tables\Actions\DetachAction::make()->label('Gruptan çıkar'),
            ]);
    }
}
