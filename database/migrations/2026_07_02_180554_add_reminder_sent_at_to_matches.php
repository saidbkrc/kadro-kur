<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Maç hatırlatma bildirimi bir kez gönderilsin diye işaretlenir. */
    public function up(): void
    {
        if (Schema::hasColumn('matches', 'reminder_sent_at')) {
            return; // tekrar çalıştırılabilir: elle deploy'da kolon zaten varsa atla
        }

        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('mvp_closes_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
