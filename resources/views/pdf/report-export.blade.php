<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h2 { margin-bottom: 4px; }
        .meta { color: #555; font-size: 11px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; font-size: 11px; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <p class="meta">
        Periode: {{ $period['start'] ?? '-' }} s/d {{ $period['end'] ?? '-' }}<br>
        Dicetak: {{ now()->format('d F Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
