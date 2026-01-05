{{-- resources/views/pdf/wi-summary.blade.php --}}

@php
    use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>WI Summary - {{ $plant }}</title>
    <style>
        @page { margin: 1cm 1cm; size: landscape; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color:#1f2937; line-height:1.2; }

        .header-container { width:100%; margin-bottom: 14px; border-bottom:2px solid #059669; padding-bottom:8px; }
        .header-title { font-size:14pt; font-weight:800; color:#064e3b; text-transform:uppercase; letter-spacing:.5px; margin:0; }
        .header-subtitle { font-size:8pt; color:#059669; margin-top:2px; font-weight:600; }
        .header-meta { text-align:right; font-size:7pt; color:#6b7280; vertical-align:bottom; }

        table.data-table { width:100%; border-collapse:collapse; table-layout:auto; }
        table.data-table thead th {
            background:#065f46; color:#fff; text-align:center;
            font-weight:bold; font-size:8pt; text-transform:uppercase;
            padding:6px 3px; border:1px solid rgba(255,255,255,.2);
        }
        table.data-table tbody td {
            padding:5px 3px; border:1px solid #e5e7eb; vertical-align:middle; font-size:8pt; font-weight:bold;
        }
        table.data-table tbody tr:nth-child(even) { background:#f0fdf4; }

        .text-center{ text-align:center; }
        .text-left{ text-align:left; }
        .text-right{ text-align:right; }
        .font-mono{ font-family:'Courier New', Courier, monospace; letter-spacing:-0.2px; }
        .nowrap{ white-space:nowrap; overflow:hidden; }

        .w-no{ width:4%; }
        .w-nik{ width:10%; }
        .w-range{ width:16%; }
        .w-nama{ width:22%; }
        .w-wc{ width:10%; }
        .w-num{ width:12%; }
        .w-kpi{ width:8%; }
    </style>
</head>
<body>

    <div class="header-container">
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:bottom;">
                    <div class="header-title">WI Daily Report - Summary</div>
                    <div class="header-subtitle">Plant {{ $plant }} &bull; Periode {{ $rangeStart }} s.d. {{ $rangeEnd }}</div>
                </td>
                <td class="header-meta">
                    <div><strong>Dicetak:</strong> {{ Carbon::now()->isoFormat('D MMM Y, HH:mm') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="w-no">No</th>
                <th class="w-nik">NIK</th>
                <th class="w-range">Rentang Tanggal</th>
                <th class="w-nama">Nama</th>
                <th class="w-wc">WC</th>
                <th class="w-num">Time WI (SUM)</th>
                <th class="w-num">Time QM (SUM)</th>
                <th class="w-kpi">% KPI</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                @php
                    $minDate = !empty($row->min_tanggal) ? Carbon::parse($row->min_tanggal)->format('d/m/y') : '-';
                    $maxDate = !empty($row->max_tanggal) ? Carbon::parse($row->max_tanggal)->format('d/m/y') : '-';

                    $wiSum = (float)($row->time_wi_sum ?? 0);
                    $qmSum = (float)($row->time_qm_sum ?? 0);
                    $kpi   = isset($row->kpi_pct) ? (float)$row->kpi_pct : ($wiSum == 0 ? 0 : ($qmSum / $wiSum) * 100);
                @endphp
                <tr>
                    <td class="text-center">{{ $i+1 }}</td>
                    <td class="text-center font-mono nowrap">{{ $row->nik }}</td>
                    <td class="text-center font-mono nowrap">{{ $minDate }} - {{ $maxDate }}</td>
                    <td class="text-left" style="text-transform:capitalize;">{{ strtolower((string)($row->nama ?? '-')) }}</td>
                    <td class="text-center font-mono nowrap">{{ $row->wc ?? '-' }}</td>
                    <td class="text-center font-mono nowrap">{{ number_format($wiSum, 2) }}</td>
                    <td class="text-center font-mono nowrap">{{ number_format($qmSum, 2) }}</td>
                    <td class="text-center font-mono nowrap">{{ number_format($kpi, 0) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $size = 6;
            $font = $fontMetrics->getFont("Helvetica", "italic");
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $pdf->page_text(
                $pdf->get_width() - $width - 30,
                $pdf->get_height() - 20,
                $text,
                $font,
                $size,
                [0.4, 0.4, 0.4]
            );
        }
    </script>

</body>
</html>
