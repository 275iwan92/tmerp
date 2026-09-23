<div class="dl-doc">
    <h1>Density</h1>
    <p>Kerapatan isian & baris untuk seluruh aplikasi, disimpan per pengguna: <b>Rapat</b> (layar kecil, data banyak), <b>Normal</b>, <b>Longgar</b> (layar sentuh). Pilih di bawah, contoh field langsung ikut.</p>
    <div class="dl-sample">
        <x-dl::theme-picker />
        <div>
            <x-dl::field.text name="a" label="No. Dokumen" value="PO-2609-0001" :case-toggle="false" />
            <x-dl::field.date name="b" label="Tanggal" value="2026-09-23" />
            <x-dl::field.money name="c" label="Nilai" currency="IDR" value="1.250.000" />
        </div>
    </div>
    <x-dl::token-table kind="text" :tokens="['--dl-density' => 'Pengali jarak isi (Rapat 0,7 · Normal 1 · Longgar 1,35)']" />
</div>
