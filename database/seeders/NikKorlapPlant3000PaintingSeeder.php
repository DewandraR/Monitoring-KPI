<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NikKorlap;

class NikKorlapPlant3000PaintingSeeder extends Seeder
{
    public function run(): void
    {
        // Data sumber: WC Personal -> Korlap (Plant 3000)
        // Catatan: banyak baris duplikat (INDUK / kosong) -> akan di-unique-kan saat grouping
        $source = [
            // 10005166 - DJ AKROM ABU YAHYA (A1-A4)
            ['wc' => 'WC261', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],
            ['wc' => 'WC262', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],
            ['wc' => 'WC263', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],
            ['wc' => 'WC264', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],

            // 10001035 - Nurkholis (A5-A10)
            ['wc' => 'WC265', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC266', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC267', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC268', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC269', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC270', 'nik' => '10001035', 'nama' => 'Nurkholis'],

            // 10004416 - Muhammad Zamakhsyari (A11, A13, B7, B8, B9, B10)
            ['wc' => 'WC444', 'nik' => '10004416', 'nama' => 'Muhammad Zamakhsyari'],
            ['wc' => 'WC443', 'nik' => '10004416', 'nama' => 'Muhammad Zamakhsyari'],
            ['wc' => 'WC452', 'nik' => '10004416', 'nama' => 'Muhammad Zamakhsyari'],
            ['wc' => 'WC863', 'nik' => '10004416', 'nama' => 'Muhammad Zamakhsyari'],

            // 10000992 - Sugiyanto (B1-B6)
            ['wc' => 'WC445', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC446', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC447', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC448', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC449', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC450', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
        ];

        $plant = '3000';

        // Group per korlap (nik)
        $grouped = [];

        foreach ($source as $row) {
            $nik  = trim((string)($row['nik'] ?? ''));
            $nama = trim((string)($row['nama'] ?? ''));
            $wc   = strtoupper(trim((string)($row['wc'] ?? '')));

            if ($nik === '' || $wc === '') continue;

            if (!isset($grouped[$nik])) {
                $grouped[$nik] = [
                    'nik' => $nik,
                    'nama' => $nama,
                    'plant' => $plant,
                    'wc_korlap' => [],
                ];
            }

            // pakai nama terakhir yang non-empty
            if ($nama !== '') {
                $grouped[$nik]['nama'] = $nama;
            }

            $grouped[$nik]['wc_korlap'][] = $wc;
        }

        // Insert/update
        foreach ($grouped as $row) {
            $row['wc_korlap'] = array_values(array_unique($row['wc_korlap']));
            sort($row['wc_korlap']); // biar rapi

            NikKorlap::updateOrCreate(
                ['nik' => $row['nik'], 'plant' => $row['plant']],
                ['nama' => $row['nama'], 'wc_korlap' => $row['wc_korlap']]
            );
        }
    }
}
