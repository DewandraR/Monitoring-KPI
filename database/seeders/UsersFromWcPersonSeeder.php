<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersFromWcPersonSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Semua user passwordnya sama (plaintext: 123456789)
        $passwordHash = Hash::make('123456789');

        $users = [
            [
                'name' => 'Hendra',
                'email' => 'kmi-u169@gmail.com',
                'scope_arbpl' => ['WC001','WC002','WC006','WC007'],
            ],
            [
                'name' => 'Luky',
                'email' => 'kmi-u059@gmail.com',
                'scope_arbpl' => ['WC009','WC010','WC011','WC012','WC013','WC014','WC015','WC016'],
            ],
            [
                'name' => 'Anggi',
                'email' => 'kmi-u170@gmail.com',
                'scope_arbpl' => ['WC018','WC019','WC021'],
            ],
            [
                'name' => 'Yeni',
                'email' => 'kmi-u081@gmail.com',
                'scope_arbpl' => ['WC024','WC025','WC587','WC026','WC027','WC028','WC029','WC030','WC031','WC819','WC820','WC821','WC822'],
            ],
            [
                'name' => 'Yuni',
                'email' => 'kmi-u086@gmail.com',
                'scope_arbpl' => ['WC033','WC034','WC035','WC852','WC036','WC037','WC038','WC779','WC048'],
            ],
            [
                'name' => 'Susilo',
                'email' => 'kmi-u165@gmail.com',
                'scope_arbpl' => ['WC040','WC041','WC042','WC043','WC044','WC045','WC046','WC047','WC051','WC052'],
            ],
            [
                'name' => 'Khusnul',
                'email' => 'kmi-u083@gmail.com',
                'scope_arbpl' => ['WC049','WC050'],
            ],
            [
                'name' => 'Wiwin',
                'email' => 'kmi-u097@gmail.com',
                'scope_arbpl' => ['WC053','WC054','WC055','WC056','WC057','WC058','WC059','WC585'],
            ],
            [
                'name' => 'Rica',
                'email' => 'kmi-u110@gmail.com',
                'scope_arbpl' => ['WC060','WC061','WC062','WC063','WC064','WC065','WC066','WC067','WC068','WC070','WC071'],
            ],
            [
                'name' => 'Badar',
                'email' => 'kmi-u092@gmail.com',
                'scope_arbpl' => ['WC080','WC737','WC072','WC106','WC831','WC109','WC110','WC111','WC478','WC073','WC075','WC079','WC569','WC862','WC818','WC089','WC088'],
            ],
        ];

        foreach ($users as $u) {
            // upsert by email biar aman dijalankan berkali-kali
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => $passwordHash,
                    'scope_all' => 0,
                    'scope_devisi' => null,
                    'scope_arbpl' => json_encode($u['scope_arbpl']),
                    'email_verified_at' => null,
                    'remember_token' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
