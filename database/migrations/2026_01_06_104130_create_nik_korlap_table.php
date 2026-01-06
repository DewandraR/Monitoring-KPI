<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nik_korlap', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 20)->unique();
            $table->string('nama', 100);
            $table->json('wc_korlap')->nullable(); // simpan banyak WC dalam array JSON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nik_korlap');
    }
};
