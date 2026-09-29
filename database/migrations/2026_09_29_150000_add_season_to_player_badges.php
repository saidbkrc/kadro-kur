<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rozetler sezonluk: aynı rozet her sezon yeniden kazanılır. Eski kayıtlar
     * season = NULL kalır (sezon sistemi öncesi); açılış sezonu onları sahiplenir.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('player_badges', 'season')) {
            Schema::table('player_badges', function (Blueprint $table) {
                $table->string('season', 7)->nullable()->after('badge_key');
            });
        }

        // Önce yeni tekillik (player_id ile başladığı için player_id foreign key'ine
        // de hizmet eder), sonra eskisi — tersi sırada MySQL eski index'i FK yüzünden
        // silmeyi reddeder.
        Schema::table('player_badges', function (Blueprint $table) {
            $table->unique(['player_id', 'badge_key', 'season'], 'player_badges_player_badge_season_unique');
        });

        Schema::table('player_badges', function (Blueprint $table) {
            $table->dropUnique(['player_id', 'badge_key']);
        });
    }

    public function down(): void
    {
        // Sezonlu kopyalar silinir (eski tekillik tek kayıt ister)
        \Illuminate\Support\Facades\DB::table('player_badges')->whereNotNull('season')->delete();

        Schema::table('player_badges', function (Blueprint $table) {
            $table->unique(['player_id', 'badge_key']);
        });

        Schema::table('player_badges', function (Blueprint $table) {
            $table->dropUnique('player_badges_player_badge_season_unique');
            $table->dropColumn('season');
        });
    }
};
