<div class="dl-doc">
    <h1>Typography</h1>
    <p>Satu font untuk semua layar dan satu <b>skala huruf</b> global. Coba ubah <i>Ukuran huruf</i> di halaman Themes: semua teks ikut.</p>
    <x-dl::token-table kind="text" :tokens="[
        '--dl-font' => 'Font seluruh aplikasi',
        '--dl-font-mono' => 'Kode, nomor dokumen teknis',
        '--dl-font-scale' => 'Pengali semua ukuran huruf (1 = ukuran mockup)',
    ]" />
    <h2>Skala ukuran</h2>
    <x-dl::token-table kind="font" :tokens="[
        '--dl-text-2xs' => 'Label kecil, badge',
        '--dl-text-xs' => 'Hint, error, keterangan',
        '--dl-text-sm' => 'Label field, isi tabel',
        '--dl-text-md' => 'Teks tombol & menu',
        '--dl-text-base' => 'Teks isian besar (login)',
        '--dl-text-lg' => 'Judul form',
        '--dl-text-xl' => 'Nama brand',
        '--dl-text-2xl' => 'Judul login, jam',
        '--dl-text-3xl' => 'Salam dashboard',
    ]" />
</div>
