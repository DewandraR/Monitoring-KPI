<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NikKorlap;

class NikKorlapPlant3000PaintingSeeder extends Seeder
{
    public function run(): void
    {
        // Data sumber: WC Personal -> Korlap
        // Catatan: beberapa baris duplikat (Role kosong / sama) -> kita unique-kan di wc_korlap
        $source = [
            ['wc' => 'WC261', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],
            ['wc' => 'WC262', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],
            ['wc' => 'WC263', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],

            ['wc' => 'WC264', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],

            ['wc' => 'WC265', 'nik' => '10001035', 'nama' => 'NURKHOLIS'],
            ['wc' => 'WC266', 'nik' => '10001035', 'nama' => 'NURKHOLIS'],
            ['wc' => 'WC267', 'nik' => '10001035', 'nama' => 'NURKHOLIS'],
            ['wc' => 'WC268', 'nik' => '10001035', 'nama' => 'NURKHOLIS'],
            ['wc' => 'WC269', 'nik' => '10001035', 'nama' => 'NURKHOLIS'],
            ['wc' => 'WC270', 'nik' => '10001035', 'nama' => 'NURKHOLIS'],
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

            // kalau nama di row beda-beda, kamu bisa pilih yang pertama; di sini kita pakai yang terakhir yang non-empty
            if ($nama !== '') {
                $grouped[$nik]['nama'] = $nama;
            }

            $grouped[$nik]['wc_korlap'][] = $wc;
        }

        // Insert/update
        foreach ($grouped as $row) {
            $row['wc_korlap'] = array_values(array_unique($row['wc_korlap']));
            sort($row['wc_korlap']); // optional, biar rapi urut

            NikKorlap::updateOrCreate(
                ['nik' => $row['nik'], 'plant' => $row['plant']],
                ['nama' => $row['nama'], 'wc_korlap' => $row['wc_korlap']]
            );
        }
    }
}
