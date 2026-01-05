{{-- resources/views/pdf/wi-detail.blade.php --}}

@php
    use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>WI Detail - {{ $plant }}</title>
    <style>
        @page { margin: 1cm 1cm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color:#374151; line-height:1.25; }

        .header-container { width:100%; margin-bottom: 14px; border-bottom:2px solid #059669; padding-bottom:8px; }
        .header-title { font-size:14pt; font-weight:800; color:#064e3b; text-transform:uppercase; letter-spacing:.5px; }
        .header-subtitle { font-size:8pt; color:#059669; margin-top:2px; }

        table.data-table { width:100%; border-collapse:collapse; table-layout:fixed; }
        table.data-table thead th {
            background:#065f46; color:#fff; text-align:center; font-weight:bold;
            font-size:8pt; text-transform:uppercase; padding:6px 3px; border-bottom:2px solid #064e3b;
        }
        table.data-table tbody td {
            padding:4px 3px; border-bottom:1px solid #e5e7eb; vertical-align:middle;
            word-wrap:break-word; font-size:8pt; font-weight:bold;
        }
        table.data-table tbody tr:nth-child(even) { background:#f0fdf4; }

        .text-center{ text-align:center; }
        .text-left{ text-align:left; }
        .font-mono{ font-family:'Courier New', Courier, monospace; }

        .col-no{ width:5%; }
        .col-tgl{ width:12%; }
        .col-nik{ width:12%; }
        .col-nama{ width:20%; }
        .col-devisi{ width:14%; } /* ✅ kolom baru */
        .col-wc{ width:10%; }
        .col-num{ width:11%; }
        .col-kpi{ width:9%; }

        .group-header-row td{
            background:#e5e7eb;
            border-top:1px solid #059669;
            border-bottom:1px solid #059669;
            padding:5px 6px;
            font-size:7pt;
        }
        .group-header-label{ font-weight:bold; color:#065f46; text-transform:uppercase; letter-spacing:.3px; }
        .group-header-value{ font-weight:bold; color:#111827; }
    </style>
</head>

<body>
    <div class="header-container">
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:bottom;">
                    <div class="header-title">WI Daily Report - Detail</div>
                    <div class="header-subtitle">Plant {{ $plant }} &bull; Periode {{ $rangeStart }} s.d. {{ $rangeEnd }}</div>
                </td>
                <td style="text-align:right; font-size:8pt; color:#6b7280;">
                    Dicetak: {{ Carbon::now()->isoFormat('D MMMM Y, HH:mm') }}
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-tgl">Tanggal</th>
                <th class="col-nik">NIK</th>
                <th class="col-nama">Nama</th>
                <th class="col-devisi">Devisi</th> {{-- ✅ tambah --}}
                <th class="col-wc">WC</th>
                <th class="col-num">Time WI</th>
                <th class="col-num">Time QM</th>
                <th class="col-kpi">% KPI</th>
            </tr>
        </thead>

        <tbody>
            @php
                $currentKey = null;
                $rowNumberPerPerson = 0;
            @endphp

            @foreach($rows as $r)
                @php
                    $groupKey = (string)$r->nik . '|' . strtolower((string)$r->nama);
                @endphp

                @if($groupKey !== $currentKey)
                    @php
                        $currentKey = $groupKey;
                        $rowNumberPerPerson = 0;

                        $devisiHeader = (string)($r->devisi ?? '');
                        $devisiHeaderShow = $devisiHeader !== '' ? $devisiHeader : '-';
                    @endphp
                    <tr class="group-header-row">
                        <td colspan="9">
                            <span class="group-header-label">Personal:</span>
                            <span class="group-header-value">{{ $r->nik }}</span>
                            &mdash;
                            <span style="text-transform:capitalize;">{{ strtolower((string)$r->nama) }}</span>
                            &nbsp;&bull;&nbsp;
                            <span class="group-header-label">Devisi:</span>
                            <span class="group-header-value">{{ $devisiHeaderShow }}</span>
                        </td>
                    </tr>
                @endif

                @php
                    $rowNumberPerPerson++;
                    $timeWi = $r->time_wi; // bisa null
                    $timeQm = $r->time_qm; // bisa null
                    $kpi    = $r->kpi_pct; // null kalau WI null

                    $devisi = (string)($r->devisi ?? '');
                    $devisiShow = $devisi !== '' ? $devisi : '-';
                @endphp

                <tr>
                    <td class="text-center">{{ $rowNumberPerPerson }}</td>
                    <td class="text-center font-mono">{{ Carbon::parse($r->tanggal)->format('d/m/y') }}</td>
                    <td class="text-center font-mono">{{ $r->nik }}</td>
                    <td class="text-left" style="text-transform:capitalize;">{{ strtolower((string)$r->nama) }}</td>
                    <td class="text-center font-mono">{{ $devisiShow }}</td> {{-- ✅ tampil devisi --}}
                    <td class="text-center font-mono">{{ $r->wc ?? '-' }}</td>

                    <td class="text-center font-mono">
                        {{ is_null($timeWi) ? '-' : number_format((float)$timeWi, 2) }}
                    </td>

                    <td class="text-center font-mono">
                        {{ is_null($timeQm) ? '-' : number_format((float)$timeQm, 2) }}
                    </td>

                    <td class="text-center font-mono">
                        {{ is_null($timeWi) ? '-' : number_format((float)($kpi ?? 0), 0) . '%' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $text  = "Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $size  = 6;
            $font  = $fontMetrics->getFont("Helvetica", "italic");
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $pdf->page_text(
                $pdf->get_width() - $width - 30,
                $pdf->get_height() - 20,
                $text,
                $font,
                $size,
                [0.6, 0.6, 0.6]
            );
        }
    </script>
</body>
</html>
