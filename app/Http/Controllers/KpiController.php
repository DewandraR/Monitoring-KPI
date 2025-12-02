<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

class KpiController extends Controller
{
    public function getConfirmedNik()
    {
        // Sesuaikan nama table Anda di sini
        $data = DB::table('wc_person_data')
            ->select('arbpl', 'pernr', 'devisi',  'short')
            ->where('role', 'INDUK')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }
}
