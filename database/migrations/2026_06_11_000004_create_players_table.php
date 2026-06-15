<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grup içi oyuncu kimliği. user_id null ise "misafir": başkanın isimle
        // eklediği, henüz hesabı olmayan oyuncu. Kişi kayıt olunca eşleştirilir,
        // puanları/istatistikleri korunur.
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 24);
            $table->unsignedTinyInteger('shirt_number')->nullable(); // 1-99
            $table->json('positions'); // öncelik sıralı: ["OS","FV"] — KL tek başına
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
