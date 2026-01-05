<?php
/*

REMEMBER
scope_devisi = ["PEMBAHANAN CUTTING - CHARLY","MACHINING - DELTA"]

scope_arbpl = ["WC232"] Summary of namespace Database\Seeders
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersWithScopeSeeder extends Seeder
{
    public function run(): void
    {
        $pass = Hash::make('123456789');

        $rows = [
            // Guest lihat semua
            [
                'name' => 'Guest',
                'email' => 'guest@gmail.com',
                'password' => $pass,
                'scope_all' => true,
                'scope_devisi' => null,
                'scope_arbpl' => null,
            ],

            // Cutting Charly
            [
                'name' => 'Cutting Charly',
                'email' => 'cuttingcharly@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['PEMBAHANAN CUTTING - CHARLY'],
                'scope_arbpl' => null,
            ],

            // Machining Delta
            [
                'name' => 'Machining Delta',
                'email' => 'machiningdelta@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MACHINING - DELTA'],
                'scope_arbpl' => null,
            ],

            // Edge Delta
            [
                'name' => 'Edge Delta',
                'email' => 'edgedelta@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['EDGE BANDING - DELTA'],
                'scope_arbpl' => null,
            ],

            // Assy Delta (email kamu ketik "Assydellta", aku normalisasi jadi assydellta@gmail.com)
            [
                'name' => 'Assy Delta',
                'email' => 'assydellta@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['ASSY BODY - DELTA'],
                'scope_arbpl' => null,
            ],

            // Painting Delta
            [
                'name' => 'Painting Delta',
                'email' => 'paintingdelta@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['PAINTING - DELTA'],
                'scope_arbpl' => null,
            ],

            //PACKING - DELTA
            [
                'name' => 'Packing Delta',
                'email' => 'packinfdelta@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['PAINTING - DELTA'],
                'scope_arbpl' => null,
            ],

            // Casting Bravo
            [
                'name' => 'Casting Bravo',
                'email' => 'castingbravo@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MA CASTING - BRAVO'],
                'scope_arbpl' => null,
            ],

            // Asseembly Bravo
            [
                'name' => 'Asseembly Bravo',
                'email' => 'asseemblybravo@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MA ASSEMBLY - BRAVO'],
                'scope_arbpl' => null,
            ],

            // MA FINISHING
            [
                'name' => 'Finishing',
                'email' => 'finishing@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MA FINISHING'],
                'scope_arbpl' => null,
            ],

            // MA PACKING - BRAVO
            [
                'name' => 'Packing Bravo',
                'email' => 'packingbravo@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MA PACKING - BRAVO'],
                'scope_arbpl' => null,
            ],

            // SAMPLE - CHARLY
            [
                'name' => 'Sample Charly',
                'email' => 'samplecharly@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['SAMPLE - CHARLY'],
                'scope_arbpl' => null,
            ],

            // MATERIAL PREPARATION - ALPHA
            [
                'name' => 'Material Preparation Alpha',
                'email' => 'maprepalpha@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MATERIAL PREPARATION - ALPHA'],
                'scope_arbpl' => null,
            ],

            // MACHINING - ALPHA
            [
                'name' => 'Machining Alpha',
                'email' => 'machiningalpha@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MACHINING - ALPHA'],
                'scope_arbpl' => null,
            ],

            // ASSEMBLY - ALPHA
            [
                'name' => 'Assembly Alpha',
                'email' => 'assemblyalpha@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['ASSEMBLY - ALPHA'],
                'scope_arbpl' => null,
            ],

            // PAINTING - BRAVO
            [
                'name' => 'Painting Bravo',
                'email' => 'paintingbravo@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['PAINTING - BRAVO'],
                'scope_arbpl' => null,
            ],

            // ASSY BODY - DELTA (Assy Body Delta)
            [
                'name' => 'Assy Body Delta',
                'email' => 'assybodydelta@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['ASSY BODY - DELTA'],
                'scope_arbpl' => null,
            ],

            // ASSEMBLY - DELTA
            [
                'name' => 'Assembly Delta',
                'email' => 'assemblydelta@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['ASSEMBLY - DELTA'],
                'scope_arbpl' => null,
            ],

            // MA MACHINING - BRAVO
            [
                'name' => 'Ma Machining Bravo',
                'email' => 'machiningbravo@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MA MACHINING - BRAVO'],
                'scope_arbpl' => null,
            ],

            // MA ASSEMBLY - BRAVO
            [
                'name' => 'Ma Assembly Bravo',
                'email' => 'maassemblybravo@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['MA ASSEMBLY - BRAVO'],
                'scope_arbpl' => null,
            ],

            // ASSY COMPONENT - CHARLY
            [
                'name' => 'Assy Component Charly',
                'email' => 'assycomponentcharly@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['ASSY COMPONENT - CHARLY'],
                'scope_arbpl' => null,
            ],

            // ASSY UNIT - CHARLY
            [
                'name' => 'Assy Unit Charly',
                'email' => 'assyunitcharly@gmail.com',
                'password' => $pass,
                'scope_all' => false,
                'scope_devisi' => ['ASSY UNIT - CHARLY'],
                'scope_arbpl' => null,
            ],

            [
                'name' => 'Adi',
                'email' => 'adi@gmail.com',
                'password' => $pass,

                'scope_all' => false,

                'scope_devisi' => [
                    'Sawmill',
                    'Kiln Dry',
                    'Slicer',
                    'Wood Working Cutting',
                    'Wood Working Finger Joint',
                    'Wood Working Laminating',
                    'Moulding',
                    'Component Working',
                    'Production 1',
                    'Paneling',
                    'CNC Paneling',
                    'Casegood Drawer',
                    'Production 3',
                    'Upholestery',
                    'Production 5',
                    'Packaging Box',
                    'Production 2',
                    'FINISHING',
                    'CGF Painting',
                    'CGF Packing',
                    'Production 4',
                    'Research and Development',
                    'Ware House',
                    'Slicer 3',
                    'Drying Machinery',
                    'MP. Slicer',
                    'Chair',
                    'Painting',
                ],

                'scope_arbpl' => ['WC853']
            ],

        ];

        foreach ($rows as $data) {
            // UNIK by email (kalau ada sudah ada, update)
            User::updateOrCreate(
                ['email' => strtolower(trim($data['email']))],
                $data
            );
        }
    }
}
