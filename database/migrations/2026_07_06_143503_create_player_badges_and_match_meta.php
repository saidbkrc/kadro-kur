<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * player_badges: kazanılan rozetlerin kalıcı kaydı — yeni kazanım tespiti ve
     * "rozet kazandın" bildirimi için (rozetler yine türetilmiş hesaplanır).
     * matches meta: maç özeti bildirimi (1 kez) + skor düzenleme şeffaflık kaydı.
     */
    public function up(): void
    {
        // Tekrar çalıştırılabilir: elle deploy'da var olanlar atlanır
        if (! Schema::hasTable('player_badges')) {
            Schema::create('player_badges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('player_id')->constrained()->cascadeOnDelete();
                $table->string('badge_key', 40);
                $table->timestamps();
                $table->unique(['player_id', 'badge_key']);
            });
        }

        Schema::table('matches', function (Blueprint $table) {
            if (! Schema::hasColumn('matches', 'digest_sent_at')) {
                $table->timestamp('digest_sent_at')->nullable()->after('reminder_sent_at');
            }
            if (! Schema::hasColumn('matches', 'result_edited_at')) {
                $table->timestamp('result_edited_at')->nullable()->after('digest_sent_at');
            }
            if (! Schema::hasColumn('matches', 'result_edited_by')) {
                $table->foreignId('result_edited_by')->nullable()->after('result_edited_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_edited_by');
            $table->dropColumn(['digest_sent_at', 'result_edited_at']);
        });

        Schema::dropIfExists('player_badges');
    }
};
