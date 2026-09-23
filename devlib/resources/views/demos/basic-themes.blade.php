<div class="dl-doc">
    <h1>Themes</h1>
    <p>12 tema (Pastel / Cerah / Dark / Hitam × Biru / Hijau / Merah) = data <code>sys_theme</code> tmerp. Tema hanya menimpa token warna; komponen tidak perlu tahu tema apa yang aktif.</p>
    <div class="dl-sample">
        <x-dl::theme-picker />
        <div>
            <x-dl::field.text name="nama" label="Contoh field" tooltip="Ikut tema" placeholder="Ketik di sini..." />
            <x-dl::field.select name="kota" label="Contoh pilihan" :options="['Surabaya', 'Sidoarjo', 'Malang']" />
            <x-dl::alert tone="info" message="Contoh pesan info mengikuti tema." />
            <x-dl::button label="Tombol utama" />
            <x-dl::button variant="secondary" label="Tombol kedua" />
        </div>
    </div>
</div>
