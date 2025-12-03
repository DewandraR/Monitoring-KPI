<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    public function getConfirmedNik(Request $request): JsonResponse
    {
        // Ambil input kode_laravel dari body
        // Boleh:
        //   "kode_laravel": "1011"
        //   "kode_laravel": "1011,1012"
        //   "kode_laravel": ["1011","1012"]
        $kodeInput = $request->input('kode_laravel');

        if ($kodeInput === null) {
            // Kalau tidak dikirim, kembalikan semua role INDUK (behaviour lama)
            $query = DB::table('wc_person_data')
                ->select('arbpl', 'pernr', 'devisi', 'short')
                ->where('role', 'INDUK');

        } else {
            // Normalisasi jadi array kode
            if (is_array($kodeInput)) {
                $codes = $kodeInput;
            } else {
                // string: bisa "1011" atau "1011,1012"
                $codes = preg_split('/\s*,\s*/', (string) $kodeInput);
            }

            // Bersihkan kosong & spasi
            $codes = array_values(array_filter(array_map('trim', $codes)));

            if (empty($codes)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'kode_laravel tidak boleh kosong',
                ], 422);
            }

            // Query dengan filter role INDUK + kode_laravel
            $query = DB::table('wc_person_data')
                ->select('arbpl', 'pernr', 'devisi', 'short')
                ->where('role', 'INDUK')
                ->where(function ($q) use ($codes) {
                    foreach ($codes as $code) {
                        // kolom kode_laravel di DB berisi CSV, mis: "1011,1012"
                        $q->orWhereRaw(
                            'FIND_IN_SET(?, REPLACE(kode_laravel, " ", ""))',
                            [$code]
                        );
                    }
                });
        }

        $data = $query->get();

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }
}

