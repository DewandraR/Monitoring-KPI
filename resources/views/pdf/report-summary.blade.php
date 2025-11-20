{{-- resources/views/pdf/report-summary.blade.php --}}

@php
    use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Report Data - yppr058_data</title>
    <style>
        /** GLOBAL SETTINGS */
        @page {
            margin: 1cm 1cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7pt;
            color: #333333;
            line-height: 1.3;
        }

        /** HEADER SECTION **/
        .header-container {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
        }

        .header-table {
            width: 100%;
        }

        .header-title {
            font-size: 16pt;
            font-weight: bold;
            color: #064e3b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header-subtitle {
            font-size: 9pt;
            color: #059669;
            margin-top: 4px;
        }

        .header-meta {
            text-align: right;
            font-size: 8pt;
            color: #6b7280;
        }

        /** TABLE DESIGN **/
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* Header Tabel */
        table.data-table thead th {
            background-color: #065f46;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
            font-size: 6.5pt;
            text-transform: uppercase;
            padding: 8px 4px;
            border-bottom: 2px solid #064e3b;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }

        /* Baris Data */
        table.data-table tbody td {
            padding: 6px 4px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
            word-wrap: break-word;
        }

        /* Zebra Striping */
        table.data-table tbody tr:nth-child(even) {
            background-color: #f0fdf4;
        }

        /* Typography Helper */
        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .font-mono {
            font-family: 'Courier New', Courier, monospace;
            letter-spacing: -0.5px;
        }

        .font-bold {
            font-weight: bold;
        }

        /* Warna Text Khusus */
        .text-emerald {
            color: #047857;
        }

        .text-gray {
            color: #4b5563;
        }

        /** KOLOM WIDTH CONFIGURATION **/
        .col-no {
            width: 4%;
        }

        .col-pernr {
            width: 8%;
        }

        .col-range {
            width: 12%;
        }

        .col-nama {
            width: 20%;
        }

        .col-wc {
            width: 14%;
        }

        .col-menit {
            width: 7%;
        }

        .col-upah {
            width: 8%;
        }
    </style>
</head>

<body>

    <div class="header-container">
        <table class="header-table">
            <tr>
                <td style="vertical-align: bottom;">
                    <div class="header-title">Laporan Ringkasan</div>
                    <div class="header-subtitle">Sistem Personalia WC-Person &bull; yppr058_data</div>
                </td>
                <td style="vertical-align: bottom;" class="header-meta">
                    <div>
                        <strong>PLANT:</strong>
                        <span style="font-size: 11pt; color: #059669;">{{ $werks }}</span>
                    </div>
                    <div style="margin-top: 2px;">
                        Dicetak: {{ Carbon::now()->isoFormat('D MMMM Y, HH:mm') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-pernr">Personal No</th>
                <th class="col-range">Rentang Tanggal</th>

                <th class="col-nama">Nama</th>

                <th class="col-wc">WC Personal</th>
                <th class="col-wc">DESC WC</th>

                <th class="col-menit">Menit Hadir</th>
                <th class="col-menit">Menit Conf</th>
                <th class="col-menit">Menit Inspect</th>
                <th class="col-menit">Detik Inspect</th>
                <th class="col-menit">Detik Konf</th>
                <th class="col-upah">Upah Hadir</th>
                <th class="col-upah">Upah Inspect</th>
                <th class="col-upah">Var Upah</th>
                <th class="col-upah">% Var</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $data)
                <tr>
                    {{-- NO --}}
                    <td class="text-center text-gray">{{ $i + 1 }}</td>

                    {{-- PERSONAL NO --}}
                    <td class="font-bold text-emerald text-center">{{ $data->pernr }}</td>

                    {{-- RENTANG TANGGAL --}}
                    <td class="text-center text-gray" style="font-size: 6pt;">
                        {{ Carbon::createFromFormat('Ymd', $data->min_begda)->format('d/m/y') }}
                        -
                        {{ Carbon::createFromFormat('Ymd', $data->max_begda)->format('d/m/y') }}
                    </td>

                    {{-- NAMA --}}
                    <td class="text-left" style="text-transform: capitalize;">
                        {{ strtolower($data->cname) }}
                    </td>

                    {{-- WC PERSONAL --}}
                    <td class="text-center text-gray" style="font-size: 6pt;">
                        {{ $data->arbpl }}
                    </td>

                    {{-- DESC WC (FULL) --}}
                    <td class="text-left text-gray" style="font-size: 6pt;">
                        {{ $data->desc }}
                    </td>

                    {{-- MENIT HADIR (total_jam) --}}
                    <td class="text-center font-mono">
                        {{ number_format($data->total_jam, 1) }}
                    </td>

                    {{-- MENIT CONF (mint2) --}}
                    <td class="text-center font-mono">
                        {{ (int) $data->mint2 }}
                    </td>

                    {{-- MENIT INSPECT (mintu) --}}
                    <td class="text-center font-mono">
                        {{ (int) $data->mintu }}
                    </td>

                    {{-- DETIK INSPECT (mintu2) --}}
                    <td class="text-center font-mono">
                        {{ (int) $data->mintu2 }}
                    </td>

                    {{-- DETIK KONFIRMASI (mintu3) --}}
                    <td class="text-center font-mono">
                        {{ (int) $data->mintu3 }}
                    </td>

                    {{-- UPAH HADIR (gji) --}}
                    <td class="text-center font-mono">
                        {{ number_format($data->gji, 0, ',', '.') }}
                    </td>

                    {{-- UPAH INSPECT (gji2) --}}
                    <td class="text-center font-mono">
                        {{ number_format($data->gji2, 0, ',', '.') }}
                    </td>

                    {{-- VAR UPAH (varnt) --}}
                    <td class="text-center font-mono" style="color: {{ $data->varnt < 0 ? '#dc2626' : '#333' }};">
                        {{ number_format($data->varnt, 0, ',', '.') }}
                    </td>

                    @php
                        // Persentase Var = TOTAL Var Upah / TOTAL Upah Inspect * 100
                        $gji2 = (float) $data->gji2; // Upah Inspect total
                        $varnt = (float) $data->varnt; // Var Upah total
                        $persenVar = $gji2 != 0.0 ? ($varnt / $gji2) * 100 : 0.0;
                    @endphp
                    <td class="text-center font-mono">
                        {{ number_format($persenVar, 2) }}%
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
