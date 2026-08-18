<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kombine kupon (slip) + Çim hareket geçmişi. */
    public function up(): void
    {
        if (! Schema::hasTable('prediction_slips')) {
            Schema::create('prediction_slips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('group_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('stake');
                $table->decimal('total_odds', 8, 2);
                $table->string('status', 10)->default('pending'); // pending|won|lost|void
                $table->integer('payout')->default(0);
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('cim_transactions')) {
            Schema::create('cim_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->integer('amount');                 // + kazanç/yükleme, − kupon
                $table->string('type', 12);                // grant|bet|win|refund
                $table->string('description', 120)->nullable();
                $table->integer('balance_after');
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        Schema::table('predictions', function (Blueprint $table) {
            if (! Schema::hasColumn('predictions', 'slip_id')) {
                $table->foreignId('slip_id')->nullable()->after('match_id')
                    ->constrained('prediction_slips')->cascadeOnDelete();
            }
        });

        // Kombine bacakları aynı market'te tekil kaydı bozar — kısıt uygulama katmanına taşındı
        try {
            Schema::table('predictions', function (Blueprint $table) {
                $table->dropUnique('predictions_user_id_match_id_market_key_unique');
            });
        } catch (\Throwable $e) {
            // index yoksa (tekrar çalıştırma) sorun değil
        }
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('slip_id');
        });
        Schema::dropIfExists('cim_transactions');
        Schema::dropIfExists('prediction_slips');
    }
};
