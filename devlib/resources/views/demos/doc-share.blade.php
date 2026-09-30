{{-- Print + Email: 1 tag per form/dokumen, tunjuk area dokumennya lewat target. Di tmerp endpoint = /v2/doc-share (config devlib.doc_share_url)
     -> HTML online + QR + Email aktif; di situs DevLib (tanpa database) hanya PDF / JPEG / Printer.
     Bahasa cetak (ID | EN) di menu Print: beda dari bahasa layar -> halaman ini dirender ulang dengan ?print_lang, jadi label
     (kamus Lang), angka (NumberValue) & tanggal (DateValue) di hasil cetak ikut bahasa cetak. --}}
@php
    \Devlib\Lang::extend([
        'dsd_title' => ['Inquiry Rekalkulasi', 'Inquiry Recalculation'], 'dsd_desc' => ['Contoh form: tombol Print ▾ (pilih Bahasa cetak) dan Email di kanan atas', 'Sample form: Print ▾ (choose Print language) and Email at top right'],
        'dsd_product' => ['Produk', 'Product'], 'dsd_customer' => ['Pelanggan', 'Customer'], 'dsd_qty' => ['Qty order', 'Order qty'], 'dsd_due' => ['Tanggal kirim', 'Delivery date'],
        'dsd_date' => ['Tanggal', 'Date'], 'dsd_price' => ['Harga jual /pcs', 'Selling price /pc'], 'dsd_amount' => ['Jumlah', 'Amount'],
    ]);
    $t = fn ($k) => \Devlib\Lang::t($k);
    $rows = collect([['2026-09-23', '10000', '1250.00'], ['2026-06-02', '15000', '1180.50']])->map(fn ($r) => [
        'date' => \Devlib\DateValue::of($r[0])->display(), 'qty' => \Devlib\NumberValue::of($r[1])->display(),
        'price' => \Devlib\Locale::money($r[2], 'IDR', 2), 'amount' => \Devlib\Locale::money((float) $r[1] * (float) $r[2]),
    ])->all();
@endphp
<x-dl::card id="demo-doc" :title="$t('dsd_title').' · IQ-2609-0012'" :description="$t('dsd_desc')">
    <x-dl::action-bar align="between">
        <x-dl::badge label="Draft" />
        <x-dl::doc-share target="#demo-doc" type="demo" doc-id="12" number="IQ-2609-0012" :title="$t('dsd_title')" email-to="purchasing@customer.test" />
    </x-dl::action-bar>
    <x-dl::form.row cols="4">
        <x-dl::field.text :label="$t('dsd_product')" name="product" value="KS Power N120 MF" />
        <x-dl::field.text :label="$t('dsd_customer')" name="customer" value="PT Contoh Baterai" />
        <x-dl::field.decimal :label="$t('dsd_qty')" name="qty" value="20000" :decimals="0" />
        <x-dl::field.date :label="$t('dsd_due')" name="due" value="2026-10-10" />
    </x-dl::form.row>
    <x-dl::table :columns="['date' => $t('dsd_date'), 'qty' => $t('dsd_qty'), 'price' => $t('dsd_price'), 'amount' => $t('dsd_amount')]" :rows="$rows" />
</x-dl::card>
