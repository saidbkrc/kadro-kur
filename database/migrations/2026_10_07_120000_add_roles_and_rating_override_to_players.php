<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * roles: başkanın atadığı halı saha mevkileri (Stoper, Sol Bek…; en çok 2, sıra = öncelik).
     *        Kadro dengesi ve saha dizilişi bunu kullanır; boşsa oyuncunun genel pozisyonu.
     * rating_forced_public: eşik sayıda oy almamış üyenin oy ortalamasını başkan açtı mı.
     * (Elle verilen puan için mevcut guest_rating kolonu kullanılır.)
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            if (! Schema::hasColumn('players', 'roles')) {
                $table->json('roles')->nullable();
            }
            if (! Schema::hasColumn('players', 'rating_forced_public')) {
                $table->boolean('rating_forced_public')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['roles', 'rating_forced_public']);
        });
    }
};
