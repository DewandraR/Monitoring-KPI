<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_time_wi', function (Blueprint $table) {
            // Mengubah tipe kolom 'tag' menjadi JSON
            // Kita gunakan nullable() karena di gambar kolom tersebut boleh NULL
            $table->json('tag')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_time_wi', function (Blueprint $table) {
            // Mengembalikan ke tipe data asal (VARCHAR 50 sesuai gambar phpMyAdmin)
            $table->string('tag', 50)->nullable()->change();
        });
    }
};