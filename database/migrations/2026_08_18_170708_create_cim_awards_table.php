<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Çim ödül defteri: her ödül bir kez verilsin diye kayıt tutar.
     * ref = ödülün bağlı olduğu şey ("match:12", "player:5", "month:2026-08"),
     * tek seferlik ödüllerde boş kalır.
     */
    public function up(): void
    {
        if (Schema::hasTable('cim_awards')) {
            return;
        }

        Schema::create('cim_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('award_key', 30);
            $table->string('ref', 40)->default('');
            $table->unsignedInteger('amount');
            $table->timestamps();
            $table->unique(['user_id', 'group_id', 'award_key', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cim_awards');
    }
};
