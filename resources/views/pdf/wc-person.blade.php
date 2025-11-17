{{-- resources/views/pdf/wc-person-list.blade.php --}}
@php
    use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan WC Person</title>
    <style>
        /** GLOBAL & TYPOGRAPHY */
        @page {
            margin: 1cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7pt;
            /* Font kecil agar muat banyak kolom */
            color: #333;
            line-height: 1.3;
        }

        /** HEADER STYLE */
        .header-container {
            border-bottom: 2px solid #059669;
            /* Emerald 600 */
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .header-title {
            font-size: 16pt;
            font-weight: bold;
            color: #064e3b;
            text-transform: uppercase;
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
            vertical-align: bottom;
        }

        /** TABLE STYLE */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* Header Kolom */
        thead th {
            background-color: #065f46;
            /* Emerald 800 */
            color: #fff;
            font-weight: bold;
            font-size: 6.5pt;
            text-transform: uppercase;
            padding: 8px 4px;
            border-bottom: 2px solid #042f2e;
            text-align: left;
            /* Default Left */
        }

        /* Baris Data */
        tbody td {
            padding: 6px 4px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
            word-wrap: break-word;
        }

        /* Zebra Striping */
        tbody tr:nth-child(even) {
            background-color: #f0fdf4;
        }

        /** UTILITY CLASSES */
        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
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

        /* Colors */
        .text-emerald {
            color: #047857;
        }

        .text-gray {
            color: #4b5563;
        }

        .text-amber {
            color: #d97706;
        }

        /* Utk Induk */

        /** COLUMN WIDTHS (Total 100%) */
        .col-no {
            width: 4%;
        }

        .col-nik {
            width: 10%;
        }

        .col-tgl {
            width: 10%;
        }

        .col-nama {
            width: 20%;
        }

        .col-wc {
            width: 10%;
        }

        .col-desc {
            width: 25%;
        }

        .col-plant {
            width: 8%;
        }

        .col-role {
            width: 13%;
        }
    </style>
</head>

<body>
    <div class="header-container">
        <table style="width: 100%">
            <tr>
                <td>
                    <div class="header-title">Laporan WC Person</div>
                    <div class="header-subtitle">Data Personalia Work Center &bull; wc_person_data</div>
                </td>
                <td class="header-meta">
                    <div>Dicetak: {{ Carbon::now()->isoFormat('D MMMM Y, HH:mm') }}</div>
                    @if (!empty($q))
                        <div style="margin-top:2px; font-style:italic;">Filter: "{{ $q }}"</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-no text-center">No</th>
                <th class="col-nik text-center">NIK</th>
                <th class="col-tgl text-center">Tgl Mulai</th>
                <th class="col-nama text-left">Nama Karyawan</th>
                <th class="col-wc text-left">Work Center</th>
                <th class="col-desc text-left">Deskripsi WC</th>
                <th class="col-plant text-center">Plant</th>
                <th class="col-role text-center">Role</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                @php
                    $isInduk = isset($row->role) && strtoupper($row->role) === 'INDUK';
                @endphp
                <tr>
                    {{-- No --}}
                    <td class="text-center text-gray">{{ $i + 1 }}</td>

                    {{-- NIK (Monospace + Bold) --}}
                    <td class="text-center font-mono font-bold text-emerald">{{ $row->pernr }}</td>

                    {{-- TGL Mulai --}}
                    <td class="text-center text-gray">
                        @if ($row->begda && preg_match('/^\d{8}$/', $row->begda))
                            {{ Carbon::createFromFormat('Ymd', $row->begda)->format('d/m/Y') }}
                        @else
                            {{ $row->begda }}
                        @endif
                    </td>

                    {{-- Nama (Capitalize) --}}
                    <td style="text-transform: capitalize;">
                        {{ strtolower($row->stext) }}
                    </td>

                    {{-- Kode WC --}}
                    <td class="text-gray">{{ $row->arbpl }}</td>

                    {{-- Desc WC --}}
                    <td class="text-gray" style="font-size: 6pt;">{{ $row->desc }}</td>

                    {{-- Plant --}}
                    <td class="text-center font-mono">{{ $row->werks }}</td>

                    {{-- Role (Highlight Induk) --}}
                    <td class="text-center">
                        @if ($isInduk)
                            <span class="font-bold text-amber" style="text-transform:uppercase;">INDUK</span>
                        @else
                            <span class="text-gray">{{ $row->role }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Hal {PAGE_NUM} / {PAGE_COUNT}";
            $size = 6;
            $font = $fontMetrics->getFont("Helvetica", "italic");
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $pdf->page_text($pdf->get_width() - $width - 30, $pdf->get_height() - 20, $text, $font, $size, array(0.6, 0.6, 0.6));
        }
    </script>
</body>

</html>
