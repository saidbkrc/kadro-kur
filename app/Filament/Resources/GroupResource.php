<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupResource\Pages;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Yönetim';

    protected static ?string $navigationLabel = 'Gruplar';

    protected static ?string $modelLabel = 'grup';

    protected static ?string $pluralModelLabel = 'Gruplar';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Grup Bilgileri')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Grup adı')
                    ->required()
                    ->maxLength(50),

                Forms\Components\Select::make('owner_id')
                    ->label('Başkan')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->label('Açıklama')
                    ->maxLength(500)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('invite_code')
                    ->label('Davet kodu')
                    ->disabled()
                    ->dehydrated(false),
            ])->columns(2),

            Forms\Components\Section::make('Haftalık Maç Ayarları')->schema([
                Forms\Components\Select::make('match_day')
                    ->label('Maç günü')
                    ->options([
                        1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe',
                        5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar',
                    ]),

                Forms\Components\TimePicker::make('match_time')
                    ->label('Saat')
                    ->seconds(false),

                Forms\Components\TextInput::make('default_location')
                    ->label('Varsayılan saha')
                    ->maxLength(100),

                Forms\Components\TextInput::make('capacity')
                    ->label('Kapasite')
                    ->numeric()
                    ->minValue(4)
                    ->maxValue(24)
                    ->default(14),

                Forms\Components\Toggle::make('auto_schedule')
                    ->label('Otomatik maç oluştur'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Grup')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Başkan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('members_count')
                    ->label('Üye')
                    ->counts('members')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('players_count')
                    ->label('Oyuncu')
                    ->counts('players')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('matches_count')
                    ->label('Maç')
                    ->counts('matches')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\IconColumn::make('auto_schedule')
                    ->label('Otomatik')
                    ->boolean(),

                Tables\Columns\TextColumn::make('invite_code')
                    ->label('Davet kodu')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Kuruldu')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('auto_schedule')
                    ->label('Otomatik maç'),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            GroupResource\RelationManagers\PlayersRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('owner');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}
