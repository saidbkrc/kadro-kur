<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('invite_code', 16)->unique(); // davet linki: /davet/{invite_code}

            // Haftalık otomatik maç ayarları — bir kere kurulur, her hafta tekrar eder
            $table->unsignedTinyInteger('match_day')->nullable();   // 1=Pzt ... 7=Paz (ISO)
            $table->time('match_time')->nullable();                 // örn. 21:00
            $table->string('default_location')->nullable();
            $table->unsignedTinyInteger('capacity')->default(14);   // 7v7
            $table->boolean('auto_schedule')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
