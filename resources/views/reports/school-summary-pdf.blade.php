<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        h2 { margin-top: 24px; }
    </style>
</head>
<body>
    <h1>Laporan Sekolah: {{ $school->name }}</h1>
    <p>Dibuat: {{ now()->format('d-m-Y H:i') }}</p>

    <h2>Ringkasan</h2>
    <table>
        <tr><th>Metrik</th><th>Nilai</th></tr>
        <tr><td>Rata-rata Poin per Siswa</td><td>{{ $data['summary']['average_points'] }}</td></tr>
        <tr><td>Tingkat Partisipasi Hari Ini (%)</td><td>{{ $data['summary']['today_participation_rate'] }}</td></tr>
    </table>

    <h2>Pencapaian per Rombel</h2>
    <table>
        <tr><th>Rombel</th><th>Jumlah Siswa</th><th>Rata-rata Poin</th></tr>
        @foreach ($data['rombels'] as $rombel)
            <tr>
                <td>{{ $rombel['rombel_name'] }}</td>
                <td>{{ $rombel['student_count'] }}</td>
                <td>{{ $rombel['average_points'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Tren Harian (Poin)</h2>
    <table>
        <tr><th>Tanggal</th><th>Total Poin</th></tr>
        @foreach ($data['trend'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['points'] }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>