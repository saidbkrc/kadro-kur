<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sezon sonu oylaması (en iyi oyuncu, takım oyuncusu…). Anonim: arayüz yalnızca
     * toplamları gösterir. Kişi kategori başına tek oy verir, kapanışa kadar değiştirebilir.
     */
    public function up(): void
    {
        if (Schema::hasTable('season_votes')) {
            return;
        }

        Schema::create('season_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('season', 7);
            $table->string('category', 20);
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'season', 'category', 'voter_id']);
            $table->index(['group_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_votes');
    }
};
