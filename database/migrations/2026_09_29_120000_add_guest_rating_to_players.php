<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Misafir oyuncunun başkanın elle ayarladığı puanı (boşsa Player::GUEST_RATING). */
    public function up(): void
    {
        if (Schema::hasColumn('players', 'guest_rating')) {
            return;
        }

        Schema::table('players', function (Blueprint $table) {
            $table->decimal('guest_rating', 3, 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('guest_rating');
        });
    }
};
