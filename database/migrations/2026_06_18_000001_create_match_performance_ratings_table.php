<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Maç sonu performans puanı: o maçın asıl kadrosundakiler birbirini 1-10 puanlar.
        // Anonim. Nihai görünen puan = OVR×0.8 + (son 5 maç performans ort.)×0.2.
        Schema::create('match_performance_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1-10
            $table->timestamps();

            $table->unique(['match_id', 'rater_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_performance_ratings');
    }
};
