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
        Schema::create('yppr058_sap_logs', function (Blueprint $table) {
            $table->id();

            // Info batch / run
            $table->uuid('batch_id')->index(); // satu kali klik SAVE = satu batch
            $table->string('sap_user')->index(); // user SAP yang dipakai

            // Data input ke RFC
            $table->string('pernr')->index();
            $table->string('cname')->nullable();
            $table->string('arbpl')->nullable();
            $table->string('start_date', 8)->nullable(); // yyyymmdd
            $table->string('end_date', 8)->nullable();   // yyyymmdd

            $table->bigInteger('mint2')->nullable();
            $table->bigInteger('mintu')->nullable();
            $table->bigInteger('mintu2')->nullable();
            $table->bigInteger('mintu3')->nullable();

            // Hasil SAP (dari E_RETURN di Python)
            $table->boolean('ok')->default(false); // true = sukses, false = gagal
            $table->string('return_type', 1)->nullable();    // S / E / W / I / dll
            $table->string('return_id', 20)->nullable();     // ID message SAP (CLASS)
            $table->string('return_number', 10)->nullable(); // nomor message
            $table->string('return_message', 255)->nullable();
            $table->string('message_v1', 50)->nullable();
            $table->string('message_v2', 50)->nullable();
            $table->string('message_v3', 50)->nullable();
            $table->string('message_v4', 50)->nullable();

            // Kalau mau simpan error raw dari Python kalau ada
            $table->text('error_raw')->nullable();

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yppr058_sap_logs');
    }
};
