<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Livewire\WiDailyReport; // ✅ TAMBAH

class SendWeeklyOwnerReport extends Command
{
    protected $signature = 'report:send-weekly-owner';
    protected $description = 'Mengirim 1 email berisi file PDF report mingguan Korlap ke Owner';

    // KONFIGURASI TEKNIS
    protected string $qmTable = 'yppr058_data';
    protected string $wiTable = 'daily_time_wi';
    protected float $qmDivisor = 1.0;

    // ✅ samakan dengan Blade/Livewire
    protected string $dataVisibleFrom = '2025-12-01';

    /**
     * =========================================================================
     * AREA KONFIGURASI PENERIMA
     * =========================================================================
     */
    protected function getRecipientConfiguration()
    {
        $semua_plant      = ['1000', '1001', '2000', '3000'];
        $hanya_plant_3    = ['3000'];
        $hanya_plant_1_2  = ['1000', '1001', '2000'];

        return [
            // 'kmi3.61.smg@gmail.com' => $semua_plant,
            'andraku76@gmail.com' => $hanya_plant_3,
			// 'finc.smg@pawindo.com' => $hanya_plant_3,
			// 'kmi356smg@gmail.com' => $hanya_plant_3,
			// 'adm.mkt5.smg@gmail.com' => $hanya_plant_3,
			// 'lily.smg@pawindo.com' => $hanya_plant_3,
			// 'kmi3.60.smg@gmail.com' => $hanya_plant_3,
			// 'kmi3.31.smg@gmail.com' => $hanya_plant_3,
			// 'kmi3.16.smg@gmail.com' => $hanya_plant_3,
			// 'kmi3.29.smg@gmail.com' => $hanya_plant_3,
			// 'kmi3.58.smg@gmail.com' => $hanya_plant_3,
			// 'kmi3.57.smg@gmail.com' => $hanya_plant_3,
			// 'kmi3.2.smg@gmail.com'  => $hanya_plant_3,
			// 'kmi3.1.smg@gmail.com'  => $hanya_plant_3,
        ];
    }

