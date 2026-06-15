<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Yönetim';

    protected static ?string $navigationLabel = 'Site Ayarları';

    protected static ?string $title = 'Site Ayarları';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.manage-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site_name' => Setting::get('site_name', config('app.name')),
            'default_capacity' => Setting::int('default_capacity', 14),
            'min_ratings_visibility' => Setting::int('min_ratings_visibility', 5),
            'squad_approval_percent' => Setting::int('squad_approval_percent', 60),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Genel')
                    ->description('Site genelinde geçerli temel ayarlar.')
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Site adı')
                            ->required()
                            ->maxLength(50),
                    ]),

                Section::make('Maç ve Kadro')
                    ->description('Yeni gruplar ve kadro kurma için varsayılanlar.')
                    ->schema([
                        TextInput::make('default_capacity')
                            ->label('Varsayılan kadro kapasitesi')
                            ->helperText('Yeni kurulan gruplara uygulanır.')
                            ->numeric()
                            ->minValue(4)
                            ->maxValue(24)
                            ->required(),

                        TextInput::make('min_ratings_visibility')
                            ->label('Puan görünürlük eşiği')
                            ->helperText('Bir oyuncunun ortalaması, en az bu kadar kişi puanlayınca görünür.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->required(),

                        TextInput::make('squad_approval_percent')
                            ->label('Kadro onay yüzdesi (%)')
                            ->helperText('Kadronun kesinleşmesi için gereken evet oyu oranı.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, (string) $value);
        }

        Notification::make()
            ->title('Ayarlar kaydedildi')
            ->success()
            ->send();
    }
}
