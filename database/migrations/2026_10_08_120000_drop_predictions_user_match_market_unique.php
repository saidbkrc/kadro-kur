<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * predictions (user_id, match_id, market_key) tekilliğini gerçekten kaldırır.
     *
     * 2026_08_18 kombine migration'ı bunu try/catch içinde silmeye çalışıyordu;
     * MySQL'de index user_id foreign key'ine hizmet ettiği için silme reddedildi
     * (1553) ve hata yutuldu. Canlıda tekillik kaldı → kombine bacağı olan ya da
     * iade edilmiş bir market'e tekrar kupon "Duplicate entry" ile patlıyordu.
     * Kural uygulama katmanında (KehanetService::placeBet) duruyor.
     *
     * Sıra önemli: önce user_id'ye kendi index'i (FK artık ona yaslanır), sonra
     * tekillik silinir. Hata yutulmaz — başarısız olursa deploy'da görünür.
     */
    public function up(): void
    {
        if (! Schema::hasIndex('predictions', 'predictions_user_id_index')) {
            Schema::table('predictions', function (Blueprint $table) {
                $table->index('user_id');
            });
        }

        if (Schema::hasIndex('predictions', 'predictions_user_id_match_id_market_key_unique')) {
            Schema::table('predictions', function (Blueprint $table) {
                $table->dropUnique('predictions_user_id_match_id_market_key_unique');
            });
        }
    }

    public function down(): void
    {
        // Bilerek boş: tekillik geri getirilmez (kombine bacaklarıyla çakışır) ve
        // user_id index'i foreign key'e hizmet ettiği için MySQL silmeyi reddeder.
    }
};
