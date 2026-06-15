<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eşleşme kuralları: "X ile Y ayrı takımlarda" / "aynı takımda".
        // Dengeleme algoritması bu kurallara uymak zorunda.
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_a_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('player_b_id')->constrained('players')->cascadeOnDelete();
            $table->string('type'); // apart, together
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
