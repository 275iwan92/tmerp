# qc_feedback.md — Catatan QC & Revisi dari User

> **Cara pakai:**
> 1. Waktu cek aplikasi di browser, kalau nemu bug atau mau revisi, tambah
>    entry baru di bagian **Belum Dikerjakan** — copy blok kosong paling
>    bawah, isi minimal **Layar** & **Deskripsi** (yang lain opsional).
> 2. Tidak perlu isi nomor QC-xxx atau tanggal — Claude yang isi pas mulai
>    kerjakan (biar tidak ada nomor bentrok/duplikat).
> 3. Kasih tahu Claude kapan saja — "cek qc_feedback.md", "kerjakan semua
>    yang belum", atau sebut nomor spesifik ("kerjakan QC-003").
> 4. Setelah beres, Claude yang mindahin entry ke **Selesai** + isi
>    **Perbaikan** & tanggal. User tidak perlu edit manual bagian itu.
> 5. Kalau ternyata BUKAN bug/tidak jadi direvisi (mis. sudah sesuai by
>    design), Claude pindah ke **Ditutup (Bukan Bug)** + kasih alasan,
>    bukan dihapus diam-diam.

---

## Belum Dikerjakan

### Dropdown "Ukuran Pasaran" (Inquiry NEW) — fitur hilang total, bukan sekadar bug UI — 2026-09-03, diperbarui 2026-09-11
- **Layar:** `inquiry/form.blade.php` — dulu di section Tipe Produk per-Part, dropdown "⭐ Ukuran Pasaran" (mis. "Serum 30ml (40x40x110)")
- **Jenis:** Bug → **naik status jadi "fitur hilang", butuh keputusan penempatan, BUKAN lagi bug kecil**
- **Deskripsi awal (2026-09-03):** dropdown balik ke placeholder "— pilih ukuran umum —" setelah user memilih opsi, walau dimensi & dieline sudah ter-update benar. Diduga cuma kurang `@selected(...)`.
- **Temuan ulang 2026-09-11 (dicek ke kode & git log, bukan tebakan):** seluruh blok `<select wire:change="applySizePreset(...)">` (dulu `form.blade.php:~1454`, sudah pakai pola `x-effect` Alpine yang benar sesuai `[[feedback_livewire_select_morph_bug]]`, bukan `@selected` polos — jadi diagnosis awal soal `@selected` sebenarnya keliru) **DIHAPUS TOTAL** saat redesign "Peran Part"/pemindahan Tipe Produk ke level Inquiry (commit `a1a32e5`, 2026-09-04, lihat `[[project_prodtype_multipart_category_system]]`). Method backend `applySizePreset()` + state `size_preset_id` di `iqp_part` masih ada di class Livewire tapi jadi **kode mati** — tidak dipanggil dari markup manapun lagi.
- **Kenapa belum dikerjakan sekarang:** ini bukan lagi bug 1-baris. CLAUDE.md §4 masih mencatat preset "Ukuran Pasaran" sbg bagian goal InqProd Engine yang "SUDAH ADA & DIVERIFIKASI BAGUS, JANGAN DIUBAH" — padahal senyatanya sudah tidak ada UI-nya sejak redesign Peran Part. Perlu keputusan: (a) bangun ulang dropdown ini di lokasi baru (per-Part, setelah gerbang Peran Part, sebelum/sesudah Dimensi) — konsisten dgn catatan CLAUDE.md §4 "Galeri visual popup dieline... sementara dilepas dari scope saat pemindahan ini, belum dibangun ulang di lokasi baru", kemungkinan preset ini nasibnya sama; atau (b) fitur ini memang sengaja tidak dilanjutkan pasca-redesign — perlu konfirmasi user, jangan diasumsikan salah satu.

---

## Selesai

