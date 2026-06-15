<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\GroupResource;
use App\Filament\Resources\MatchResource;
use App\Filament\Resources\UserResource;
use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_normal_kullanici_panele_giremez_admin_girer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();

        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_resource_sayfalari_acilir(): void
    {
        $admin = $this->admin();
        $group = Group::create(['owner_id' => $admin->id, 'name' => 'Test Grubu']);
        $group->members()->attach($admin->id, ['role' => 'owner']);
        $group->matches()->create([
            'created_by' => $admin->id,
            'title' => 'Test maçı',
            'starts_at' => now()->addDay(),
            'capacity' => 14,
        ]);

        $this->actingAs($admin);

        $this->get(UserResource::getUrl('index'))->assertOk();
        $this->get(GroupResource::getUrl('index'))->assertOk();
        $this->get(MatchResource::getUrl('index'))->assertOk();
        $this->get(ManageSettings::getUrl())->assertOk();
    }

    public function test_admin_yetkisi_panelden_verilir(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(UserResource\Pages\EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['is_admin' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->refresh()->is_admin);
    }

    public function test_site_ayarlari_kaydedilir(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageSettings::class)
            ->fillForm([
                'site_name' => 'Kadro Kur',
                'default_capacity' => 12,
                'min_ratings_visibility' => 3,
                'squad_approval_percent' => 70,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('12', Setting::get('default_capacity'));
        $this->assertSame(3, Setting::int('min_ratings_visibility', 5));
        $this->assertSame(70, Setting::int('squad_approval_percent', 60));
    }
}
