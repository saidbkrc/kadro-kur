<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Saha diziliş görselinde gösterilen kozmetik rozet. */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'equipped_pitch')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('equipped_pitch', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('equipped_pitch');
        });
    }
};
