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
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111827;
        }

        body {
            margin: 10px 12px;
            /* sedikit jarak dari tepi kertas */
        }

        .header {
            margin-bottom: 8px;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: #065f46;
        }

        .header-meta {
            margin-top: 3px;
            font-size: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* biar lebar kolom konsisten */
        }

        thead {
            display: table-header-group;
            /* header muncul di setiap halaman */
        }

        tr {
            page-break-inside: avoid;
        }

        th,
        td {
            border: 0.4px solid #d1d5db;
            padding: 2px 3px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        th {
            background-color: #065f46;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
        }

        th.num,
        td.num {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }

        th.center,
        td.center {
            text-align: center;
        }

        tr:nth-child(even) td {
            background-color: #ecfdf5;
        }

        tr:nth-child(odd) td {
            background-color: #ffffff;
        }

        /* Lebar kolom (total ~99%) supaya tidak melebar melewati kertas */
        th.col-no {
            width: 2%;
        }

        /* NO */
        th.col-pernr {
            width: 6%;
        }

        /* PERSONAL NO. */
        th.col-range {
            width: 9%;
        }

        /* RENTANG TANGGAL */

        th.col-menit {
            width: 5%;
        }

        /* MENIT HADIR / MENIT KERJA */
        th.col-total {
            width: 6%;
        }

        /* TOTAL MENIT/DETIK* */

        th.col-nama {
            width: 12%;
        }

        /* NAMA */

        th.col-upah {
            width: 7%;
        }

        /* UPAH* & VARIANT / PROSENTASE */

        th.col-wc {
            width: 5%;
        }

        /* WC PERSONAL / CONFIRMASI */
        th.col-plant {
            width: 4%;
        }

        /* PLANT */
    </style>
</head>

<body>
    <div class="header">
        <div class="header-title">
            Report Data - yppr058_data (Ringkasan per Personal No.)
        </div>
        <div class="header-meta">
            Plant: <strong>{{ $werks }}</strong>
            &nbsp; | &nbsp;
            Dicetak: {{ Carbon::now()->format('d-m-Y H:i') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-no center">NO</th>
                <th class="col-pernr">PERSONAL NO.</th>
                <th class="col-range">RENTANG TANGGAL</th>

                <th class="col-menit num">MENIT HADIR</th>
                <th class="col-menit num">MENIT KERJA</th>
                <th class="col-total num">TOTAL MENIT INSPECT</th>
                <th class="col-total num">TOTAL DETIK INSPECT</th>
                <th class="col-total num">TOTAL DETIK CONFIRMATION</th>

                <th class="col-nama">NAMA</th>

                <th class="col-upah num">UPAH HADIR</th>
                <th class="col-upah num">UPAH INSP</th>
                <th class="col-upah num">VARIANT UPAH</th>
                <th class="col-upah num">PROSENTASE UPAH</th>

                <th class="col-wc">WC PERSONAL</th>
                <th class="col-wc">WC CONFIRMASI</th>
                <th class="col-plant center">PLANT</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($rows as $i => $data)
                <tr>
                    {{-- NO --}}
                    <td class="center">{{ $i + 1 }}</td>

                    {{-- PERSONAL NO --}}
                    <td>{{ $data->pernr }}</td>

                    {{-- RENTANG TANGGAL --}}
                    <td>
                        {{ Carbon::createFromFormat('Ymd', $data->min_begda)->isoFormat('YY-MM-DD') }}
                        -
                        {{ Carbon::createFromFormat('Ymd', $data->max_begda)->isoFormat('YY-MM-DD') }}
                    </td>

                    {{-- MENIT HADIR (total_jam) --}}
                    <td class="num">
                        {{ number_format($data->total_jam, 1) }}
                    </td>

                    {{-- MENIT KERJA --}}
                    <td class="num">
                        {{ (int) $data->mint2 }}
                    </td>

                    {{-- TOTAL MENIT INSPECT --}}
                    <td class="num">
                        {{ (int) $data->mintu }}
                    </td>

                    {{-- TOTAL DETIK INSPECT --}}
                    <td class="num">
                        {{ (int) $data->mintu2 }}
                    </td>

                    {{-- TOTAL DETIK CONFIRMATION --}}
                    <td class="num">
                        {{ (int) $data->mintu3 }}
                    </td>

                    {{-- NAMA --}}
                    <td>{{ $data->cname }}</td>

                    {{-- UPAH HADIR --}}
                    <td class="num">
                        {{ number_format($data->gji, 2) }}
                    </td>

                    {{-- UPAH INSP --}}
                    <td class="num">
                        {{ number_format($data->gji2, 2) }}
                    </td>

                    {{-- VARIANT UPAH --}}
                    <td class="num">
                        {{ number_format($data->varnt, 2) }}
                    </td>

                    {{-- PROSENTASE UPAH --}}
                    <td class="num">
                        {{ number_format($data->varnt1, 2) }}
                    </td>

                    {{-- WC PERSONAL --}}
                    <td>{{ $data->arbpl }}</td>

                    {{-- WC CONFIRMASI --}}
                    <td>{{ $data->arbpl2 }}</td>

                    {{-- PLANT --}}
                    <td class="center">{{ $data->werks }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
