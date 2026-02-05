<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_time_wi', function (Blueprint $table) {
            // 1) kolom sementara JSON
            $table->json('tag_json')->nullable()->after('tag');
        });

        /**
         * 2) Migrasi data lama (opsional, tapi saya buat aman)
         * Asumsi: tag lama adalah string sederhana (mis. "ABC" atau "A,B,C").
         * - Kalau kosong/null => tetap null
         * - Kalau ada koma => jadi JSON array ["A","B","C"]
         * - Kalau tidak ada koma => jadi JSON string "ABC"
         *
         * Kalau format tag lama kamu beda, bilang aja—nanti saya sesuaikan.
         */
        DB::statement("
            UPDATE daily_time_wi
            SET tag_json =
                CASE
                    WHEN tag IS NULL OR TRIM(tag) = '' THEN NULL
                    WHEN LOCATE(',', tag) > 0 THEN
                        JSON_ARRAYAGG(TRIM(jt.val))
                    ELSE
                        JSON_QUOTE(TRIM(tag))
                END
            WHERE tag IS NOT NULL
        ");

        // Catatan: JSON_ARRAYAGG butuh trick lewat JSON_TABLE. Kalau MySQL kamu < 8.0, pakai versi update lain di bawah.
        // Untuk MySQL 8+, ganti statement di atas dengan versi yang benar-benar valid berikut:

        DB::statement("
            UPDATE daily_time_wi d
            SET d.tag_json =
                CASE
                    WHEN d.tag IS NULL OR TRIM(d.tag) = '' THEN NULL
                    WHEN LOCATE(',', d.tag) > 0 THEN (
                        SELECT JSON_ARRAYAGG(TRIM(j.val))
                        FROM JSON_TABLE(
                            CONCAT(
                                '[\"',
                                REPLACE(REPLACE(TRIM(d.tag), '\"', '\\\\\"'), ',', '\",\"'),
                                '\"]'
                            ),
                            '$[*]' COLUMNS (val VARCHAR(255) PATH '$')
                        ) j
                    )
                    ELSE JSON_QUOTE(TRIM(d.tag))
                END
            WHERE d.tag IS NOT NULL
        ");

        // 3) drop kolom lama
        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->dropColumn('tag');
        });

        // 4) rename tag_json -> tag
        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->renameColumn('tag_json', 'tag');
        });
    }

    public function down(): void
    {
        // Balikin ke varchar(50) nullable
        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->string('tag_old', 50)->nullable()->after('nik');
        });

        // Convert JSON -> string (ambil apa adanya; array jadi string JSON)
        DB::statement("
            UPDATE daily_time_wi
            SET tag_old =
                CASE
                    WHEN tag IS NULL THEN NULL
                    ELSE JSON_UNQUOTE(
                        CASE
                            WHEN JSON_TYPE(tag) = 'STRING' THEN tag
                            ELSE JSON_EXTRACT(tag, '$')
                        END
                    )
                END
        ");

        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->dropColumn('tag');
        });

        Schema::table('daily_time_wi', function (Blueprint $table) {
            $table->renameColumn('tag_old', 'tag');
        });
    }
};
