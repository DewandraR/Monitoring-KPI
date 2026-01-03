<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_time_wi', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->date('tanggal')->index();

            // nik biasanya max 12-20, sesuaikan kebutuhan
            $table->string('nik', 20)->index();

            $table->string('nama', 120)->nullable();

            // total_time_wi misal menit/jam, 16,2 seperti tabel contoh
            $table->decimal('total_time_wi', 16, 2)->default(0);

            $table->timestamps();

            // (opsional) cegah duplikat per hari per nik
            $table->unique(['tanggal', 'nik'], 'uniq_daily_time_wi_tanggal_nik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_time_wi');
    }
};
