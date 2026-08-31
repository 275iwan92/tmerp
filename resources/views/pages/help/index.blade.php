<?php

use Livewire\Attributes\Title;
use Livewire\Component;

// Help Center -- panduan pakai aplikasi, BUKAN modal "Help & Support" (itu
// tempat kirim tiket ke tim support, masih ada terpisah di profile dropdown).
// Instruksi user 2026-08-30: "buatkan fasilitas help untuk user yang mudah...
// help dimulai dengan menjelaskan user interface, baik grid dan lainnya...
// lalu juga perihal module." Konten statis (bukan dari DB) -- ini dokumentasi
// kurasi, bukan data bisnis yang perlu diedit runtime lewat UI. Kalau nanti
// user minta konten bisa diedit dari admin, itu keputusan baru (jangan
// dianggap otomatis perlu tabel DB dari awal).
new #[Title('Panduan Penggunaan — TELASIH MAKMUR ERP')] class extends Component
{
    //
};
?>

<div class="min-h-screen bg-[#f4f6fa]" x-data="{ search: '' }">
    <header class="bg-navy text-white sticky top-0 z-30">
        <div class="max-w-[1200px] mx-auto px-5 h-16 flex items-center gap-4">
            <a href="{{ route('menu') }}" class="flex items-center gap-2.5 shrink-0">
                <span class="w-9 h-9 rounded-[10px] flex-shrink-0" style="background: conic-gradient(from 200deg, #ff5f6d, #ffc371, #2f6fed, #8a5cf6, #ff5f6d);"></span>
                <span class="leading-tight hidden sm:block">
                    <b class="block text-[14px] tracking-[.3px] text-white">TELASIH MAKMUR</b>
                    <span class="block text-[9px] tracking-[2px] text-white/60 uppercase">Panduan Penggunaan</span>
                </span>
            </a>
            <div class="flex-1 max-w-[420px] ml-auto">
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/50 text-sm">🔍</span>
                    <input type="text" x-model="search" placeholder="Cari topik bantuan… (mis. &quot;grid&quot;, &quot;drag&quot;, &quot;procurement&quot;)"
                        class="w-full pl-9 pr-3 py-2 rounded-md bg-white/10 border border-white/20 text-white placeholder-white/50 text-[13px] focus:bg-white/15 focus:outline-none">
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-[1200px] mx-auto px-5 py-8 flex gap-8 items-start">
        {{-- ============ TOC (sticky, kiri) ============ --}}
        <nav class="hidden lg:block w-[220px] shrink-0 sticky top-24 text-[13px]">
            <p class="text-[10px] font-bold uppercase tracking-wide text-muted mb-2 px-2">Cara Pakai Aplikasi</p>
            <a href="#ui-navigasi" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Navigasi Umum</a>
            <a href="#ui-grid" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Tabel / Grid</a>
            <a href="#ui-grid-lanjutan" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Fitur Grid Lanjutan</a>
            <a href="#ui-form" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Form Isian</a>
            <a href="#ui-kanban" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Papan Kanban</a>
            <a href="#ui-gantt" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Papan Gantt (Jadwal)</a>
            <a href="#ui-cetak" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Cetak Dokumen</a>
            <a href="#ui-lain" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Notifikasi, Chat &amp; AI</a>
            <p class="text-[10px] font-bold uppercase tracking-wide text-muted mb-2 mt-5 px-2">Modul</p>
            <a href="#mod-sales" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Sales</a>
            <a href="#mod-proc" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Procurement</a>
            <a href="#mod-inv" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Inventory</a>
            <a href="#mod-prod" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Production</a>
            <a href="#mod-hrm" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Human Resources</a>
            <a href="#mod-mdata" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Master Data</a>
            <a href="#mod-finacc" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Finance &amp; Accounting</a>
            <a href="#mod-setup" class="block px-2 py-1.5 rounded-md text-[#495166] hover:bg-white hover:text-navy">Setup &amp; Security</a>
        </nav>

        {{-- ============ KONTEN ============ --}}
        <div class="flex-1 min-w-0 space-y-6">

            <div class="bg-[#eaf2ff] border border-[#c8ddfb] rounded-lg p-4 text-[13px] text-[#3b5b99]">
                👋 Selamat datang! Halaman ini menjelaskan cara pakai tampilan-tampilan umum di aplikasi (tabel, form, papan jadwal, dst) dan ringkasan tiap modul. Kalau butuh bantuan langsung dari tim, pakai menu profil → <b>Help &amp; Support</b> untuk kirim tiket.
            </div>

            <h1 class="text-xl font-extrabold text-navy" id="ui-navigasi-title">1. Cara Pakai Aplikasi</h1>

            {{-- ---- Navigasi Umum ---- --}}
            <section id="ui-navigasi" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                <h2 class="text-[15px] font-bold text-navy mb-3">🧭 Navigasi Umum</h2>
                <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                    <li><b>Bar modul di atas</b> (Sales, Procurement, Inventory, dst) — klik untuk lihat daftar layar di modul itu. Modul yang belum ada isinya akan tampak redup/tidak bisa diklik.</li>
                    <li><b>Sidebar kiri</b> berubah isinya sesuai modul yang dipilih, dikelompokkan per grup. <b>Klik nama grup (mis. "Transaksi") untuk ciutkan/buka</b> — ini tersembunyi kalau tidak dicoba, coba klik langsung nama grupnya, bukan cuma panah kecilnya.</li>
                    <li><b>Tab di atas area kerja</b> — tiap layar yang dibuka jadi tab baru, bisa buka banyak layar sekaligus tanpa kehilangan yang lain. Klik ✕ di tab untuk menutup.</li>
                    <li><b>Ikon profil</b> (kanan atas) — Edit profil, Ubah password, Portal Karyawan, My Security, dan <b>Panduan Penggunaan (halaman ini)</b>.</li>
                    <li><b>Ikon 🔍 pencarian cepat</b> di topbar (atau tekan <kbd>Ctrl</kbd>+<kbd>K</kbd>) — cari nama layar langsung tanpa klik-klik menu satu-satu.</li>
                    <li><b>Tekan <kbd>F1</kbd> kapan saja</b> — langsung buka halaman ini (Panduan Penggunaan) di tab baru, di layar mana pun Anda sedang berada.</li>
                </ul>
            </section>

            {{-- ---- Grid ---- --}}
            <section id="ui-grid" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                <h2 class="text-[15px] font-bold text-navy mb-3">📊 Tabel / Grid (Daftar Data)</h2>
                <p class="text-[13px] text-[#333a4d] mb-3">Hampir semua layar daftar (Customer, Item, Purchase Order, dst) pakai tampilan yang SAMA — begitu paham 1 grid, paham semuanya.</p>
                <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                    <li><b>Kotak angka warna-warni di atas</b> — ringkasan cepat (Total/Aktif/Nonaktif/dst), bukan tombol, cuma info.</li>
                    <li><b>Kotak pencarian</b> — ketik langsung, hasil otomatis update (tak perlu tekan Enter atau klik tombol cari).</li>
                    <li><b>Tombol "+ New Record"</b> (pojok kanan atas tabel) — untuk menambah data baru.</li>
                    <li><b>Klik 2x pada baris</b> data — ini <b>satu-satunya cara buka detail/edit</b> lewat baris itu sendiri (1x klik tidak berbuat apa-apa di banyak layar — ini yang paling sering tidak disadari pengguna baru).</li>
                    <li><b>Ikon-ikon kecil di kolom paling kanan (atau kiri, tergantung layar)</b> — arahkan mouse ke ikon untuk lihat keterangannya (✎ = Edit, ⏻ = Aktifkan/Nonaktifkan, dan ikon lain sesuai layar). Untuk layar dokumen transaksi (PO, CO, dst), ikonnya ↗ Buka dan kadang ✓ Confirm.</li>
                    <li><b>Nomor halaman di bawah tabel</b> — data besar dipecah per halaman supaya tidak berat, bukan semua tampil sekaligus.</li>
                </ul>
            </section>

            {{-- ---- Grid Lanjutan ---- --}}
            <section id="ui-grid-lanjutan" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                <h2 class="text-[15px] font-bold text-navy mb-3">🛠️ Fitur Grid Lanjutan (di beberapa layar tertentu)</h2>
                <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                    <li><b>Panah ▸ di kiri baris</b> (mis. di Customer Order) — klik untuk intip isi dokumen (item/baris) langsung di tempat, tanpa pindah halaman.</li>
                    <li><b>Kotak centang di baris</b> (khusus data yang masih draft) — centang beberapa baris sekaligus untuk hapus massal. <b>Tahan tombol Shift lalu klik baris lain</b> untuk memilih SEMUA baris di antaranya sekaligus — jalan pintas ini gampang terlewat karena tidak kelihatan dari tampilannya.</li>
                    <li><b>Tombol ☰ Kolom / ↕ Sort / ⬇ Export</b> (di beberapa grid dengan banyak kolom) — atur kolom mana yang mau ditampilkan, urutkan data, atau unduh ke Excel/CSV/PDF.</li>
                    <li><b>Toggle "☰ List" / "▦ Card"</b> (khusus Master Employee) — ganti tampilan daftar jadi kartu foto.</li>
                </ul>
            </section>

            {{-- ---- Form ---- --}}
            <section id="ui-form" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                <h2 class="text-[15px] font-bold text-navy mb-3">📝 Form Isian</h2>
                <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                    <li><b>Tanda bintang (*)</b> di label field — wajib diisi, tidak bisa disimpan kalau kosong.</li>
                    <li><b>Kesalahan isian</b> muncul warna merah tepat di bawah field yang bermasalah setelah tombol Simpan diklik.</li>
                    <li><b>Field abu-abu/tidak bisa diketik</b> — biasanya data yang dikunci (mis. dari akun login, atau dokumen yang sudah dikonfirmasi) supaya tidak berubah tanpa sengaja.</li>
                    <li><b>Tombol Batal</b> — kembali ke daftar tanpa menyimpan apa pun.</li>
                </ul>
            </section>

            {{-- ---- Kanban ---- --}}
            <section id="ui-kanban" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                <h2 class="text-[15px] font-bold text-navy mb-3">🗂️ Papan Kanban</h2>
                <p class="text-[13px] text-[#333a4d]">Dipakai di layar seperti Pipeline Board (Recruitment) dan Monitoring Produksi — kartu mewakili 1 dokumen/pekerjaan, kolom mewakili status/tahapannya.</p>
                <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside mt-2">
                    <li><b>Seret (drag) kartu ke kolom lain</b> untuk pindahkan statusnya — klik-tahan kartu, geser ke kolom tujuan, lepas.</li>
                    <li>Kalau perpindahannya tidak diperbolehkan (mis. lompat tahap), sistem akan menolak otomatis — coba lagi lewat tombol aksi di kartu sebagai alternatif kalau drag tidak berhasil.</li>
                </ul>
            </section>

            {{-- ---- Gantt ---- --}}
            <section id="ui-gantt" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                <h2 class="text-[15px] font-bold text-navy mb-3">📅 Papan Gantt (Jadwal Produksi)</h2>
                <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                    <li><b>Seret badan balok jadwal</b> — geser ke kiri/kanan untuk pindah waktu, ke atas/bawah untuk pindah mesin/resource.</li>
                    <li><b>Seret tepi kanan balok</b> — ubah durasi/lama pengerjaan (perbesar/perkecil balok).</li>
                    <li>Highlight otomatis muncul kalau ada jadwal yang bertabrakan lintas mesin.</li>
                </ul>
            </section>

            {{-- ---- Cetak ---- --}}
            <section id="ui-cetak" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                <h2 class="text-[15px] font-bold text-navy mb-3">🖨️ Cetak Dokumen (Nota, Quotation, dll)</h2>
                <p class="text-[13px] text-[#333a4d]">Dokumen seperti Nota Inquiry, Quotation, dan dokumen resmi lain punya tombol "Cetak"/ikon printer di layar detailnya — klik untuk buka tampilan versi cetak, lalu pakai perintah cetak/simpan-PDF bawaan browser (Ctrl+P).</p>
            </section>

            {{-- ---- Lain ---- --}}
            <section id="ui-lain" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                <h2 class="text-[15px] font-bold text-navy mb-3">💬 Notifikasi, Chat &amp; Asisten AI</h2>
                <ul class="space-y-2 text-[13px] text-[#333a4d] list-disc list-inside">
                    <li><b>🔔 Notifikasi</b> — info dari sistem (mis. dokumen butuh approval Anda).</li>
                    <li><b>💬 Chat</b> — pesan internal antar pengguna aplikasi.</li>
                    <li><b>✨ Asisten AI</b> — bantuan cepat berbasis AI di dalam aplikasi.</li>
                    <li>Semua ada di ikon profil/lonceng pojok kanan atas topbar.</li>
                </ul>
            </section>

            <h1 class="text-xl font-extrabold text-navy pt-4" id="mod-title">2. Ringkasan Modul</h1>

            @php
                $modules = [
                    ['id' => 'mod-sales', 'icon' => '💼', 'name' => 'Sales', 'desc' => 'Alur penjualan dari awal sampai kirim barang.', 'flow' => 'Inquiry (spek produk) → Imposition/Costing (harga pokok) → Quotation (penawaran) → Customer Order (CO, order fix) → Rencana Kirim → Delivery Order.', 'note' => 'Tahapan ini berurutan dan saling mengunci — tahap belakang tidak bisa ubah data tahap sebelumnya, kalau ada perubahan spek harus lewat REVISI di Inquiry.'],
                    ['id' => 'mod-proc', 'icon' => '🛒', 'name' => 'Procurement', 'desc' => 'Proses pembelian barang/bahan dari supplier.', 'flow' => 'Purchase Requisition (permintaan beli) → Purchase Order (PO ke supplier) → Purchase Voucher (bukti pembayaran) → penerimaan barang (Purchase Receive/PRT).', 'note' => 'PR & PO punya alur approval sebelum bisa lanjut ke tahap berikutnya.'],
                    ['id' => 'mod-inv', 'icon' => '📦', 'name' => 'Inventory', 'desc' => 'Catatan stok barang (bahan baku, barang jadi, setengah jadi).', 'flow' => 'Stock In (barang masuk), Stock Out (barang keluar), Stock Return (retur), Stock Adjustment (koreksi stok) — semua tercatat di Kartu Stok per item.', 'note' => 'Master Stok bisa difilter per jenis barang (Bahan Baku/Barang Jadi/Setengah Jadi).'],
                    ['id' => 'mod-prod', 'icon' => '🏭', 'name' => 'Production', 'desc' => 'Perencanaan & pemantauan produksi di lantai pabrik.', 'flow' => 'Work Order (WO) dibuat dari Rencana Kirim Sales → dijadwalkan di Papan Gantt/Kanban → dicatat hasil produksinya (Production Output) sampai selesai.', 'note' => '1 Work Order = 1 bagian produk (part) — kalau 1 pesanan punya beberapa part, akan ada beberapa WO terkait.'],
                    ['id' => 'mod-hrm', 'icon' => '👥', 'name' => 'Human Resources', 'desc' => 'Data karyawan sampai rekrutmen & absensi.', 'flow' => 'Master Employee (data karyawan) · Struktur Organisasi · Shift Roster (jadwal shift) · Cuti/Sakit/Izin · Rekrutmen (permintaan tenaga kerja sampai kandidat diterima).', 'note' => 'Ada Portal Karyawan terpisah (ikon profil) untuk karyawan lihat data diri sendiri, beda dari layar HR ini yang untuk HRD.'],
                    ['id' => 'mod-mdata', 'icon' => '🗄️', 'name' => 'Master Data', 'desc' => 'Data induk yang dipakai bersama semua modul lain.', 'flow' => 'Customer, Supplier, Produk, Item Bahan Baku, Satuan (UoM), Mata Uang, Wilayah, dsb.', 'note' => 'Kalau data di sini salah/kurang, akan berdampak ke semua modul yang memakainya (mis. salah alamat Customer akan salah juga di CO & Delivery).'],
                    ['id' => 'mod-finacc', 'icon' => '💰', 'name' => 'Finance & Accounting', 'desc' => 'Pencatatan keuangan & akuntansi perusahaan.', 'flow' => 'Chart of Account (COA) · Jurnal Kas/Bank (BKM/BKK/BBM/BBK) · Jurnal otomatis dari transaksi PV & Stock Out · DP Invoice.', 'note' => 'Sebagian besar jurnal di sini tercipta OTOMATIS dari transaksi modul lain (PV, Stock Out) — tidak perlu diinput manual dobel.'],
                    ['id' => 'mod-setup', 'icon' => '🔒', 'name' => 'Setup & Security', 'desc' => 'Pengaturan akun, hak akses, dan administrasi sistem (khusus admin).', 'flow' => 'Pengguna (buat/kelola akun login) · Grup (kumpulan hak akses) · Hak Akses Grup (izin per modul) · Migrasi Data Legacy.', 'note' => 'Menu ini hanya muncul untuk akun admin — pengguna biasa tidak akan melihat grup Security di sidebar.'],
                ];
            @endphp

            @foreach ($modules as $m)
                <section id="{{ $m['id'] }}" x-show="!search || $el.innerText.toLowerCase().includes(search.toLowerCase())" class="bg-white border border-[#e6e9ef] rounded-xl p-5 scroll-mt-24">
                    <h2 class="text-[15px] font-bold text-navy mb-1">{{ $m['icon'] }} {{ $m['name'] }}</h2>
                    <p class="text-[13px] text-[#333a4d] mb-2">{{ $m['desc'] }}</p>
                    <p class="text-[12.5px] text-muted mb-2"><b class="text-[#495166]">Alur:</b> {{ $m['flow'] }}</p>
                    <p class="text-[11.5px] text-[#8a6d00] bg-[#fef6e7] border border-[#f5e0b3] rounded-md px-3 py-2">💡 {{ $m['note'] }}</p>
                </section>
            @endforeach

            <div class="text-center text-[11.5px] text-muted py-6">
                Tidak menemukan yang dicari? Kirim pertanyaan lewat menu profil → <b>Help &amp; Support</b>.
            </div>
        </div>
    </div>
</div>
