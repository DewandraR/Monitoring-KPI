<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nik_korlap', function (Blueprint $table) {
            // plant disimpan string 4 digit (contoh: "2000")
            $table->string('plant', 4)->default('NULL')->after('nama');

            // opsional: index supaya filter per plant cepat
            $table->index('plant');
        });
    }

    public function down(): void
    {
        Schema::table('nik_korlap', function (Blueprint $table) {
            $table->dropIndex(['plant']);
            $table->dropColumn('plant');
        });
    }
};
