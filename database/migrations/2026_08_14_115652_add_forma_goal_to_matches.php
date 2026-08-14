<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forma golü: maç başına EN FAZLA BİR oyuncu işaretlenir.
     * Skora ve gol istatistiğine etki etmez — ayrı bir onur/anı kaydıdır.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('forma_goal_player_id')->nullable()->after('result_edited_by')
                ->constrained('players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forma_goal_player_id');
        });
    }
};
