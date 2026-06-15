<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MVP oyu: oy veren gerçek kullanıcı, oy verilen oyuncu (misafir olabilir).
        // Maç başına 1 oy, değiştirilemez, anonim.
        Schema::create('mvp_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['match_id', 'voter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mvp_votes');
    }
};
