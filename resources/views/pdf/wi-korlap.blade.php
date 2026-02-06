@php
    use Carbon\Carbon;
    use App\Livewire\WiDailyReport;

    // rangeStart/rangeEnd dikirim dari controller export (string Y-m-d)
    $start = Carbon::parse($rangeStart)->startOfDay();
    $end   = Carbon::parse($rangeEnd)->startOfDay();

    // samakan dengan Livewire
    $dataVisibleFrom = '2025-12-01';

    // kumpulkan semua member NIK & mapping korlap->member
    $membersByKorlap = [];
    $allMemberSet = [];

    foreach ($data as $g) {
        $korlapNik = (string)($g['korlap_nik'] ?? '');
        $members = $g['members'] ?? [];

        $set = [];
        foreach ($members as $m) {
            $nik = is_object($m) ? (string)($m->nik ?? '') : (string)($m['nik'] ?? '');
            $nik = trim($nik);
            if ($nik === '') continue;

            $allMemberSet[$nik] = true;
            $set[$nik] = true;
        }

        $membersByKorlap[$korlapNik] = array_values(array_keys($set));
    }

    $allMemberNiks = array_values(array_keys($allMemberSet));

    // remark per member nik (untuk kolom REMARK anggota)
    $remarkByNik = !empty($allMemberNiks)
        ? WiDailyReport::export_remarkMapForNiks($plant, $dataVisibleFrom, $allMemberNiks, $start, $end)
        : [];

    /**
     * ✅ Formatter DETAIL (tetap pakai "Task" seperti semula untuk tabel member)
     */
    $fmtRemarkDetail = function ($txt) {
        $txt = preg_replace('/\s+/u', ' ', trim((string)$txt));
        $safe = e($txt);
        // "(1 Task)" -> nowrap
        $safe = preg_replace('/\((\d+)\s*Task\)/i', '<span class="nowrap">($1&nbsp;Task)</span>', $safe);
        return $safe;
    };

    // (Logika remark summary korlap telah dihapus karena tabelnya dihilangkan)
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>WI Report Korlap - {{ $plant }}</title>

    <style>
        @page { margin: 1cm; size: landscape; }

        body{
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color:#1a202c;
            line-height:1.3;
            background-color:#fff;
        }

        .header-container{
            width:100%;
            margin-bottom: 25px;
            border-bottom: 3px solid #2d3748;
            padding-bottom:10px;
        }
        .header-title{
            font-size:16pt;
            font-weight:bold;
            color:#2d3748;
            text-transform:uppercase;
            margin:0;
            letter-spacing:1px;
        }
        .header-subtitle{
            font-size:10pt;
            color:#4a5568;
            margin-top:5px;
            font-weight:bold;
            font-family:'Courier New', monospace;
        }
        .header-meta{
            text-align:right;
            font-size:8pt;
            color:#25292e;
            vertical-align:bottom;
            font-family:'Courier New', monospace;
        }

        /* --- LOGIKA HALAMAN --- */
        .korlap-section{
            /* Memastikan container ini bersih, page break diatur via inline style di loop */
            display: block;
            width: 100%;
        }

        /* Wrapper Header Korlap (Tabel Summary) */
        .korlap-header-wrapper {
            display: block;
            page-break-inside: avoid; 
            page-break-after: avoid;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        /* --- TABEL KORLAP (SUMMARY) --- */
        table.korlap-table{
            width:100%;
            border-collapse:collapse;
            margin-bottom: 5px;
            border:2px solid #1a202c;
            page-break-inside: avoid;
            page-break-after: avoid; 
        }
        table.korlap-table th{
            background:#2d3748;
            color:#edf2f7;
            text-align:center;
            font-weight:bold;
            font-size:9pt;
            text-transform:uppercase;
            padding:10px 6px;
            border:1px solid #1a202c;
            letter-spacing:0.5px;
            vertical-align:middle;
        }
        table.korlap-table td{
            background:#f1f5f9;
            padding:10px 6px;
            border:1px solid #cbd5e0;
            vertical-align:middle;
            font-size:10pt;
            font-weight:normal;
            color:#1a202c;
        }
        .korlap-name-cell{
            font-weight:bold !important;
            color:#2d3748;
        }

        .label-detail{
            font-size:9pt;
            font-weight:bold;
            color:#4a5568;
            margin-left:4%;
            margin-bottom:5px;
            margin-top:8px;
            display:block;
            text-transform:uppercase;
            font-family:'Courier New', monospace;
            page-break-after: avoid; 
            page-break-inside: avoid;
        }

        /* --- TABEL MEMBER (DETAIL) --- */
        table.member-table{
            width:96%;
            margin-left:4%;
            border-collapse:collapse;
            border:1px solid #a0aec0;
            table-layout: fixed;
            margin-top: 0;
            page-break-before: avoid; /* Nempel ke header korlap */
        }
        table.member-table th{
            background:#e2e8f0;
            color:#2d3748;
            text-align:center;
            font-weight:bold;
            font-size:8.5pt;
            text-transform:uppercase;
            padding:6px;
            border:1px solid #a0aec0;
            vertical-align:middle;
        }
        table.member-table td{
            padding:6px;
            border:1px solid #e2e8f0;
            vertical-align:middle;
            font-size:9pt;
            font-weight:normal;
            color:#4a5568;
        }
        table.member-table tr:nth-child(even){ background:#f8fafc; }

        .remark-cell{
            font-size:9.5pt;
            line-height:1.25;
            color:#1f2937;
            white-space:normal;
            word-break: normal;
            overflow-wrap: break-word;
            vertical-align:middle;
        }
        .remark-line{ margin:1px 0; }
        .nowrap{ white-space:nowrap; }

        .text-center{ text-align:center; }
        .text-left{ text-align:left; }
        .text-right{ text-align:right; }
        
        table.member-table tr { page-break-inside: avoid; }
    </style>
</head>

<body>
    {{-- Header Halaman (Muncul di awal PDF) --}}
    <div class="header-container">
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:bottom;">
                    <div class="header-title">Laporan Tim Korlap</div>
                    <div class="header-subtitle">
                        Plant {{ $plant }} | Periode: {{ $rangeStart }} s.d. {{ $rangeEnd }}
                    </div>
                </td>
                <td class="header-meta">
                    <div>Printed: {{ Carbon::now()->isoFormat('D MMM Y, HH:mm') }}</div>
                </td>
            </tr>
        </table>
    </div>

    @foreach($data as $idx => $group)
        @php
            $kpiQtyKorlap     = (float)($group['summary']['kpi_qty_pct'] ?? 0);
            $kpiQualityKorlap = (float)($group['summary']['kpi_quality_pct'] ?? 0);
        @endphp

        {{-- 
            ✅ LOGIKA PAGE BREAK:
            Jika bukan iterasi terakhir, paksa page-break-after: always.
            Ini membuat Korlap 1 di Hal 1, Korlap 2 di Hal 2, dst.
        --}}
        <div class="korlap-section" style="{{ !$loop->last ? 'page-break-after: always;' : '' }}">
            
            <div class="korlap-header-wrapper">
                <table class="korlap-table">
                    <tbody>
                        <tr>
                            <th style="width:4%">No</th>
                            <th style="width:9%">NIK Korlap</th>
                            <th style="width:18%">Nama Korlap</th>
                            <th style="width:16%">WC Anggota</th>
                            <th style="width:6%">Jml NIK INDUK WI</th>
                            <th style="width:9%">Menit WI</th>
                            <th style="width:9%">Menit CONF</th>
                            <th style="width:9%">Time QM</th>
                            <th style="width:10%">% HASIL MENIT CONF</th>
                            <th style="width:10%">% HASIL MENIT QM</th>
                        </tr>
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td class="text-center">{{ $group['korlap_nik'] ?? '-' }}</td>
                            <td class="text-left korlap-name-cell" style="text-transform:uppercase;">
                                {{ strtoupper((string)($group['korlap_nama'] ?? '-')) }}
                            </td>
                            <td class="text-left" style="font-size:8pt; word-wrap:break-word;">
                                {{ $group['summary']['wc_string'] ?? '-' }}
                            </td>
                            <td class="text-center">{{ $group['summary']['count_nik'] ?? 0 }}</td>
                            <td class="text-center">{{ number_format((float)($group['summary']['total_wi'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-center">{{ number_format((float)($group['summary']['total_conf'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-center">{{ number_format((float)($group['summary']['total_qm'] ?? 0), 2, ',', '.') }}</td>

                            <td class="text-center" style="font-weight:bold; {{ $kpiQtyKorlap < 100 ? 'color:#c53030;' : 'color:#047857;' }}">
                                {{ number_format($kpiQtyKorlap, 2, ',', '.') }}%
                            </td>

                            <td class="text-center" style="font-weight:bold; {{ $kpiQualityKorlap < 100 ? 'color:#c53030;' : 'color:#047857;' }}">
                                {{ number_format($kpiQualityKorlap, 2, ',', '.') }}%
                            </td>
                        </tr>
                        {{-- ✅ BARIS REMARK KORLAP TELAH DIHAPUS SESUAI PERMINTAAN --}}
                    </tbody>
                </table>
                
                <span class="label-detail">DETAIL ANGGOTA:</span>
            </div>

            <table class="member-table">
                <thead>
                    <tr>
                        <th style="width:3%;">No</th>
                        <th style="width:7%;">NIK</th>
                        <th style="width:15%;">Nama Anggota</th>
                        <th style="width:10%;">Devisi</th>
                        <th style="width:6%;">WC</th>
                        <th style="width:7%;">Menit WI</th>
                        <th style="width:7%;">Menit CONF</th>
                        <th style="width:7%;">Time QM</th>
                        <th style="width:7%;">HASIL MENIT CONF %</th>
                        <th style="width:7%;">HASIL MENIT QM %</th>
                        <th style="width:24%;">Remark</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach(($group['members'] ?? []) as $mIdx => $m)
                        @php
                            $wi   = (float)($m->time_wi_sum ?? 0);
                            $conf = (float)($m->time_conf_sum ?? 0);
                            $qm   = (float)($m->time_qm_sum ?? 0);

                            $kpiQty     = (float)($m->kpi_qty_pct ?? 0);
                            $kpiQuality = (float)($m->kpi_quality_pct ?? 0);

                            $nikMember = trim((string)($m->nik ?? ''));

                            $lines = $m->remark_lines ?? ($remarkByNik[$nikMember] ?? []);
                            if (!is_array($lines)) $lines = [];
                        @endphp

                        <tr>
                            <td class="text-center">{{ $mIdx + 1 }}</td>
                            <td class="text-center">{{ $m->nik ?? '-' }}</td>
                            <td class="text-left" style="text-transform:capitalize;">
                                {{ strtolower((string)($m->nama ?? '-')) }}
                            </td>
                            <td class="text-left">{{ $m->devisi ?? '-' }}</td>
                            <td class="text-center">{{ $m->wc ?? '-' }}</td>

                            <td class="text-center">{{ number_format($wi, 2, ',', '.') }}</td>
                            <td class="text-center">{{ number_format($conf, 2, ',', '.') }}</td>
                            <td class="text-center">{{ number_format($qm, 2, ',', '.') }}</td>

                            <td class="text-center" style="font-weight:bold; {{ $kpiQty < 100 ? 'color:#c53030;' : 'color:#047857;' }}">
                                {{ number_format($kpiQty, 2, ',', '.') }}%
                            </td>

                            <td class="text-center" style="font-weight:bold; {{ $kpiQuality < 100 ? 'color:#c53030;' : 'color:#047857;' }}">
                                {{ number_format($kpiQuality, 2, ',', '.') }}%
                            </td>

                            <td class="text-left remark-cell">
                                @if(!empty($lines))
                                    @foreach($lines as $line)
                                        <div class="remark-line">{!! $fmtRemarkDetail($line) !!}</div>
                                    @endforeach
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    @endforeach

    {{-- FOOTER: NOMOR HALAMAN --}}
    <script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont("Helvetica", "normal");
        $y = $pdf->get_height() - 24;
        $pdf->page_text($pdf->get_width() - 120, $y, "Page {PAGE_NUM}/{PAGE_COUNT}", $font, 9, [0,0,0]);
    }
    </script>
</body>
</html>