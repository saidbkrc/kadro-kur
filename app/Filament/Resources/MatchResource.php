<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MatchResource\Pages;
use App\Models\FootballMatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MatchResource extends Resource
{
    protected static ?string $model = FootballMatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Yönetim';

    protected static ?string $navigationLabel = 'Maçlar';

    protected static ?string $modelLabel = 'maç';

    protected static ?string $pluralModelLabel = 'Maçlar';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Maç')->schema([
                Forms\Components\Select::make('group_id')
                    ->label('Grup')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('title')
                    ->label('Başlık')
                    ->required()
                    ->maxLength(100),

                Forms\Components\TextInput::make('location')
                    ->label('Saha')
                    ->maxLength(100),

                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Tarih ve saat')
                    ->seconds(false)
                    ->required(),

                Forms\Components\TextInput::make('capacity')
                    ->label('Kapasite')
                    ->numeric()
                    ->minValue(4)
                    ->maxValue(24),

                Forms\Components\Select::make('status')
                    ->label('Durum')
                    ->options([
                        'scheduled' => 'Planlandı',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal',
                    ])
                    ->required(),
            ])->columns(2),

            Forms\Components\Section::make('Sonuç (düzeltme)')->schema([
                Forms\Components\TextInput::make('team_a_score')
                    ->label('Turuncu skor')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99),

                Forms\Components\TextInput::make('team_b_score')
                    ->label('Yeşil skor')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99),

                Forms\Components\Select::make('squad_status')
                    ->label('Kadro durumu')
                    ->options([
                        'none' => 'Kurulmadı',
                        'voting' => 'Oylamada',
                        'approved' => 'Onaylandı',
                    ]),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Maç')
                    ->searchable()
                    ->description(fn (FootballMatch $record): ?string => $record->location),

                Tables\Columns\TextColumn::make('group.name')
                    ->label('Grup')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'scheduled' => 'Planlandı',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('score')
                    ->label('Skor')
                    ->state(fn (FootballMatch $record): string => $record->team_a_score === null
                        ? '—'
                        : $record->team_a_score.' - '.$record->team_b_score),

                Tables\Columns\TextColumn::make('rsvps_count')
                    ->label('Katılım')
                    ->counts('rsvps')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'scheduled' => 'Planlandı',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatches::route('/'),
            'edit' => Pages\EditMatch::route('/{record}/edit'),
        ];
    }
}
