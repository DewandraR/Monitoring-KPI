<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // default sesuai permintaan:
            // ALL = false, DEVISI = null, ARBPL = null
            $table->boolean('scope_all')->default(false)->after('remember_token');
            $table->json('scope_devisi')->nullable()->after('scope_all');
            $table->json('scope_arbpl')->nullable()->after('scope_devisi');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['scope_all', 'scope_devisi', 'scope_arbpl']);
        });
    }
};
