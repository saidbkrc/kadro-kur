<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anonim özellik bazlı akran puanlaması: her üye, her oyuncuyu özellik
        // özellik 1-10 puanlar. OVR tüm puanlayıcıların ortalamasından hesaplanır.
        // Puanlayan kimliği asla gösterilmez.
        Schema::create('attribute_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete(); // puanlanan
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->json('scores'); // {hiz: 7, sut: 6, ...} — 1-10
            $table->timestamps();

            $table->unique(['player_id', 'rater_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_ratings');
    }
};