### Shell (sidebar+topbar) tidak responsive di layar sempit/mobile — 2026-09-03
- **Layar:** Seluruh app (main-menu.blade.php / .shell)
- **Jenis:** Bug
- **Deskripsi:** `.shell` pakai `flex` dgn `aside` lebar tetap 262px, TIDAK ADA `@media` breakpoint sama sekali di `resources/css/app.css` utk layar sempit. Di layar sempit/HP, sidebar makan hampir semua lebar, topbar (termasuk avatar dropdown) kegencet/kedorong nyaris tak kelihatan — user report dropdown avatar tidak bisa dibuka sama sekali di layar sempit. Ditemukan 2026-08-30 waktu user coba fitur Help & Support di layar sempit.
- **Perbaikan:** Instruksi user 2026-09-03 "jalankan jika tidak mengubah layout yang sekarang" -- fix di-scope MURNI di dalam `@media (max-width: 767px)` baru (`resources/css/app.css`): `aside` jadi `position: fixed` (overlay di atas konten) alih-alih ikut flex row, `.main` dapat lebar penuh viewport. Reuse toggle hamburger ☰ yang SUDAH ADA (`nosidebar`, `resources/js/app-shell.js`) -- cuma nambah `init()` supaya default TERTUTUP saat load pertama di layar sempit (`window.matchMedia('(max-width: 767px)')`). Tidak ada markup/class baru, tidak ada perubahan JS logic selain nilai awal. Desktop (>=768px) tidak berubah sama sekali (diverifikasi: aturan lama di luar media query baru tidak disentuh). `npm run build` dijalankan, media query terverifikasi masuk ke CSS terkompilasi. Verifikasi visual browser (login) TIDAK dilakukan Claude Code (kredensial tidak boleh diotomatisasi, lihat aturan) -- user disarankan cek langsung di HP/window sempit.
- **Sementara (sebelum fix ini):** pakai jendela browser lebar desktop biasa (bukan HP/window sempit).

### QC-002 — 2026-08-30
- **Layar:** Dropdown profil (topbar) + sidebar kiri (`main-menu.blade.php`)
- **Jenis:** Revisi
- **Deskripsi:** Lanjutan QC-001. Klarifikasi user: (1) "Account Security" bukan panel kanan seperti dugaan awal, tapi item tersendiri di **panel/sidebar kiri, dipasak paling bawah**, hanya utk user `is_admin`; (2) "semua menu di dalam user profile bentuknya pop up dengan kaidah aturan form kecuali security" — popup lebar proporsional tanpa scroll ganda.
- **Perbaikan:**
  - Sidebar kiri (`<aside>`) dapat section baru `.side-footer` dipasak di bawah (flex layout, tak perlu posisi absolut) — isinya 1 tombol "Account Security" (English, gate `@if(Auth::user()->is_admin)`), membuka Setup (`openTab('SET_USER')`) — ini pemindahan+penggantian nama dari trigger "Pengaturan (Setup)" yang tadinya cuma ada di dropdown profil, BUKAN fitur baru.
  - Dropdown "Pengaturan (Setup)" utk user `canSetup()` tapi bukan `is_admin` (mis. user yg cuma dikasih akses modul SETUP) tetap ada di dropdown seperti biasa — supaya tidak kehilangan akses (mereka tak dapat item sidebar baru).
  - "Portal Karyawan" di dropdown profil diubah dari `<a href>` (navigasi keluar shell) jadi tombol pop up (`portalOpen`) — modal lebar (`.modal.wide`, max-width 1100px) berisi `<iframe>` ke `route('self-service.index')`, di-lazy-load (`:src` cuma keisi saat modal dibuka).
  - Link Security (dulu berlabel "Account Security", sekarang di-rename **"My Security"** biar tidak tabrakan nama dgn item sidebar admin di atas) TETAP navigasi biasa `<a href>` ke tab Security Portal Karyawan — sengaja DIKECUALIKAN dari pop up sesuai instruksi user.
  - Diverifikasi via `Livewire::test('pages::main-menu')` sbg user admin (`johiwan`) & user non-admin ber-`employee_id`: sidebar footer cuma muncul utk admin, dropdown Setup hilang utk admin (tak dobel dgn sidebar), trigger popup Portal Karyawan ada, link My Security ada. `npm run build` sudah dijalankan (ada class Tailwind baru: `.side-footer`, `.modal.wide`).
