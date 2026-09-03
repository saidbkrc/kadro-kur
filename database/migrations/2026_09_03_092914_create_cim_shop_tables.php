<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Çim mağazası: kozmetik satın alımlar + kuşanılan görünüm. */
    public function up(): void
    {
        if (! Schema::hasTable('cim_purchases')) {
            Schema::create('cim_purchases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('item_key', 40);
                $table->unsignedInteger('price');
                $table->timestamps();
                $table->unique(['user_id', 'item_key']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['equipped_frame', 'equipped_color', 'equipped_title'] as $kolon) {
                if (! Schema::hasColumn('users', $kolon)) {
                    $table->string($kolon, 40)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['equipped_frame', 'equipped_color', 'equipped_title']);
        });
        Schema::dropIfExists('cim_purchases');
    }
};
