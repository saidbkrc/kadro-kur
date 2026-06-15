<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Panel yöneticisi (sadece local) — is_admin fillable dışında, bilinçli forceFill
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@kadrokur.test',
        ])->forceFill(['is_admin' => true])->save();

        User::factory()->create([
            'name' => 'Said',
            'email' => 'saidbkrc14@gmail.com',
            'password' => '12345678',
        ]);
    }
}
