<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kayıtlı kadro şablonu: grup başına en fazla 3. teams = {player_id: "A"|"B"}.
        // "Şablondan kullan" deyince takımlar bu eşlemeden gelir.
        Schema::create('squad_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->json('teams');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('squad_templates');
    }
};
