<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1a1a2e; }
        h1 { font-size: 14px; color: #2c3e69; margin-bottom: 2px; }
        .meta { font-size: 9px; color: #667; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f4f5f8; color: #4b5568; text-transform: uppercase; font-size: 8.5px; text-align: left; padding: 5px 6px; border-bottom: 1.5px solid #d7dbe2; }
        td { padding: 4px 6px; border-bottom: 1px solid #eef1f5; }
        tr:nth-child(even) td { background: #fafbfd; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Dicetak {{ now()->translatedFormat('d M Y H:i') }} — {{ \App\Support\Branding\Brand::name() }}
        @if ($truncated ?? false)
            <br><span style="color:#c0392b">⚠ Data dipotong ke {{ number_format($rowLimit) }} baris pertama (PDF tak cocok utk data sangat besar — pakai Excel/CSV utk data lengkap).</span>
        @endif
    </div>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
