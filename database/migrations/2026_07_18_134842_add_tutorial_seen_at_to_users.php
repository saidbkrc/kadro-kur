<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tanıtım turu görüldü mü (null = hiç görmedi → ilk girişte otomatik açılır). */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'tutorial_seen_at')) {
            return; // tekrar çalıştırılabilir
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('tutorial_seen_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tutorial_seen_at');
        });
    }
};
