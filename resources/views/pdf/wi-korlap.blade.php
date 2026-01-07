@php
    use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>WI Report Korlap - {{ $plant }}</title>

    <style>
        @page { margin: 1cm; size: landscape; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color:#1a202c;
            line-height:1.3;
            background-color:#fff;
        }

        .header-container {
            width:100%;
            margin-bottom: 25px;
            border-bottom: 3px solid #2d3748;
            padding-bottom:10px;
        }
        .header-title {
            font-size:16pt;
            font-weight:bold;
            color:#2d3748;
            text-transform:uppercase;
            margin:0;
            letter-spacing: 1px;
        }
        .header-subtitle {
            font-size:10pt;
            color:#4a5568;
            margin-top:5px;
            font-weight:bold;
            font-family: 'Courier New', monospace;
        }
        .header-meta {
            text-align:right;
            font-size:8pt;
            color:#25292e;
            vertical-align:bottom;
            font-family: 'Courier New', monospace;
        }

        .korlap-section {
            margin-bottom: 35px;
            break-inside: avoid;
        }

        /* --- TABEL KORLAP (SUMMARY) --- */
        table.korlap-table {
            width:100%;
            border-collapse:collapse;
            margin-bottom: 12px;
            border: 2px solid #1a202c;
        }
        table.korlap-table th {
            background:#2d3748;
            color:#edf2f7;
            text-align:center;
            font-weight:bold;
            font-size:9pt;
            text-transform:uppercase;
            padding:10px 6px;
            border:1px solid #1a202c;
            letter-spacing: 0.5px;
        }
        table.korlap-table td {
            background:#f1f5f9;
            padding:10px 6px;
            border:1px solid #cbd5e0;
            vertical-align:middle;
            font-size:10pt;
            font-weight:normal;
            color:#1a202c;
        }
        .korlap-name-cell {
            font-weight: bold !important;
            color: #2d3748;
        }

        /* --- LABEL DETAIL --- */
        .label-detail {
            font-size: 9pt;
            font-weight: bold;
            color: #4a5568;
            margin-left: 4%;
            margin-bottom: 5px;
            display: block;
            text-transform: uppercase;
            font-family: 'Courier New', monospace;
        }

        /* --- TABEL MEMBER (DETAIL) --- */
        table.member-table {
            width:96%;
            margin-left: 4%;
            border-collapse:collapse;
            border: 1px solid #a0aec0;
        }
        table.member-table th {
            background:#e2e8f0;
            color:#2d3748;
            text-align:center;
            font-weight:bold;
            font-size:8pt;
            text-transform:uppercase;
            padding:6px;
            border:1px solid #a0aec0;
        }
        table.member-table td {
            padding:6px;
            border:1px solid #e2e8f0;
            vertical-align:middle;
            font-size:8.5pt;
            font-weight:normal;
            color:#4a5568;
        }
        table.member-table tr:nth-child(even) { background:#f8fafc; }

        /* --- UTILITIES --- */
        .text-center { text-align:center; }
        .text-left { text-align:left; }
        .text-right { text-align:right; }
        .font-mono { font-family:'Courier New', monospace; font-weight: 600; }

        .badge-filter {
            display:inline-block;
            padding: 3px 8px;
            border-radius: 0;
            font-size: 8pt;
            font-weight: bold;
            background: #e2e8f0;
            color: #2d3748;
            margin-left: 15px;
            border: 2px solid #2d3748;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    <div class="header-container">
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:bottom;">
                    <div class="header-title">Laporan Tim Korlap</div>
                    <div class="header-subtitle">
                        Plant {{ $plant }} | Periode: {{ $rangeStart }} s.d. {{ $rangeEnd }}

                        @if(($wiMode ?? 'all') === 'with')
                            <span class="badge-filter" style="background:#d1fae5; color:#065f46; border-color:#059669;">
                                FILTER: HANYA YG ADA WI
                            </span>
                        @elseif(($wiMode ?? 'all') === 'without')
                            <span class="badge-filter" style="background:#fee2e2; color:#991b1b; border-color:#b91c1c;">
                                FILTER: BELUM ADA WI
                            </span>
                        @endif
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

        <div class="korlap-section">

            {{-- SUMMARY KORLAP --}}
            <table class="korlap-table">
                <thead>
                    <tr>
                        <th style="width:4%">No</th>
                        <th style="width:9%">NIK Korlap</th>
                        <th style="width:18%">Nama Korlap</th>
                        <th style="width:16%">WC Korlap</th>
                        <th style="width:6%">Jml NIK</th>
                        <th style="width:9%">Time WI</th>
                        <th style="width:9%">Time CONF</th>
                        <th style="width:9%">Time QM</th>
                        <th style="width:10%">% KPI QTY</th>
                        <th style="width:10%">% KPI QUALITY</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $group['korlap_nik'] }}</td>
                        <td class="text-left korlap-name-cell" style="text-transform:uppercase;">
                            {{ strtolower($group['korlap_nama']) }}
                        </td>
                        <td class="text-left" style="font-size:8pt; word-wrap:break-word;">
                            {{ $group['summary']['wc_string'] }}
                        </td>
                        <td class="text-center">{{ $group['summary']['count_nik'] }}</td>
                        <td class="text-center">{{ number_format((float)$group['summary']['total_wi'], 2) }}</td>
                        <td class="text-center">{{ number_format((float)$group['summary']['total_conf'], 2) }}</td>
                        <td class="text-center">{{ number_format((float)$group['summary']['total_qm'], 2) }}</td>

                        <td class="text-center" style="font-weight:bold; {{ $kpiQtyKorlap < 100 ? 'color:#c53030;' : 'color:#047857;' }}">
                            {{ number_format($kpiQtyKorlap, 0) }}%
                        </td>

                        <td class="text-center" style="font-weight:bold; {{ $kpiQualityKorlap < 100 ? 'color:#c53030;' : 'color:#047857;' }}">
                            {{ number_format($kpiQualityKorlap, 0) }}%
                        </td>
                    </tr>
                </tbody>
            </table>

            <span class="label-detail">DETAIL ANGGOTA:</span>

            {{-- DETAIL MEMBER --}}
            <table class="member-table">
                <thead>
                    <tr>
                        <th style="width:4%;">No</th>
                        <th style="width:10%;">NIK</th>
                        <th style="width:20%;">Nama Anggota</th>
                        <th style="width:14%;">Devisi</th>
                        <th style="width:8%;">WC</th>
                        <th style="width:9%;">Time WI</th>
                        <th style="width:9%;">Time CONF</th>
                        <th style="width:9%;">Time QM</th>
                        <th style="width:9%;">KPI QTY %</th>
                        <th style="width:9%;">KPI QUALITY %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['members'] as $mIdx => $m)
                        @php
                            $wi   = (float)($m->time_wi_sum ?? 0);
                            $conf = (float)($m->time_conf_sum ?? 0);
                            $qm   = (float)($m->time_qm_sum ?? 0);

                            $kpiQty     = (float)($m->kpi_qty_pct ?? 0);
                            $kpiQuality = (float)($m->kpi_quality_pct ?? 0);
                        @endphp

                        <tr>
                            <td class="text-center">{{ $mIdx + 1 }}</td>
                            <td class="text-center">{{ $m->nik }}</td>
                            <td class="text-left" style="text-transform:capitalize;">{{ strtolower($m->nama) }}</td>
                            <td class="text-left">{{ $m->devisi ?? '-' }}</td>
                            <td class="text-center">{{ $m->wc }}</td>
                            <td class="text-center">{{ number_format($wi, 2) }}</td>
                            <td class="text-center">{{ number_format($conf, 2) }}</td>
                            <td class="text-center">{{ number_format($qm, 2) }}</td>

                            <td class="text-center" style="font-weight:bold; {{ $kpiQty < 100 ? 'color:#c53030;' : 'color:#047857;' }}">
                                {{ number_format($kpiQty, 0) }}%
                            </td>

                            <td class="text-center" style="font-weight:bold; {{ $kpiQuality < 100 ? 'color:#c53030;' : 'color:#047857;' }}">
                                {{ number_format($kpiQuality, 0) }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    @endforeach

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page {PAGE_NUM} of {PAGE_COUNT} | Industrial Report System";
            $size = 7;
            $font = $fontMetrics->getFont("Courier New", "normal");
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
