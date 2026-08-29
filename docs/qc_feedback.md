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

### Shell (sidebar+topbar) tidak responsive di layar sempit/mobile — 2026-08-30
- **Layar:** Seluruh app (main-menu.blade.php / .shell)
- **Jenis:** Bug
- **Deskripsi:** `.shell` pakai `flex` dgn `aside` lebar tetap 262px, TIDAK ADA `@media` breakpoint sama sekali di `resources/css/app.css` utk layar sempit. Di layar sempit/HP, sidebar makan hampir semua lebar, topbar (termasuk avatar dropdown) kegencet/kedorong nyaris tak kelihatan — user report dropdown avatar tidak bisa dibuka sama sekali di layar sempit. Ditemukan 2026-08-30 waktu user coba fitur Help & Support di layar sempit.
- **Kenapa belum dikerjakan sekarang:** perbaikan yang benar (sidebar jadi off-canvas drawer di mobile, hamburger toggle, dst.) menyentuh shell inti yang dipakai SEMUA layar aplikasi — risiko regresi lebih besar dari sekadar 1 fitur, perlu sesi/scope sendiri, bukan tambahan cepat.
- **Sementara:** pakai jendela browser lebar desktop biasa (bukan HP/window sempit) — semua fitur sesi-sesi sebelumnya juga diasumsikan & diuji di lebar desktop, bukan cuma Help & Support.

---

## Selesai

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
