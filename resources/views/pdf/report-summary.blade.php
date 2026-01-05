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
        /** KONFIGURASI HALAMAN */
        @page {
            margin: 1cm 1cm;
            size: landscape; /* Disarankan Landscape agar kolom muat dengan lega */
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7pt; /* Ukuran font disesuaikan agar muat */
            color: #1f2937;
            line-height: 1.2;
        }

        /** HEADER UTAMA DOKUMEN */
        .header-container {
            width: 100%;
            margin-bottom: 15px;
            border-bottom: 2px solid #059669;
            padding-bottom: 8px;
        }

        .header-table {
            width: 100%;
            border: none;
        }

        .header-title {
            font-size: 14pt;
            font-weight: 800;
            color: #064e3b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .header-subtitle {
            font-size: 8pt;
            color: #059669;
            margin-top: 2px;
            font-weight: 600;
        }

        .header-meta {
            text-align: right;
            font-size: 7pt;
            color: #6b7280;
            vertical-align: bottom;
        }

        /** DESAIN TABEL DATA */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto; /* Auto allow column to fit content, but we control via width classes */
        }

        /* Styling Header Tabel */
        table.data-table thead th {
            background-color: #065f46;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
            padding: 6px 2px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            vertical-align: middle;
            line-height: 1.1; /* Jarak antar baris di header */
        }

        /* Styling Baris Data */
        table.data-table tbody td {
            padding: 5px 3px;
            border: 1px solid #e5e7eb; /* Border halus di setiap sel */
            vertical-align: middle;
            font-size: 7pt;
            font-weight: bold;
            font-size: 8pt;
        }

        /* Zebra Striping (Baris Genap) */
        table.data-table tbody tr:nth-child(even) {
            background-color: #f0fdf4;
        }

        /* Typography Helper Classes */
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }
        
        .font-bold   { font-weight: bold; }
        .font-mono   { 
            font-family: 'Courier New', Courier, monospace; 
            letter-spacing: -0.3px; /* Agar angka rapat tapi jelas */
        }

        .text-emerald { color: #047857; }
        .text-red     { color: #dc2626; }
        .text-gray    { color: #4b5563; }

        /* --- KEY FEATURES --- */

        /* 1. Mencegah Angka Terpotong (No Wrap) */
        .nowrap {
            white-space: nowrap;
            overflow: hidden;
        }

        /* 2. Styling Khusus Tanggal Stacked */
        .date-stacked {
            font-size: 6pt;
            line-height: 1.3;
            color: #4b5563;
        }
        .date-separator {
            color: #9ca3af; /* Warna strip lebih muda */
            font-size: 5pt;
            display: block;
            margin: 1px 0;
        }

        /* 3. Pengaturan Lebar Kolom (Total est 100%) */
        .w-no     { width: 3%; }
        .w-pernr  { width: 6%; }
        .w-range  { width: 7%; }
        .w-nama   { width: 14%; }
        .w-wc     { width: 6%; }
        .w-desc   { width: 12%; }
        .w-role   { width: 5%; }
        .w-div    { width: 6%; }
        
        /* Kolom Angka (Dibuat fit agar tidak terlalu lebar tapi cukup) */
        .w-num-sm { width: 5%; } /* Untuk menit/detik */
        .w-num-md { width: 7%; } /* Untuk Rupiah */

    </style>
</head>

<body>

    <div class="header-container">
        <table class="header-table">
            <tr>
                <td style="vertical-align: bottom;">
                    <div class="header-title">Laporan Ringkasan</div>
                    <div class="header-subtitle">Monitoring KPI WC-Person &bull; yppr058_data</div>
                </td>
                <td class="header-meta">
                    <div>
                        <strong>PLANT:</strong> 
                        <span style="font-size: 10pt; color: #059669; font-weight:bold;">{{ $werks }}</span>
                    </div>
                    <div style="margin-top: 3px;">
                        Dicetak: {{ Carbon::now()->isoFormat('D MMM Y, HH:mm') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="w-no">NO</th>
                <th class="w-pernr">PERSONAL<br>NO</th>
                <th class="w-range">RENTANG<br>TANGGAL</th>
                
                <th class="w-nama">NAMA</th>
                
                <th class="w-wc">WC<br>PERSONAL</th>
                <th class="w-desc">DESC<br>WC</th>
                <th class="w-role">ROLE</th>
                <th class="w-div">DEVISI</th>

                <th class="w-num-sm">MENIT<br>HADIR</th>
                <th class="w-num-sm">MENIT<br>CONF</th>
                <th class="w-num-sm">MENIT<br>INSP</th>
                <th class="w-num-sm">DETIK<br>INSP</th>
                <th class="w-num-sm">DETIK<br>KONF</th>
                
                <th class="w-num-md">UPAH<br>HADIR</th>
                <th class="w-num-md">UPAH<br>INSP</th>
                <th class="w-num-md">VAR<br>UPAH</th>
                <th class="w-num-sm">% VAR</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $data)
                @php
                    $minDate = Carbon::createFromFormat('Ymd', $data->min_begda)->format('d/m/y');
                    $maxDate = Carbon::createFromFormat('Ymd', $data->max_begda)->format('d/m/y');

                    // Kalkulasi Persentase
                    $gji2 = (float) $data->gji2;
                    $varnt = (float) $data->varnt;
                    $persenVar = $gji2 != 0.0 ? ($varnt / $gji2) * 100 : 0.0;
                @endphp

                <tr>
                    {{-- NO --}}
                    <td class="text-center text-gray">{{ $i + 1 }}</td>

                    {{-- PERSONAL NO --}}
                    <td class="text-center font-bold text-emerald nowrap">{{ $data->pernr }}</td>

                    {{-- RENTANG TANGGAL (STACKED) --}}
                    <td class="text-center date-stacked">
                        {{ $minDate }}
                        <span class="date-separator">-</span>
                        {{ $maxDate }}
                    </td>

                    {{-- NAMA --}}
                    <td class="text-left font-bold" style="text-transform: capitalize;">
                        {{ strtolower($data->cname) }}
                    </td>

                    {{-- WC PERSONAL --}}
                    <td class="text-center text-gray nowrap" style="font-size: 6pt;">
                        {{ $data->arbpl }}
                    </td>

                    {{-- DESC WC --}}
                    <td class="text-left text-gray" style="font-size: 6pt;">
                        {{ $data->desc }}
                    </td>

                    {{-- ROLE --}}
                    <td class="text-center text-gray" style="font-size: 6pt;">
                        {{ $data->role ?? '-' }}
                    </td>

                    {{-- DEVISI --}}
                    <td class="text-left text-gray" style="font-size: 6pt;">
                        {{ $data->devisi ?? '-' }}
                    </td>

                    {{-- === ANGKA (SEMUA PAKAI CLASS .nowrap) === --}}

                    {{-- MENIT HADIR --}}
                    <td class="text-center font-mono nowrap">
                        {{ number_format($data->total_jam, 1) }}
                    </td>

                    {{-- MENIT CONF --}}
                    <td class="text-center font-mono nowrap">
                        {{ (int) $data->mint2 }}
                    </td>

                    {{-- MENIT INSPECT --}}
                    <td class="text-center font-mono nowrap">
                        {{ (int) $data->mintu }}
                    </td>

                    {{-- DETIK INSPECT --}}
                    <td class="text-center font-mono nowrap">
                        {{ (int) $data->mintu2 }}
                    </td>

                    {{-- DETIK KONF --}}
                    <td class="text-center font-mono nowrap">
                        {{ (int) $data->mintu3 }}
                    </td>

                    {{-- UPAH HADIR --}}
                    <td class="text-right font-mono nowrap">
                        {{ number_format($data->gji, 0, ',', '.') }}
                    </td>

                    {{-- UPAH INSPECT --}}
                    <td class="text-right font-mono nowrap">
                        {{ number_format($data->gji2, 0, ',', '.') }}
                    </td>

                    {{-- VAR UPAH --}}
                    <td class="text-right font-mono nowrap {{ $data->varnt < 0 ? 'text-red' : '' }}">
                        {{ number_format($data->varnt, 0, ',', '.') }}
                    </td>

                    {{-- % VAR --}}
                    <td class="text-center font-mono nowrap">
                        {{ number_format($persenVar, 2) }}%
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- SCRIPT HALAMAN PDF --}}
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