<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Support\Attributes;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlayersRelationManager extends RelationManager
{
    protected static string $relationship = 'players';

    protected static ?string $title = 'Oyuncular';

    protected static ?string $modelLabel = 'oyuncu';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Ad')
                ->required()
                ->maxLength(24),

            Forms\Components\TextInput::make('shirt_number')
                ->label('Forma no')
                ->numeric()
                ->minValue(1)
                ->maxValue(99),

            Forms\Components\Select::make('positions')
                ->label('Pozisyonlar')
                ->multiple()
                ->options(Attributes::POSITIONS)
                ->helperText('Sıralama önceliği belirler (ilk seçilen 1. öncelik).'),
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

                Tables\Columns\TextColumn::make('shirt_number')
                    ->label('No')
                    ->badge()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('positions')
                    ->label('Pozisyonlar')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Hesap')
                    ->placeholder('Misafir')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('attribute_ratings_count')
                    ->label('Oylama')
                    ->counts('attributeRatings'),

                Tables\Columns\TextColumn::make('overall')
                    ->label('OVR')
                    ->state(fn (Model $record): string => $record->overallIsPublic() ? number_format($record->overall(), 1) : '?'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
