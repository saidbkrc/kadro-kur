<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Oyuncu kartı fotoğrafı (public disk yolu, kullanıcı kendi profilinden yükler). */
    public function up(): void
    {
        if (Schema::hasColumn('players', 'photo_path')) {
            return; // tekrar çalıştırılabilir
        }

        Schema::table('players', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('foot');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
