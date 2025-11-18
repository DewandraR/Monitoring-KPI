@php
    use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Report Detail - yppr058_data</title>
    <style>
        @page {
            margin: 1cm 1cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7pt;
            color: #374151;
            line-height: 1.3;
        }

        .header-container {
            width: 100%;
            margin-bottom: 16px;
            border-bottom: 2px solid #059669;
            padding-bottom: 6px;
        }

        .header-title {
            font-size: 14pt;
            font-weight: bold;
            color: #064e3b;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .header-subtitle {
            font-size: 8pt;
            color: #059669;
            margin-top: 2px;
        }

        .header-meta {
            text-align: right;
            font-size: 8pt;
            color: #6b7280;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.data-table thead th {
            background-color: #065f46;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
            font-size: 6.5pt;
            text-transform: uppercase;
            padding: 6px 3px;
            border-bottom: 2px solid #064e3b;
            vertical-align: middle;
        }

        table.data-table tbody td {
            padding: 4px 3px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
            word-wrap: break-word;
            font-size: 6.5pt;
        }

        table.data-table tbody tr:nth-child(even) {
            background-color: #f0fdf4;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .font-mono {
            font-family: 'Courier New', Courier, monospace;
        }

        .font-bold {
            font-weight: bold;
        }

        .text-red {
            color: #dc2626;
        }

        .col-no {
            width: 4%;
        }

        .col-tgl {
            width: 8%;
        }

        .col-wc {
            width: 7%;
        }

        .col-desc {
            width: 22%;
        }

        .col-men {
            width: 7%;
        }

        .col-var {
            width: 8%;
        }

        .col-plant {
            width: 5%;
        }

        .group-header-row td {
            background-color: #e5e7eb;
            border-top: 1px solid #059669;
            border-bottom: 1px solid #059669;
            padding: 5px 6px;
            font-size: 7pt;
        }

        .group-header-label {
            font-weight: bold;
            color: #065f46;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .group-header-value {
            font-weight: bold;
            color: #111827;
        }
    </style>
</head>

<body>
    <div class="header-container">
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:bottom;">
                    <div class="header-title">Laporan Detail</div>
                    <div class="header-subtitle">Sistem Personalia WC-Person &bull; yppr058_data</div>
                </td>
                <td class="header-meta">
                    <div>
                        <strong>PLANT:</strong>
                        <span style="font-size:10pt; color:#059669;">{{ $werks }}</span>
                    </div>
                    <div>Dicetak: {{ Carbon::now()->isoFormat('D MMMM Y, HH:mm') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-tgl">Tanggal</th>
                <th class="col-wc">WC<br>Pers</th>
                <th class="col-desc text-left">DESC WC</th>

                <th class="col-men">Menit<br>Hadir</th>
                <th class="col-men">Menit<br>Conf</th>
                <th class="col-men">Menit<br>Inspect</th>
                <th class="col-var">%<br>Upah</th>
                <th class="col-var">Var<br>Upah</th>

                <th class="col-wc">WC<br>Conf</th>
                <th class="col-plant">Plant</th>
                <th class="col-plant">Shift</th>
            </tr>
        </thead>
        <tbody>
            @php
                // Asumsikan $rows sudah di-sort pernr, begda (dari controller)
                $currentKey = null;
                $rowNumberPerPerson = 0;
            @endphp

            @foreach ($rows as $d)
                @php
                    $groupKey = $d->pernr . '|' . strtolower($d->cname);
                @endphp

                {{-- Jika NIK+Nama berubah -> buat header grup baru --}}
                @if ($groupKey !== $currentKey)
                    @php
                        $currentKey = $groupKey;
                        $rowNumberPerPerson = 0;
                    @endphp
                    <tr class="group-header-row">
                        <td colspan="12">
                            <span class="group-header-label">Personal:</span>
                            <span class="group-header-value">{{ $d->pernr }}</span>
                            &mdash;
                            <span style="text-transform:capitalize;">{{ strtolower($d->cname) }}</span>
                        </td>
                    </tr>
                @endif

                @php
                    $rowNumberPerPerson++;
                @endphp

                <tr>
                    {{-- No per NIK --}}
                    <td class="text-center">{{ $rowNumberPerPerson }}</td>

                    {{-- Tanggal --}}
                    <td class="text-center">
                        {{ Carbon::createFromFormat('Ymd', $d->begda)->format('d/m/y') }}
                    </td>

                    {{-- WC & DESC --}}
                    <td class="text-center">{{ $d->arbpl }}</td>
                    <td class="text-left">{{ $d->desc }}</td>

                    {{-- Menit --}}
                    <td class="text-center font-mono">{{ number_format($d->total_jam, 1) }}</td>
                    <td class="text-center font-mono">{{ (int) $d->mintu3 }}</td>
                    <td class="text-center font-mono">{{ (int) $d->mintu }}</td>

                    {{-- Persentase & Var Upah --}}
                    <td class="text-center font-mono">
                        {{ number_format($d->varnt1, 2) }}%
                    </td>
                    <td class="text-center font-mono {{ $d->varnt < 0 ? 'text-red' : '' }}">
                        {{ number_format($d->varnt, 0, ',', '.') }}
                    </td>

                    {{-- WC Confirm, Plant, Shift --}}
                    <td class="text-center">{{ $d->arbpl2 }}</td>
                    <td class="text-center font-bold">{{ $d->werks }}</td>
                    <td class="text-center font-mono">
                        {{ is_null($d->shift) ? '-' : (int) $d->shift }}
                    </td>
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
                [0.6, 0.6, 0.6]
            );
        }
    </script>
</body>

</html>
