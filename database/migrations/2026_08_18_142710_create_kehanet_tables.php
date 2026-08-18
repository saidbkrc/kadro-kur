<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kehanet (eğlence amaçlı tahmin oyunu) altyapısı.
     * Para birimi "Çim" tamamen sanaldır; gerçek parayla ilişkisi yoktur.
     */
    public function up(): void
    {
        // Başkanın maç sonrası işaretlediği olaylar (gerginlik, günün çalımı vb.)
        if (! Schema::hasTable('match_events')) {
            Schema::create('match_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
                $table->string('event_key', 30);
                $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->unique(['match_id', 'event_key']);
            });
        }

        // Kuponlar: kullanıcı başına maç+market'te tek tahmin
        if (! Schema::hasTable('predictions')) {
            Schema::create('predictions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
                $table->string('market_key', 30);
                $table->string('selection', 30);        // 'A' / 'X' / 'B' / 'over' / player_id
                $table->decimal('odds', 5, 2);          // kupon anında kilitlenir
                $table->unsignedInteger('stake');
                $table->string('status', 10)->default('pending'); // pending|won|lost|void
                $table->integer('payout')->default(0);
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'match_id', 'market_key']);
                $table->index(['match_id', 'status']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cim_balance')) {
                $table->integer('cim_balance')->default(0)->after('tutorial_seen_at');
            }
            if (! Schema::hasColumn('users', 'cim_granted_at')) {
                $table->timestamp('cim_granted_at')->nullable()->after('cim_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cim_balance', 'cim_granted_at']);
        });
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('match_events');
    }
};
