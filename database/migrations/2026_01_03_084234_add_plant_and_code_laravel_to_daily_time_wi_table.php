<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->string('plant', 10)->after('tanggal')->index();
            $table->string('kode_laravel', 10)->after('plant')->index();
        });
    }

    public function down(): void
    {
        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->dropColumn(['plant', 'kode_laravel']);
        });
    }
};
