<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title'); // "Salı 21:00 maçı"
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->unsignedTinyInteger('capacity')->default(14);
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled

            // Kadro kurma + onay oylaması
            $table->string('squad_status')->default('none'); // none, voting, approved
            $table->string('formation_a', 8)->nullable();    // "3-1-2" | null = otomatik
            $table->string('formation_b', 8)->nullable();
            $table->json('pitch_layout')->nullable();        // {player_id: {x, y}} — sahada elle taşınanlar

            // Sonuç + MVP penceresi
            $table->unsignedTinyInteger('team_a_score')->nullable();
            $table->unsignedTinyInteger('team_b_score')->nullable();
            $table->dateTime('mvp_closes_at')->nullable();   // skor girilince now()+24 saat

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