    public function handle()
    {
        $this->info('Memulai proses pengiriman laporan mingguan...');

        // 1. Tentukan Tanggal
        $now = Carbon::now();
        $startDate = $now->copy()->subWeek()->startOfWeek();
        $endDate   = $now->copy()->subWeek()->endOfWeek();


        $startStr = $startDate->format('Y-m-d');
        $endStr   = $endDate->format('Y-m-d');

        $startDisplay = $startDate->isoFormat('D MMMM Y');
        $endDisplay   = $endDate->isoFormat('D MMMM Y');

        $dateIso = [$startStr, $endStr];
        $dateYmd = [$startDate->format('Ymd'), $endDate->format('Ymd')];

        $this->info("Periode Data: {$startStr} s.d {$endStr}");

        // 2. Load Konfigurasi
        $emailConfig = $this->getRecipientConfiguration();

        $allPlantsNeeded = [];
        foreach ($emailConfig as $plants) {
            $allPlantsNeeded = array_merge($allPlantsNeeded, $plants);
        }
        $allPlantsNeeded = array_unique($allPlantsNeeded);

        // 3. Generate PDF (Simpan di Memory)
        $generatedPdfs = [];

        foreach ($allPlantsNeeded as $plantCode) {
            $this->info("Generating PDF untuk Plant: {$plantCode}...");

            $dataForPdf = $this->getKorlapDataFullLogic($plantCode, $dateIso, $dateYmd);

            if ($dataForPdf->isEmpty()) {
                $this->warn(">> Data kosong untuk Plant {$plantCode}. Skip.");
                continue;
            }

            // ✅ INJECT REMARK (TOTAL + SEMUA TAG + REMARK PER MEMBER)
            try {
                $dataForPdf = $this->injectRemarksIntoKorlapData(
                    $plantCode,
                    $dataForPdf,
                    Carbon::parse($startStr)->startOfDay(),
                    Carbon::parse($endStr)->startOfDay()
                );
                $this->info(">> Remark Plant {$plantCode} OK.");
            } catch (\Throwable $e) {
                $this->warn(">> Remark Plant {$plantCode} gagal diinject (PDF tetap dibuat): " . $e->getMessage());
            }

            try {
                $pdf = Pdf::loadView('pdf.wi-korlap', [
                    'data'       => $dataForPdf,
                    'plant'      => $plantCode,
                    'rangeStart' => $startStr,
                    'rangeEnd'   => $endStr,
                    'wiMode'     => 'all',
                ])
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'isPhpEnabled' => true,           // ✅ INI KUNCI NOMOR HALAMAN
                    'isHtml5ParserEnabled' => true,   // opsional (lebih stabil parsing)
                    // 'isRemoteEnabled' => true,      // kalau pakai gambar/font remote
                ]);

            $generatedPdfs[$plantCode] = [
                'content' => $pdf->output(),
                'name'    => "Laporan_KPI_Korlap_Plant_{$plantCode}_{$startStr}.pdf",
                'mime'    => 'application/pdf',
            ];

                $pdf->setPaper('a4', 'landscape');

                $generatedPdfs[$plantCode] = [
                    'content' => $pdf->output(),
                    'name'    => "Laporan_KPI_Korlap_Plant_{$plantCode}_{$startStr}.pdf",
                    'mime'    => 'application/pdf',
                ];

                $this->info(">> PDF Plant {$plantCode} OK.");
            } catch (\Exception $e) {
                $this->error(">> Gagal generate PDF Plant {$plantCode}: " . $e->getMessage());
            }
        }

        // 4. Kirim Email
        foreach ($emailConfig as $email => $allowedPlants) {
            $userAttachments = [];
            foreach ($allowedPlants as $plantCode) {
                if (isset($generatedPdfs[$plantCode])) {
                    $userAttachments[] = $generatedPdfs[$plantCode];
                }
            }

            if (empty($userAttachments)) {
                $this->warn("User {$email} tidak mendapatkan data (Kosong). Email skip.");
                continue;
            }

            $countFiles = count($userAttachments);
            $this->info("Mengirim email ke: {$email} ({$countFiles} file)...");

            try {
                $emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #374151; line-height: 1.6; margin: 0; padding: 0; font-size: 14px; }
        .container { max-width: 680px; margin: 0 auto; padding: 30px; background-color: #ffffff; }
        h3 { color: #111827; margin-bottom: 20px; font-size: 18px; font-weight: 700; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; }
        p { margin-bottom: 15px; text-align: justify; }
        .info-box { background-color: #f9fafb; border-left: 4px solid #059669; padding: 15px; margin: 20px 0; font-size: 13px; color: #4b5563; }
        .footer {
            background-color: #f3f4f6;
            padding: 20px;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
            border-radius: 6px;
            margin-top: 40px;
            line-height: 1.5;
        }
        .footer strong { color: #374151; }
        .confidential { font-style: italic; color: #9ca3af; margin-top: 10px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h3>Laporan Evaluasi Kinerja Korlap Mingguan</h3>

        <p>Yth. Bapak/Ibu,</p>

        <p>Melalui email ini, kami sampaikan laporan rekapitulasi <b>Key Performance Indicator (KPI) Tim Korlap</b> untuk periode:</p>

        <p style="font-size: 16px; font-weight: bold; text-align: center; color: #1f2937; margin: 20px 0;">
            {$startDisplay} &mdash; {$endDisplay}
        </p>

        <p>Dokumen terlampir memuat rincian data kinerja operasional lapangan yang mencakup:</p>
        <ul style="margin-bottom: 20px; color: #4b5563;">
            <li>Akumulasi waktu pengerjaan instruksi kerja (<b>Total Menit WI</b>).</li>
            <li>Realisasi konfirmasi produksi (<b>Total Menit Conf</b>).</li>
            <li>Parameter kualitas pengerjaan (<b>Quality Management/QM</b>).</li>
            <li>Persentase pencapaian KPI per masing-masing Koordinator Lapangan.</li>
            <li><b>Remark / Issue</b> (sudah termasuk dalam PDF).</li>
        </ul>

        <div class="info-box">
            <strong>Catatan:</strong><br>
            Data ini disajikan sebagai instrumen monitoring produktivitas dan dasar evaluasi kinerja mingguan di masing-masing Plant.
        </div>

        <p>Demikian disampaikan, atas perhatian dan arahannya kami ucapkan terima kasih.</p>

        <p>Hormat Kami,<br>
        <strong style="color: #111827;">Tim ERP & IT - PT. Kayu Mebel Indonesia</strong></p>

        <div class="footer">
            <strong>&copy; 2026 PT. Kayu Mebel Indonesia.</strong><br>
            Email ini dibuat secara otomatis oleh sistem (Auto-Generated).<br>
            Mohon untuk tidak membalas email ini secara langsung.
            <div class="confidential">
                Confidentiality Notice: This email and any attachments are confidential and intended solely for the use of the individual or entity to whom they are addressed.
            </div>
        </div>
    </div>
</body>
</html>
HTML;

                Mail::send([], [], function ($message) use ($email, $userAttachments, $startStr, $endStr, $emailBody) {
                    $message->to($email)
                        ->subject("Laporan KPI Mingguan Korlap ({$startStr} s.d {$endStr})")
                        ->html($emailBody);

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
     * ✅ INJECT REMARK KE DATA KORLAP
     * - remark_total per korlap
     * - remark_preview_lines per korlap (isi SEMUA TAG, bukan top 3)
     * - remark_lines per member (untuk kolom remark detail)
     */
    private function injectRemarksIntoKorlapData(string $plant, $dataForPdf, Carbon $start, Carbon $end)
    {
        $membersByKorlap = [];
        $allMemberSet = [];

        foreach ($dataForPdf as $g) {
            $korlapNik = (string)($g['korlap_nik'] ?? '');
            $members = $g['members'] ?? collect();

            $set = [];
            foreach ($members as $m) {
                $nik = trim((string)($m->nik ?? ''));
                if ($nik === '') continue;

                $allMemberSet[$nik] = true;
                $set[$nik] = true;
            }

            $membersByKorlap[$korlapNik] = array_values(array_keys($set));
        }

        $allMemberNiks = array_values(array_keys($allMemberSet));

        // ✅ remark per member nik
        $remarkByNik = !empty($allMemberNiks)
            ? WiDailyReport::export_remarkMapForNiks($plant, $this->dataVisibleFrom, $allMemberNiks, $start, $end)
            : [];

        // ✅ remark total + ALL tag per korlap (bukan top3)
        $previewTopAll = 9999;
        [$remarkTotalByKorlap, $remarkAllLinesByKorlap] = !empty($membersByKorlap)
            ? WiDailyReport::export_korlapRemarkTotalPreview($plant, $this->dataVisibleFrom, $membersByKorlap, $start, $end, $previewTopAll)
            : [[], []];

        // ✅ inject ke struktur data yang dikirim ke view PDF
        return $dataForPdf->map(function ($g) use ($remarkByNik, $remarkTotalByKorlap, $remarkAllLinesByKorlap) {
            $korlapNik = (string)($g['korlap_nik'] ?? '');

            $g['remark_total'] = (int)($remarkTotalByKorlap[$korlapNik] ?? 0);
            $g['remark_preview_lines'] = $remarkAllLinesByKorlap[$korlapNik] ?? [];
            if (!is_array($g['remark_preview_lines'])) $g['remark_preview_lines'] = [];

            $members = $g['members'] ?? collect();
            foreach ($members as $m) {
                $nik = trim((string)($m->nik ?? ''));
                $m->remark_lines = ($nik !== '' && isset($remarkByNik[$nik])) ? $remarkByNik[$nik] : [];
                if (!is_array($m->remark_lines)) $m->remark_lines = [];
            }
            $g['members'] = $members;

            return $g;
        });
    }

    /**
     * =========================================================================
     * CORE LOGIC DATABASE (TIDAK PERLU DIUBAH)
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
            ->selectRaw("qm.nik, qm.begda, qm.nama, qm.wc, qm.devisi,
                (qm.mintu_sum / {$this->qmDivisor}) as time_qm,
                (qm.mint2_sum / {$this->qmDivisor}) as time_conf,
                wi.time_wi as time_wi");
    }

    protected function qmDaySub(string $plant, array $dateYmd)
    {
        [$startYmd, $endYmd] = $dateYmd;

        return DB::table($this->qmTable)
            ->whereBetween('begda', [$startYmd, $endYmd])
            ->whereRaw("UPPER(TRIM(role)) = 'INDUK'")
            ->whereRaw("CASE
                WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001'
                WHEN LEFT(TRIM(werks),1)='1' THEN '1000'
                ELSE CONCAT(LEFT(TRIM(werks),1),'000')
            END = ?", [$plant])
            ->selectRaw("pernr as nik, begda, MAX(cname) as nama, MAX(arbpl) as wc, MAX(NULLIF(TRIM(devisi),'')) as devisi,
                COALESCE(SUM(mintu),0) as mintu_sum,
                COALESCE(SUM(mint2),0) as mint2_sum")
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
            ->whereRaw("CASE
                WHEN LEFT(TRIM(kode_laravel),4) IN ('1001','1002','1003','1015') THEN '1001'
                WHEN LEFT(TRIM(kode_laravel),1) = '1' THEN '1000'
                ELSE CONCAT(LEFT(TRIM(kode_laravel),1),'000')
            END = ?", [$plant])
            ->selectRaw("nik, tanggal, COALESCE(SUM(total_time_wi),0) as time_wi")
            ->groupBy('nik', 'tanggal');
    }
}