- **Keterbatasan diketahui (belum ditangani, dicatat biar tidak lupa):** `self-service/index.blade.php` (Portal Karyawan) adalah halaman full standalone dgn header sendiri ("← Kembali ke Menu Utama") — saat di-iframe dalam pop up, link itu akan menavigasi ISI IFRAME (bukan menutup pop up). Bukan bug fatal (user tetap bisa tutup pop up lewat tombol ×), tapi kurang mulus. "Aktivitas saya" di dropdown profil TIDAK ikut diubah jadi pop up sesi ini — item itu sebenarnya sudah lama salah-wiring (menunjuk ke `openTab('SET_USER')`/Setup, bukan layar aktivitas sungguhan) dan belum ada layar "Aktivitas Saya" yang nyata dibangun; ini bug lama terpisah, bukan bagian dari revisi QC-002 ini.

### QC-003 — 2026-08-30
- **Layar:** Sidebar kiri, section "Security" (khusus admin)
- **Jenis:** Revisi
- **Deskripsi:** Lanjutan QC-002. User klarifikasi: item "Account Security" seharusnya bukan 1 tombol tunggal, tapi **menu GROUP** bernama "Security" berisi sub-menu: Account, Group, Group per Submodule, User per Group, dan lain-lain.
- **Perbaikan:** `.side-footer` di sidebar kiri diubah dari 1 tombol jadi grup collapsible (pola sama seperti grup modul biasa: `.side-cap-btn` + chevron + daftar `.leaf`), berisi 5 sub-item yang membuka tab `security.index` (atau `setup.support-tickets`) yang SUDAH ADA sebelumnya, cuma dulu digabung jadi 1 pintu masuk (selalu lompat ke tab "users"): **Account** (`SET_USER`, tab Pengguna), **Group** (`SET_GROUP`, tab Grup), **Group per Submodule** (`SET_PERM`, tab "Hak Akses Grup" — memang persis hierarki modul→submodul), **Migration** (`SET_MIGRATION`), **Support Tickets** (`SET_TICKETS`). Diverifikasi via `Livewire::test()` sbg `johiwan` (is_admin): grup "Security" + kelima sub-item render dgn benar. `npm run build` dijalankan.
- **Belum dibuatkan layar terpisah:** "User per Group" — tidak ada layar drill-down khusus yg menampilkan daftar user per grup secara terpisah; info ini sudah tersirat di 2 layar yang ada (tab Account menampilkan grup tiap user, tab Group menampilkan jumlah user tiap grup). Kalau user tetap mau layar drill-down baru (klik nama grup → daftar user anggotanya), perlu diklarifikasi/diminta terpisah.

### QC-001 — 2026-08-29
- **Layar:** Dropdown profil (topbar) vs Portal Karyawan
- **Jenis:** Revisi
- **Deskripsi:** di profile user, menu user profile buat apa ? Karena ada menu portal karyawan. Apa tidak sebaiknya ini di jadikan satu.
- **Perbaikan:** Digabung. Untuk akun yang tertaut data karyawan (`employee_id` terisi): "Edit profil" & "Ubah password" (dulu modal terpisah di dropdown) dihapus, digantikan tab baru **Security** di dalam Portal Karyawan (`self-service.index?tab=security`) — berisi form profil akun (nama/email/telp) + ubah password, tetap edit langsung tanpa approval HR (beda dari tab lain di Portal Karyawan yang lewat pengajuan+approval, karena ini data akun `sec_user` bukan data HR `emp_employee`). Untuk akun TANPA `employee_id` (mis. akun admin teknis yang tak bisa akses Portal Karyawan sama sekali), modal "Edit profil"/"Ubah password" lama tetap ada sebagai fallback satu-satunya jalan.

---

## Ditutup (Bukan Bug / Tidak Jadi)

*(kosong)*

---

## Template kosong (copy blok ini ke bagian "Belum Dikerjakan" di atas)

```
### [Layar/Menu — mis. "HR > Recruitment > Pipeline Board"]
- **Jenis:** Bug / Revisi / Pertanyaan
- **Deskripsi:** ...
- **Langkah reproduksi (kalau bug):** ...
- **Screenshot:** (opsional, taruh path file gambar kalau ada)
- **Prioritas:** Tinggi / Sedang / Rendah (opsional)
```
