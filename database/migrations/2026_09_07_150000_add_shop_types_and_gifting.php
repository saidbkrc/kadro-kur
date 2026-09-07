<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yeni kozmetik türleri (forma deseni, gol sevinci, avatar halkası, rozet
     * vitrini) + mağazada hediye etme.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['equipped_kit', 'equipped_celebration', 'equipped_avatar', 'equipped_showcase'] as $kolon) {
                if (! Schema::hasColumn('users', $kolon)) {
                    $table->string($kolon, 40)->nullable();
                }
            }

            // Vitrinde öne çıkarılan rozet anahtarları
            if (! Schema::hasColumn('users', 'showcase_badges')) {
                $table->json('showcase_badges')->nullable();
            }
        });

        Schema::table('cim_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('cim_purchases', 'gifted_by')) {
                $table->foreignId('gifted_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cim_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gifted_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'equipped_kit', 'equipped_celebration', 'equipped_avatar',
                'equipped_showcase', 'showcase_badges',
            ]);
        });
    }
};
