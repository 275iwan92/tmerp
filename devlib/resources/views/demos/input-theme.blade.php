<div class="dl-doc">
    <h1>Input · Themes</h1>
    <p>Kode komponen yang sama persis di tiga tema; yang berbeda hanya tema aktif. Garis tepi, badge huruf, dan latar read-only ikut corak tema otomatis. Field read-only tidak diberi badge huruf.</p>
    @foreach (['navy' => 'Cerah Biru (default app)', 'emerald' => 'Cerah Hijau (Emerald)', 'cerah_red' => 'Cerah Merah'] as $theme => $name)
        <div data-dl-theme="{{ $theme }}" class="dl-theme-row">
            <div class="dl-theme-row__head"><span class="dl-theme-row__dot"></span><b>{{ $name }}</b> — garis tepi, badge huruf, dan latar read-only ikut corak tema ini</div>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="customer" label="Nama Customer" value="pt sinar jaya abadi" />
                <x-dl::field.text name="kode" label="Kode Produk" value="BOX-A4-250" code />
                <x-dl::field.lookup name="uom" lookup="uom" label="Satuan" value="PCS" />
                <x-dl::field.text name="no_dok" label="No. Dokumen (read-only)" value="SO-2026-0912" readonly />
            </x-dl::form.row>
        </div>
    @endforeach
</div>
