<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Tercih edilen ayak: right (sağ), left (sol), both (çift ayak).
            // Saha dizilişinde sağ/sol kanat yerleşimini belirler.
            $table->string('foot', 8)->default('right')->after('positions');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('foot');
        });
    }
};
