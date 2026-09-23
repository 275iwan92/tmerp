<div class="dl-doc" data-dl-fmt-demo>
    <h1>Locale & Formats</h1>
    <p>Indonesia, Surabaya. Zona waktu sistem <b>{{ \Devlib\Locale::timezone() }} ({{ \Devlib\Locale::tzLabel() }})</b>, bukan jam komputer pengguna. PHP: <code>Devlib\Locale</code> · browser: <code>Devlib.fmt</code>.</p>
    <table class="dl-kv">
        <tr><td>Tanggal panjang (server)</td><td>{{ \Devlib\Locale::date(null, 'long') }}</td></tr>
        <tr><td>Tanggal pendek (server)</td><td>{{ \Devlib\Locale::date() }}</td></tr>
        <tr><td>Jam (server)</td><td>{{ \Devlib\Locale::time() }}</td></tr>
        <tr><td>Jam berjalan (browser)</td><td data-fmt="fmt.time()"></td></tr>
        <tr><td>Rupiah</td><td>{{ \Devlib\Locale::money(1250000) }}</td></tr>
        <tr><td>Angka desimal</td><td>{{ \Devlib\Locale::number(1234.5, 2) }}</td></tr>
        <tr><td>Persen</td><td>{{ \Devlib\Locale::percent(12.5) }}</td></tr>
        <tr><td>Mata uang lain</td><td>{{ \Devlib\Locale::money(1500, 'USD', 2) }}</td></tr>
    </table>
</div>
