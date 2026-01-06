<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NikKorlap;

class NikKorlapSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'nik' => '10000471',
                'nama' => 'SIGIT WIDODO',
                'plant' => '2000',
                'wc_korlap' => ['WC169', 'WC170', 'WC404', 'WC406', 'WC416'],
            ],
            [
                'nik' => '10000424',
                'nama' => 'M.ZAINUDIN',
                'plant' => '2000',
                'wc_korlap' => ['WC145','WC146','WC148','WC149','WC151','WC152','WC154','WC158','WC162','WC163','WC164','WC166','WC168'],
            ],
            [
                'nik' => '10000432',
                'nama' => 'NURSALIM',
                'plant' => '2000',
                'wc_korlap' => ['WC146','WC149','WC152','WC154','WC156','WC159','WC160','WC161','WC163','WC165','WC168'],
            ],
            [
                'nik' => '10000074',
                'nama' => 'Ifan Wahyu Efendi',
                'plant' => '2000',
                'wc_korlap' => ['WC292','WC286','WC291','WC552','WC289','WC082','WC293'],
            ],
            [
                'nik' => '10000487',
                'nama' => 'Hariyono',
                'plant' => '2000',
                'wc_korlap' => ['WC094','WC091','WC096','WC092','WC097'],
            ],
            [
                'nik' => '10000046',
                'nama' => 'Beniyanto',
                'plant' => '2000',
                'wc_korlap' => ['WC085','WC086','WC783'],
            ],
            [
                'nik' => '10000429',
                'nama' => 'Beni Sunarko',
                'plant' => '2000',
                'wc_korlap' => ['WC101','WC102','WC098','WC099','WC477','WC474'],
            ],
            [
                'nik' => '10000505',
                'nama' => 'Purwanto.',
                'plant' => '2000',
                'wc_korlap' => ['WC500','WC493','WC494','WC495','WC496','WC497','WC411','WC409','WC408'],
            ],
            [
                'nik' => '10000437',
                'nama' => 'Suprianto',
                'plant' => '2000',
                'wc_korlap' => ['WC410','WC505','WC506','WC507','WC509','WC511','WC512','WC514','WC412','WC401'],
            ],
        ];

        foreach ($rows as $row) {
            // rapikan WC (unique + reindex)
            $row['wc_korlap'] = array_values(array_unique($row['wc_korlap']));

            NikKorlap::updateOrCreate(
                // ✅ key unik (nik + plant)
                ['nik' => $row['nik'], 'plant' => $row['plant']],
                // ✅ fields yang diupdate
                ['nama' => $row['nama'], 'wc_korlap' => $row['wc_korlap']]
            );
        }
    }
}
