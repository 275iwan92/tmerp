@php
    use Devlib\DateTimeValue;
    use Devlib\DateValue;
    use Devlib\Locale;
    use Devlib\NumberValue;
    use Devlib\TextValue;
    use Devlib\TimeValue;

    $n = NumberValue::of('1234567.5');
    $d = DateValue::of('2026-10-10');
    $dt = DateTimeValue::of('2026-10-10 08:30:00');
    $langs = array_keys(Locale::languages());
@endphp
<div class="dl-doc" data-dl-fmt-demo>
    <h1>Locale & Formats</h1>
    <p>Database &amp; formula selalu English + nilai mentah (angka kanonik <code>1234.5</code>, tanggal ISO); bahasa lain hanya tampilan layar &amp; cetakan (LOCKED 2026-10-01).
        Setiap nilai = <b>satu objek</b> untuk input &amp; tampilan: PHP <code>Devlib\NumberValue · DateValue · TimeValue · DateTimeValue · TextValue</code>,
        browser <code>Devlib.h.DlNumber · DlDate · DlTime · DlDateTime · DlText</code>. Bahasa aktif: <b>{{ Locale::lang() }}</b> · zona waktu {{ Locale::timezone() }} ({{ Locale::tzLabel() }}).</p>

    <table class="dl-kv">
        <tr><td><b>Objek</b></td>@foreach ($langs as $l)<td><b>{{ strtoupper($l) }}</b></td>@endforeach<td><b>Disimpan (DB)</b></td></tr>
        <tr><td>NumberValue · display(2)</td>@foreach ($langs as $l)<td>{{ $n->display(2, $l) }}</td>@endforeach<td><code>{{ $n->canonical }}</code></td></tr>
        <tr><td>NumberValue · trimmed()</td>@foreach ($langs as $l)<td>{{ NumberValue::of('12.50')->trimmed(2, $l) }}</td>@endforeach<td><code>12.50</code></td></tr>
        <tr><td>NumberValue · raw() (diedit)</td>@foreach ($langs as $l)<td>{{ $n->raw($l) }}</td>@endforeach<td><code>{{ $n->canonical }}</code></td></tr>
        <tr><td>Locale::money</td>@foreach ($langs as $l)<td>{{ Locale::money(1250000, 'IDR', 0, $l) }}</td>@endforeach<td><code>1250000.00</code></td></tr>
        <tr><td>DateValue · display()</td>@foreach ($langs as $l)<td>{{ $d->display($l) }}</td>@endforeach<td><code>{{ $d->iso() }}</code></td></tr>
        <tr><td>DateTimeValue · display()</td>@foreach ($langs as $l)<td>{{ $dt->display($l) }}</td>@endforeach<td><code>{{ $dt->iso() }}</code></td></tr>
        <tr><td>TimeValue · of('830')</td>@foreach ($langs as $l)<td>{{ TimeValue::of('830') }}</td>@endforeach<td><code>{{ TimeValue::of('830')->iso() }}</code></td></tr>
        <tr><td>TextValue · of(' kertas  duplex ', 'capitalize')</td>@foreach ($langs as $l)<td>{{ TextValue::of(' kertas  duplex ', 'capitalize') }}</td>@endforeach<td><code>Kertas Duplex</code></td></tr>
        <tr><td>Jam berjalan (browser, DlTime)</td><td colspan="{{ count($langs) + 1 }}" data-fmt="fmt.time()"></td></tr>
    </table>

    <h2>Coba ketik (ala sel Excel)</h2>
    <p>Fokus = nilai mentah, keluar isian = diformat bahasa tampilan; saat form dikirim angka berubah jadi kanonik (<code>data-dl-num</code>).
        Tanggal boleh diketik: <code>10-10-26</code>, <code>10 okt</code>, <code>2026-10-10</code>. Jam: <code>8</code>, <code>830</code>, <code>08.30</code>.</p>
    <x-dl::form.row cols="4">
        <x-dl::field.decimal name="qty" label="Decimal (2)" value="1234.5" />
        <x-dl::field.money name="price" label="Money" value="1250000.00" />
        <x-dl::field.date name="due" label="Date" value="2026-10-10" />
        <x-dl::field.time name="at" label="Time" value="08:30" />
    </x-dl::form.row>
</div>
