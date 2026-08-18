<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Maç başarı ödülleri (Çim) bir kez dağıtılsın diye işaretlenir. */
    public function up(): void
    {
        if (Schema::hasColumn('matches', 'bonus_awarded_at')) {
            return;
        }

        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('bonus_awarded_at')->nullable()->after('forma_goal_player_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('bonus_awarded_at');
        });
    }
};
