<div class="dl-doc">
    <h1>Colors</h1>
    <p>Semua warna berasal dari token ini. Ganti nilainya di <code>:root</code> (atau pilih tema) = semua komponen ikut.</p>
    <h2>Brand & aksi</h2>
    <x-dl::token-table :tokens="[
        '--dl-accent' => 'Warna utama: tombol, fokus, tautan, tab aktif',
        '--dl-accent-2' => 'Hover tombol utama',
        '--dl-accent-soft' => 'Latar lembut aksen (hint, pilihan)',
        '--dl-navy' => 'Sidebar, panel gelap, latar login',
        '--dl-topbar' => 'Top bar menu',
    ]" />
    <h2>Permukaan & teks</h2>
    <x-dl::token-table :tokens="[
        '--dl-bg' => 'Latar isi halaman',
        '--dl-surface' => 'Kartu, form, modal',
        '--dl-surface-alt' => 'Kepala tabel, kaki modal',
        '--dl-border' => 'Garis bingkai',
        '--dl-ink' => 'Teks biasa',
        '--dl-ink-strong' => 'Judul & teks penting',
        '--dl-muted' => 'Teks pelengkap',
    ]" />
    <h2>Status</h2>
    <x-dl::token-table :tokens="[
        '--dl-success' => 'Berhasil, aktif, setuju',
        '--dl-danger' => 'Gagal, hapus, tolak',
        '--dl-amber-2' => 'Peringatan, menunggu',
        '--dl-violet' => 'Tunda (hold), asisten AI',
        '--dl-info-soft' => 'Latar pesan info / hint',
    ]" />
</div>
