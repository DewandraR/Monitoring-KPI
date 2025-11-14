@php
    use Carbon\Carbon;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>WC Person</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 4px 6px;
        }

        th {
            background: #0b5345;
            color: #fff;
            font-weight: bold;
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>

<body>
    <h2>WC Person — wc_person_data</h2>
    @if (!empty($q))
        <p><strong>Filter:</strong> {{ $q }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIK</th>
                <th>TGL Mulai</th>
                <th>Nama</th>
                <th>Work Center</th>
                <th>Deskripsi Work Center</th>
                <th>Plant</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="text-right">{{ $row->pernr }}</td>
                    <td class="text-center">
                        @if ($row->begda && preg_match('/^\d{8}$/', $row->begda))
                            {{ Carbon::createFromFormat('Ymd', $row->begda)->format('Y-m-d') }}
                        @else
                            {{ $row->begda }}
                        @endif
                    </td>
                    <td>{{ $row->stext }}</td>
                    <td>{{ $row->arbpl }}</td>
                    <td>{{ $row->desc }}</td>
                    <td class="text-right">{{ $row->werks }}</td>
                    <td>{{ $row->role }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
