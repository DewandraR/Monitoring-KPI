<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->string('tag', 50)->nullable()->after('total_time_wi');
        });
    }

    public function down(): void
    {
        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }
};
