<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RSVP artık player bazlı: misafir oyuncunun katılımını başkan işaretler.
        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // going, not_going, maybe
            $table->unsignedSmallInteger('waitlist_position')->nullable();
            $table->string('team', 1)->nullable(); // A, B — kadro kurulunca atanır
            $table->timestamps();

            $table->unique(['match_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsvps');
    }
};
