<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersExtraSeeder extends Seeder
{
    public function run(): void
    {
        $pass = Hash::make('123456789');

        $rows = [
            [
                'name' => 'Arfina',
                'email' => 'arfina@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => [
                    'MACHINING - DELTA',
                    'EDGE BANDING - DELTA',
                ],
                'scope_arbpl' => null,
            ],
            [
                'name' => 'Maisa',
                'email' => 'maisa@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => [
                    'ASSY COMPONENT - CHARLY',
                    'ASSY UNIT - CHARLY',
                    'MA CASTING - BRAVO',
                    'MA MACHINING - BRAVO',
                    'MA ASSEMBLY - BRAVO',
                    'MA FINISHING',
                ],
                'scope_arbpl' => null,
            ],
            [
                'name' => 'Nilan',
                'email' => 'Nilan@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => [
                    "MATERIAL PREPARATION - ALPHA",
                    "MACHINING - ALPHA",
                    "ASSEMBLY - ALPHA",
                ],
                'scope_arbpl' => null,
            ],
        ];

        foreach ($rows as $data) {
            User::updateOrCreate(
                ['email' => strtolower(trim($data['email']))],
                $data
            );
        }
    }
}
