<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersFromWcPersonSeederBatch2 extends Seeder
{
    public function run(): void
    {
        $now = now();
        $passwordHash = Hash::make('123456789');

        $users = [
            [
                'name' => 'Philip',
                'email' => 'kmi-u085@gmail.com',
                'scope_arbpl' => [
                    'WC083','WC085','WC086','WC098','WC099','WC100','WC101','WC102','WC103',
                    'WC401','WC408','WC409','WC410','WC411','WC412',
                    'WC474','WC475','WC476','WC477',
                    'WC493','WC494','WC495','WC496','WC497','WC498','WC499','WC500','WC501','WC502','WC503','WC504','WC505','WC506','WC507','WC508','WC509','WC510','WC511','WC512','WC513','WC514','WC515','WC516',
                    'WC763','WC764','WC765','WC766','WC767','WC768','WC774','WC783',
                    'WC871','WC872','WC873','WC874','WC875',
                ],
            ],
            [
                'name' => 'Anggi',
                'email' => 'kmi-u170@gmail.com',
                'scope_arbpl' => ['WC789','WC790','WC796'],
            ],
            [
                'name' => 'Ike',
                'email' => 'kmi-u103@gmail.com',
                'scope_arbpl' => ['WC104','WC105','WC686','WC857'],
            ],
            [
                'name' => 'Ella',
                'email' => 'kmi-u090@gmail.com',
                'scope_arbpl' => [
                    'WC112','WC113','WC114','WC115','WC116','WC117','WC118','WC119','WC120','WC121','WC122',
                    'WC123','WC124','WC125','WC126','WC127','WC128',
                    'WC777',
                ],
            ],
            [
                'name' => 'Elvira',
                'email' => 'kmi-u098@gmail.com',
                'scope_arbpl' => [
                    'WC129','WC130','WC131',
                    'WC810','WC811','WC812','WC813','WC814','WC815','WC816','WC817',
                ],
            ],
            [
                'name' => 'Diva',
                'email' => 'kmi-u091@gmail.com',
                'scope_arbpl' => [
                    'WC483','WC484','WC485','WC486','WC491','WC759',
                    'WC791','WC792','WC793','WC794',
                    'WC858','WC859','WC860','WC861','WC868',
                ],
            ],
            [
                'name' => 'Dyah',
                'email' => 'kmi-u044@gmail.com',
                'scope_arbpl' => ['WC484','WC486','WC487','WC488','WC489','WC490','WC782'],
            ],
            [
                'name' => 'Risma',
                'email' => 'kmi-u114@gmail.com',
                'scope_arbpl' => ['WC137','WC138','WC139','WC140','WC141','WC142','WC143','WC144','WC776','WC778','WC832'],
            ],
            [
                'name' => 'Winda',
                'email' => 'kmi-u088@gmail.com',
                'scope_arbpl' => ['WC169','WC170','WC404','WC405','WC406','WC407','WC416'],
            ],
            [
                'name' => 'Silvi',
                'email' => 'kmi-u096@gmail.com',
                'scope_arbpl' => [
                    'WC174','WC175','WC176','WC177','WC178','WC179','WC180',
                    'WC517','WC518','WC519','WC520','WC521','WC522','WC523',
                    'WC580','WC581','WC582','WC583','WC584',
                    'WC687','WC688','WC689','WC690','WC691',
                    'WC721','WC722','WC723','WC724','WC725','WC726','WC727','WC728','WC729','WC730','WC731','WC732','WC733','WC734','WC735','WC736',
                    'WC760','WC761','WC762',
                    'WC769','WC770','WC771','WC772','WC773','WC775',
                    'WC804','WC805','WC806','WC807','WC808','WC809',
                    'WC834','WC835','WC836','WC837','WC838','WC839','WC840','WC841','WC842',
                    'WC843','WC844','WC845','WC846','WC847','WC848','WC849','WC850','WC851',
                    'WC853','WC854','WC855','WC856',
                    'WC876','WC877','WC878',
                ],
            ],
            [
                'name' => 'Dwi Agustina',
                'email' => 'kmi-ct08@gmail.com',
                'scope_arbpl' => ['WC784','WC785','WC786','WC787','WC797','WC798','WC799','WC800','WC801','WC802','WC803','WC869','WC870'],
            ],
            [
                'name' => 'Risna',
                'email' => 'kmi-u040@gmail.com',
                'scope_arbpl' => [
                    'WC032',
                    'WC181','WC182','WC183','WC184','WC185','WC186','WC187','WC188','WC189','WC190','WC191','WC192','WC193','WC194',
                    'WC195','WC196','WC197','WC198',
                    'WC413','WC414','WC415',
                ],
            ],
            [
                'name' => 'Okta',
                'email' => 'kmi-u100@gmail.com',
                'scope_arbpl' => ['WC199','WC200','WC201','WC526','WC527','WC780','WC781'],
            ],
            [
                'name' => 'Olip',
                'email' => 'kmi-u063@gmail.com',
                'scope_arbpl' => ['WC022'],
            ],
        ];

        foreach ($users as $u) {
            $existing = DB::table('users')->where('email', $u['email'])->first();

            $incomingCodes = array_values(array_unique($u['scope_arbpl']));

            if ($existing) {
                $existingCodes = [];
                if (!empty($existing->scope_arbpl)) {
                    $decoded = json_decode($existing->scope_arbpl, true);
                    if (is_array($decoded)) $existingCodes = $decoded;
                }

                $mergedCodes = array_values(array_unique(array_merge($existingCodes, $incomingCodes)));

                DB::table('users')->where('email', $u['email'])->update([
                    'name' => $u['name'],
                    'password' => $passwordHash,
                    'scope_all' => 0,
                    'scope_devisi' => null,
                    'scope_arbpl' => json_encode($mergedCodes),
                    'email_verified_at' => null,
                    'remember_token' => null,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('users')->insert([
                    'name' => $u['name'],
                    'email' => $u['email'],
                    'password' => $passwordHash,
                    'scope_all' => 0,
                    'scope_devisi' => null,
                    'scope_arbpl' => json_encode($incomingCodes),
                    'email_verified_at' => null,
                    'remember_token' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
