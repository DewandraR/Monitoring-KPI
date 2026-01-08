<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class SendWeeklyOwnerReport extends Command
{
    protected $signature = 'report:send-weekly-owner';
    protected $description = 'Mengirim email report mingguan Korlap ke Owner sesuai konfigurasi Plant';

    // KONFIGURASI TEKNIS (JANGAN DIUBAH)
    protected string $qmTable = 'yppr058_data';   
    protected string $wiTable = 'daily_time_wi';  
    protected float $qmDivisor = 1.0;             

    /**
     * =========================================================================
     * AREA KONFIGURASI PENERIMA (SANGAT MUDAH)
     * =========================================================================
     */
    protected function getRecipientConfiguration()
    {
        // 1. DEFINISIKAN GRUP PLANT DULU BIAR GAMPANG
        // -----------------------------------------------------
        $semua_plant      = ['1000', '1001', '2000', '3000'];
        $hanya_plant_1_2  = ['1000', '1001', '2000'];
        $hanya_plant_3    = ['3000'];

        // 2. TENTUKAN SIAPA MENERIMA APA
        // -----------------------------------------------------
        return [
            // --- LEVEL BOSS (Menerima Semua) ---
            'andraku76@gmail.com' => $semua_plant,

            // --- STAFF LAIN (Hanya Plant 3000) ---
            'mankyau76@gmail.com' => $hanya_plant_3,
        ];
    }

    /**
     * =========================================================================
     * LOGIC UTAMA (JANGAN DIUBAH KECUALI PAHAM)
     * =========================================================================
     */
    public function handle()
    {
        $this->info('Memulai proses pengiriman laporan mingguan...');

        // 1. Tentukan Tanggal
        $now = Carbon::now();
        $startDate = $now->copy()->subWeek()->startOfWeek(); 
        $endDate   = $now->copy()->subWeek()->endOfWeek();

        $startStr = $startDate->format('Y-m-d');
        $endStr   = $endDate->format('Y-m-d');
        
        $dateIso = [$startStr, $endStr];
        $dateYmd = [$startDate->format('Ymd'), $endDate->format('Ymd')];

        $this->info("Periode Data: {$startStr} s.d {$endStr}");

        // 2. Load Konfigurasi
        $emailConfig = $this->getRecipientConfiguration();

        // Cari tahu plant mana saja yang PERLU digenerate (gabungan unik dari semua orang)
        $allPlantsNeeded = [];
        foreach ($emailConfig as $plants) {
            $allPlantsNeeded = array_merge($allPlantsNeeded, $plants);
        }
        $allPlantsNeeded = array_unique($allPlantsNeeded); // Hapus duplikat

        // 3. Generate PDF untuk setiap Plant (Simpan di Memory)
        $generatedPdfs = []; // Format: ['1000' => ['content' => binary, 'name' => string], ...]

        foreach ($allPlantsNeeded as $plantCode) {
            $this->info("Generating PDF untuk Plant: {$plantCode}...");

            $dataForPdf = $this->getKorlapDataFullLogic($plantCode, $dateIso, $dateYmd);

            if ($dataForPdf->isEmpty()) {
                $this->warn(">> Tidak ada data Korlap untuk Plant {$plantCode}. Skip generation.");
                continue;
            }

            try {
                $pdf = Pdf::loadView('pdf.wi-korlap', [ 
                    'data'       => $dataForPdf,
                    'plant'      => $plantCode,
                    'rangeStart' => $startStr,
                    'rangeEnd'   => $endStr,
                    'wiMode'     => 'all'
                ]);
                
                $pdf->setPaper('a4', 'landscape');
                
                $generatedPdfs[$plantCode] = [
                    'content' => $pdf->output(),
                    'name'    => "Laporan_Korlap_Plant_{$plantCode}_{$startStr}_sd_{$endStr}.pdf",
                    'mime'    => 'application/pdf',
                ];

                $this->info(">> PDF Plant {$plantCode} OK.");

            } catch (\Exception $e) {
                $this->error(">> Gagal generate PDF Plant {$plantCode}: " . $e->getMessage());
            }
        }

        // 4. Kirim Email ke Setiap Penerima sesuai Hak Aksesnya
        foreach ($emailConfig as $email => $allowedPlants) {
            
            // Kumpulkan attachment khusus untuk user ini
            $userAttachments = [];
            
            // Cek setiap plant yang boleh dia akses
            foreach ($allowedPlants as $plantCode) {
                // Jika PDF plant tersebut berhasil digenerate tadi, masukkan ke attachment
                if (isset($generatedPdfs[$plantCode])) {
                    $userAttachments[] = $generatedPdfs[$plantCode];
                }
            }

            // Jika tidak ada attachment sama sekali untuk user ini (misal datanya kosong semua), skip kirim
            if (empty($userAttachments)) {
                $this->warn("User {$email} tidak mendapatkan attachment apapun (Data kosong). Email skip.");
                continue;
            }

            $countFiles = count($userAttachments);
            $this->info("Mengirim email ke: {$email} ({$countFiles} file)...");

            try {
                Mail::send([], [], function ($message) use ($email, $userAttachments, $startStr, $endStr) {
                    $message->to($email)
                            ->subject("Laporan Mingguan Korlap ({$startStr} s.d {$endStr})")
                            ->html("
                                <h3 style='color:#047857;'>Laporan Mingguan Tim Korlap</h3>
                                <p>Halo,</p>
                                <p>Berikut terlampir laporan kinerja Korlap untuk area Anda.</p>
                                <p>Periode Data: <b>{$startStr}</b> s.d <b>{$endStr}</b></p>
                                <br>
                                <p><i>Email ini dikirim otomatis oleh sistem Monitoring KPI.</i></p>
                            ");

                    foreach ($userAttachments as $file) {
                        $message->attachData($file['content'], $file['name'], ['mime' => $file['mime']]);
                    }
                });

                $this->info(">> SUKSES kirim ke {$email}");

            } catch (\Exception $e) {
                $this->error(">> GAGAL kirim ke {$email}: " . $e->getMessage());
            }
        }

        $this->info('Selesai Semua.');
    }

    /**
     * =========================================================================
     * CORE LOGIC DATABASE (SAMA SEPERTI SEBELUMNYA)
     * =========================================================================
     */
    private function getKorlapDataFullLogic(string $plant, array $dateIso, array $dateYmd)
    {
        $korlaps = DB::table('nik_korlap')
            ->whereRaw('TRIM(plant) = ?', [$plant])
            ->select('nik', 'nama', 'wc_korlap')
            ->orderBy('nama')
            ->get();

        if ($korlaps->isEmpty()) return collect([]);

        $allWcs = [];
        foreach ($korlaps as $k) {
            $wcs = json_decode($k->wc_korlap ?? '[]', true);
            if (is_array($wcs)) $allWcs = array_merge($allWcs, $wcs);
        }
        $allWcs = array_unique($allWcs);

        if (empty($allWcs)) return collect([]);

        $joined = $this->joinedDayQuery($plant, $dateIso, $dateYmd); 
        $joined->whereIn('qm.wc', $allWcs);

        $memberRows = DB::query()
            ->fromSub($joined, 'd')
            ->selectRaw("
                nik, MAX(nama) as nama, MAX(wc) as wc, MAX(devisi) as devisi,
                COALESCE(SUM(COALESCE(time_wi,0)),0) as time_wi_sum,
                COALESCE(SUM(COALESCE(time_conf,0)),0) as time_conf_sum,
                COALESCE(SUM(COALESCE(time_qm,0)),0) as time_qm_sum,
                CASE WHEN SUM(COALESCE(time_wi,0)) = 0 THEN 0 ELSE (SUM(COALESCE(time_qm,0)) / SUM(COALESCE(time_wi,0))) * 100 END as kpi_quality_pct,
                CASE WHEN SUM(COALESCE(time_wi,0)) = 0 THEN 0 ELSE (SUM(COALESCE(time_conf,0)) / SUM(COALESCE(time_wi,0))) * 100 END as kpi_qty_pct
            ")
            ->groupBy('nik')
            ->get();

        $result = collect();

        foreach ($korlaps as $k) {
            $myWcs = json_decode($k->wc_korlap ?? '[]', true) ?: [];
            $myMembers = $memberRows->filter(fn($m) => in_array($m->wc, $myWcs));

            if ($myMembers->isNotEmpty()) {
                $totalWi   = $myMembers->sum('time_wi_sum');
                $totalConf = $myMembers->sum('time_conf_sum');
                $totalQm   = $myMembers->sum('time_qm_sum');
                $kpiQualityKorlap = ($totalWi == 0) ? 0 : ($totalQm / $totalWi) * 100;
                $kpiQtyKorlap     = ($totalWi == 0) ? 0 : ($totalConf / $totalWi) * 100;
                
                sort($myWcs);
                $wcString = implode(', ', $myWcs);

                $result->push([
                    'korlap_nik'  => $k->nik,
                    'korlap_nama' => $k->nama,
                    'summary' => [
                        'wc_string'       => $wcString,
                        'count_nik'       => $myMembers->count(),
                        'total_wi'        => $totalWi,
                        'total_conf'      => $totalConf,
                        'total_qm'        => $totalQm,
                        'kpi_quality_pct' => $kpiQualityKorlap,
                        'kpi_qty_pct'     => $kpiQtyKorlap,
                    ],
                    'members' => $myMembers->sortBy('nama')->values()
                ]);
            }
        }
        return $result;
    }

    protected function joinedDayQuery(string $plant, array $dateIso, array $dateYmd)
    {
        $qmDay = $this->qmDaySub($plant, $dateYmd);
        $wiDay = $this->wiDaySub($plant, $dateIso);

        return DB::query()
            ->fromSub($qmDay, 'qm')
            ->leftJoinSub($wiDay, 'wi', function ($join) {
                $join->on('wi.nik', '=', 'qm.nik')
                     ->on(DB::raw("DATE_FORMAT(wi.tanggal, '%Y%m%d')"), '=', 'qm.begda');
            })
            ->selectRaw("qm.nik, qm.begda, qm.nama, qm.wc, qm.devisi, (qm.mintu_sum / {$this->qmDivisor}) as time_qm, (qm.mint2_sum / {$this->qmDivisor}) as time_conf, wi.time_wi as time_wi");
    }

    protected function qmDaySub(string $plant, array $dateYmd)
    {
        [$startYmd, $endYmd] = $dateYmd;
        return DB::table($this->qmTable)
            ->whereBetween('begda', [$startYmd, $endYmd])
            ->whereRaw("UPPER(TRIM(role)) = 'INDUK'")
            ->whereRaw("CASE WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001' WHEN LEFT(TRIM(werks),1)='1' THEN '1000' ELSE CONCAT(LEFT(TRIM(werks),1),'000') END = ?", [$plant])
            ->selectRaw("pernr as nik, begda, MAX(cname) as nama, MAX(arbpl) as wc, MAX(NULLIF(TRIM(devisi),'')) as devisi, COALESCE(SUM(mintu),0) as mintu_sum, COALESCE(SUM(mint2),0) as mint2_sum")
            ->groupBy('pernr', 'begda');
    }

    protected function wiDaySub(string $plant, array $dateIso)
    {
        [$startIso, $endIso] = $dateIso;
        return DB::table($this->wiTable)
            ->whereBetween('tanggal', [$startIso, $endIso])
            ->whereNotNull('kode_laravel')
            ->whereRaw("TRIM(kode_laravel) <> ''")
            ->whereRaw("TRIM(kode_laravel) REGEXP '^[0-9]{4}'")
            ->whereRaw("CASE WHEN LEFT(TRIM(kode_laravel),4) IN ('1001','1002','1003','1015') THEN '1001' WHEN LEFT(TRIM(kode_laravel),1) = '1' THEN '1000' ELSE CONCAT(LEFT(TRIM(kode_laravel),1),'000') END = ?", [$plant])
            ->selectRaw("nik, tanggal, COALESCE(SUM(total_time_wi),0) as time_wi")
            ->groupBy('nik', 'tanggal');
    }
}