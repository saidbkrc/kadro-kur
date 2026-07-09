<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nitelik onayları (LinkedIn tarzı): üye, takım arkadaşının niteliğini onaylar. */
    public function up(): void
    {
        Schema::create('player_trait_endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('trait_key', 40);
            $table->foreignId('endorser_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['player_id', 'trait_key', 'endorser_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_trait_endorsements');
    }
};
