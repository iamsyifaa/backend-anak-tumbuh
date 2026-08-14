<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .grid { width: 100%; }
        .card {
            display: inline-block;
            width: 260px;
            border: 1px solid #999;
            border-radius: 8px;
            padding: 12px;
            margin: 6px;
            text-align: center;
            page-break-inside: avoid;
        }
        .card .qr { margin-bottom: 8px; }
        .card .name { font-weight: bold; font-size: 14px; margin-bottom: 2px; }
        .card .nisn { color: #555; font-size: 11px; }
        .warning {
            font-size: 10px;
            color: #b91c1c;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <h2>Kartu QR Login Siswa — ANAKTUMBUH.ID</h2>
    <p style="font-size: 11px; color: #555;">
        Dicetak: {{ now()->format('d F Y H:i') }}. Setiap QR hanya berlaku untuk 1 siswa.
        Jika kartu hilang, minta admin revoke &amp; cetak ulang QR baru untuk siswa tersebut.
    </p>

    <div class="grid">
        @foreach ($cards as $card)
            <div class="card">
                <div class="qr">{!! $card['qr_svg'] !!}</div>
                <div class="name">{{ $card['full_name'] }}</div>
                <div class="nisn">NISN: {{ $card['nisn'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
