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

            // 10000900 - MURSALIN (WC209 - WC214)
            ['wc' => 'WC209', 'nik' => '10000900', 'nama' => 'MURSALIN'],
            ['wc' => 'WC210', 'nik' => '10000900', 'nama' => 'MURSALIN'],
            ['wc' => 'WC211', 'nik' => '10000900', 'nama' => 'MURSALIN'],
            ['wc' => 'WC212', 'nik' => '10000900', 'nama' => 'MURSALIN'],
            ['wc' => 'WC213', 'nik' => '10000900', 'nama' => 'MURSALIN'],
            ['wc' => 'WC214', 'nik' => '10000900', 'nama' => 'MURSALIN'],

            // 10002555 - Heri Prima Setiawan (WC232, WC233, WC234, WC244, WC253)
            ['wc' => 'WC232', 'nik' => '10002555', 'nama' => 'Heri Prima Setiawan'],
            ['wc' => 'WC233', 'nik' => '10002555', 'nama' => 'Heri Prima Setiawan'],
            ['wc' => 'WC234', 'nik' => '10002555', 'nama' => 'Heri Prima Setiawan'],
            ['wc' => 'WC244', 'nik' => '10002555', 'nama' => 'Heri Prima Setiawan'],
            ['wc' => 'WC253', 'nik' => '10002555', 'nama' => 'Heri Prima Setiawan'],

            // 10005640 - Saiful Julkifli (WC238, WC242, WC243, WC247, WC249, WC250, WC252, WC253)
            ['wc' => 'WC238', 'nik' => '10005640', 'nama' => 'Saiful Julkifli'],
            ['wc' => 'WC242', 'nik' => '10005640', 'nama' => 'Saiful Julkifli'],
            ['wc' => 'WC243', 'nik' => '10005640', 'nama' => 'Saiful Julkifli'],
            ['wc' => 'WC247', 'nik' => '10005640', 'nama' => 'Saiful Julkifli'],
            ['wc' => 'WC249', 'nik' => '10005640', 'nama' => 'Saiful Julkifli'],
            ['wc' => 'WC250', 'nik' => '10005640', 'nama' => 'Saiful Julkifli'],
            ['wc' => 'WC252', 'nik' => '10005640', 'nama' => 'Saiful Julkifli'],
            ['wc' => 'WC253', 'nik' => '10005640', 'nama' => 'Saiful Julkifli'],

            // 10002069 - Ilham Alfian (WC259, WC393, WC418, WC419, WC420, WC421, WC422, WC424)
            ['wc' => 'WC259', 'nik' => '10002069', 'nama' => 'Ilham Alfian'],
            ['wc' => 'WC393', 'nik' => '10002069', 'nama' => 'Ilham Alfian'],
            ['wc' => 'WC418', 'nik' => '10002069', 'nama' => 'Ilham Alfian'],
            ['wc' => 'WC419', 'nik' => '10002069', 'nama' => 'Ilham Alfian'],
            ['wc' => 'WC420', 'nik' => '10002069', 'nama' => 'Ilham Alfian'],
            ['wc' => 'WC421', 'nik' => '10002069', 'nama' => 'Ilham Alfian'],
            ['wc' => 'WC422', 'nik' => '10002069', 'nama' => 'Ilham Alfian'],
            ['wc' => 'WC424', 'nik' => '10002069', 'nama' => 'Ilham Alfian'],

            // 10002427 - Fatwa Hidayat (WC260, WC426, WC430, WC431, WC433)
            ['wc' => 'WC260', 'nik' => '10002427', 'nama' => 'Fatwa Hidayat'],
            ['wc' => 'WC426', 'nik' => '10002427', 'nama' => 'Fatwa Hidayat'],
            ['wc' => 'WC430', 'nik' => '10002427', 'nama' => 'Fatwa Hidayat'],
            ['wc' => 'WC431', 'nik' => '10002427', 'nama' => 'Fatwa Hidayat'],
            ['wc' => 'WC433', 'nik' => '10002427', 'nama' => 'Fatwa Hidayat'],

            // 10005166 - DJ AKROM ABU YAHYA (WC261 - WC264)
            ['wc' => 'WC261', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],
            ['wc' => 'WC262', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],
            ['wc' => 'WC263', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],
            ['wc' => 'WC264', 'nik' => '10005166', 'nama' => 'DJ AKROM ABU YAHYA'],

            // 10001035 - Nurkholis (WC265 - WC270)
            ['wc' => 'WC265', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC266', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC267', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC268', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC269', 'nik' => '10001035', 'nama' => 'Nurkholis'],
            ['wc' => 'WC270', 'nik' => '10001035', 'nama' => 'Nurkholis'],

            // 10001842 - ABADI (WC271, WC272, WC273)
            ['wc' => 'WC271', 'nik' => '10001842', 'nama' => 'ABADI'],
            ['wc' => 'WC272', 'nik' => '10001842', 'nama' => 'ABADI'],
            ['wc' => 'WC273', 'nik' => '10001842', 'nama' => 'ABADI'],

            // 10005648 - Agung Prasetyo (WC271, WC273)
            ['wc' => 'WC271', 'nik' => '10005648', 'nama' => 'Agung Prasetyo'],
            ['wc' => 'WC273', 'nik' => '10005648', 'nama' => 'Agung Prasetyo'],

            // 10001181 - SAFRIAUL ABIDIN (WC305, WC314, WC547, WC548, WC549, WC674, WC682, WC683, WC685)
            ['wc' => 'WC305', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],
            ['wc' => 'WC314', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],
            ['wc' => 'WC547', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],
            ['wc' => 'WC548', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],
            ['wc' => 'WC549', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],
            ['wc' => 'WC674', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],
            ['wc' => 'WC682', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],
            ['wc' => 'WC683', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],
            ['wc' => 'WC685', 'nik' => '10001181', 'nama' => 'SAFRIAUL ABIDIN'],

            // 10000953 - Wahyu Adi Susilo (WC311, WC313, WC672, WC673, WC678)
            ['wc' => 'WC311', 'nik' => '10000953', 'nama' => 'Wahyu Adi Susilo'],
            ['wc' => 'WC313', 'nik' => '10000953', 'nama' => 'Wahyu Adi Susilo'],
            ['wc' => 'WC672', 'nik' => '10000953', 'nama' => 'Wahyu Adi Susilo'],
            ['wc' => 'WC673', 'nik' => '10000953', 'nama' => 'Wahyu Adi Susilo'],
            ['wc' => 'WC678', 'nik' => '10000953', 'nama' => 'Wahyu Adi Susilo'],

            // 10002260 - MUSTHOLIHUL HASAN (WC320, WC322, WC327)
            ['wc' => 'WC320', 'nik' => '10002260', 'nama' => 'MUSTHOLIHUL HASAN'],
            ['wc' => 'WC322', 'nik' => '10002260', 'nama' => 'MUSTHOLIHUL HASAN'],
            ['wc' => 'WC327', 'nik' => '10002260', 'nama' => 'MUSTHOLIHUL HASAN'],

            // 10001033 - Rangga aji pangestu (WC341)
            ['wc' => 'WC341', 'nik' => '10001033', 'nama' => 'Rangga aji pangestu'],

            // 10004416 - Muhammad Zamakhsyari (WC443, WC444, WC452, WC863)
            ['wc' => 'WC443', 'nik' => '10004416', 'nama' => 'Muhammad Zamakhsyari'],
            ['wc' => 'WC444', 'nik' => '10004416', 'nama' => 'Muhammad Zamakhsyari'],
            ['wc' => 'WC452', 'nik' => '10004416', 'nama' => 'Muhammad Zamakhsyari'],
            ['wc' => 'WC863', 'nik' => '10004416', 'nama' => 'Muhammad Zamakhsyari'],

            // 10000992 - SUGIYANTO (WC445 - WC450)
            ['wc' => 'WC445', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC446', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC447', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC448', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC449', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],
            ['wc' => 'WC450', 'nik' => '10000992', 'nama' => 'SUGIYANTO'],

            // 10000979 - Sandyk Nanda Nandika (WC591, WC592, WC593, WC594, WC596, WC620, WC631, WC641, WC642, WC659, WC660, WC661)
            ['wc' => 'WC591', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC592', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC593', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC594', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC596', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC620', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC631', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC641', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC642', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC659', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC660', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],
            ['wc' => 'WC661', 'nik' => '10000979', 'nama' => 'Sandyk Nanda Nandika'],

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
