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

            // ===== PAINTING (tambahan dari data atas) =====
            [
                'nik' => '10000719',
                'nama' => 'Fitrianto',
                'plant' => '2000',
                'wc_korlap' => ['WC580','WC581','WC582','WC583','WC584','WC687','WC804','WC805','WC854'],
            ],
            [
                'nik' => '10000587',
                'nama' => 'Moch. Darmawan Eko',
                'plant' => '2000',
                'wc_korlap' => ['WC688','WC689','WC690','WC691','WC584','WC807','WC808','WC809','WC855'],
            ],
            [
                'nik' => '10000544',
                'nama' => 'Moch. Choirudin',
                'plant' => '2000',
                'wc_korlap' => ['WC835','WC836','WC837','WC838','WC839','WC840','WC841','WC842','WC856'],
            ],
            [
                'nik' => '10000413',
                'nama' => 'Arif Budi Prasetya',
                'plant' => '2000',
                'wc_korlap' => ['WC844','WC845','WC584','WC847'],
            ],
            [
                'nik' => '10000410',
                'nama' => 'Dian Prasetyo',
                'plant' => '2000',
                'wc_korlap' => ['WC848','WC845','WC849','WC850','WC853','WC851'],
            ],
            [
                'nik' => '10000421',
                'nama' => 'Sunarto',
                'plant' => '2000',
                'wc_korlap' => ['WC848','WC849','WC850','WC851'],
            ],
            [
                'nik' => '10000388',
                'nama' => 'Abdul Kholiq Idris',
                'plant' => '2000',
                'wc_korlap' => ['WC844','WC845','WC846','WC847'],
            ],
            [
                'nik' => '10000409',
                'nama' => 'Nanang Soiman',
                'plant' => '2000',
                'wc_korlap' => ['WC835','WC836','WC837','WC838'],
            ],
        ];

        foreach ($rows as $row) {
            // rapikan nik & nama
            $row['nik']  = trim((string) $row['nik']);
            $row['nama'] = trim((string) $row['nama']);

            // rapikan WC: trim, uppercase, unique, reindex
            $row['wc_korlap'] = array_values(array_unique(array_map(function ($wc) {
                $wc = strtoupper(trim((string) $wc));
                return $wc;
            }, $row['wc_korlap'])));

            NikKorlap::updateOrCreate(
                ['nik' => $row['nik'], 'plant' => $row['plant']],
                ['nama' => $row['nama'], 'wc_korlap' => $row['wc_korlap']]
            );
        }
    }
}
