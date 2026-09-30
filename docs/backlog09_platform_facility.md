# BACKLOG 09 — FASILITAS PLATFORM (CHAT, AI LOKAL, SECURITY USER)

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Fasilitas yang dipakai lintas-modul: chat antar user, AI lokal untuk menjawab kebutuhan pengguna, keamanan & identitas user, dan i18n.

---

## 18. E-MAIL SISTEM — PENGIRIM = E-MAIL USER TERVERIFIKASI + BOUNCE (catatan user 2026-09-29)

Dibangun 2026-09-30 (lihat LOG_JOB_DONE): layar v2 Developer > Setting > Setting E-mail (tm2sys.sys_mail_setting, password
terenkripsi, uji kirim), Profil > E-mail (link verifikasi bertanda tangan -> sec_user.email_verified), From = e-mail user
terverifikasi (saklar), wajib-verifikasi (saklar), gagal kirim langsung -> sys_notifications ke pengirim + status FAILED.
Kedua saklar default MATI sampai SMTP nyata terpasang. Sisa:
- [ ] **Bounce yang datang belakangan** (mailbox penuh / alamat salah setelah diterima server) -> butuh baca kotak masuk
      pengirim (IMAP) atau webhook penyedia SMTP; status `sys_doc_email.status` = BOUNCED belum pernah terisi. Perlu keputusan
      penyedia e-mail perusahaan.
- [ ] Alamat tujuan: pilih dari kontak customer / user (sekarang ketik bebas, format divalidasi).

## 16. CHAT ANTAR USER — LENGKAPI "NEW CHAT" ALA WHATSAPP (instruksi user 2026-09-09)

> **Permintaan user**: "untuk user profile, saya ingin membuat interface nya
> mirip whatsapp jadi awalnya bisa ada nama-nama yang di ajak chat. Karena
> chat ini tujuan nya komunikasi antar pengguna yang saat ini login." —
> **BELUM dikerjakan, ini catatan backlog saja** (user eksplisit minta
> dicatat dulu, bukan dieksekusi sekarang).

**State saat ini** (`resources/views/pages/main-menu.blade.php`, panel chat
sekitar baris 622-707 & 1010-1036, model `App\Models\Sys\SysChat{Conversation,Message,Participant}`,
tabel `tm2sys.sys_chat_*`): panel chat HANYA menampilkan daftar **percakapan
yang sudah ada** (`conversations()` — query `SysChatParticipant` milik user
login, join `SysChatConversation`). **TIDAK ADA cara memulai chat baru** —
kalau `conversations()` kosong, panel cuma menampilkan teks statis "Belum
ada percakapan." tanpa tombol/aksi apa pun. Ini gap-nya: user tidak bisa
memilih nama rekan kerja utk diajak ngobrol pertama kali, persis keluhan di
atas.

**Referensi implementasi SUDAH ADA, tinggal di-adaptasi** —
`docs/mockups/tmerp-chat-module.zip` (paket referensi eksternal, di-drop ke
repo 2026-09-07, SEBAGIAN sudah diintegrasikan jadi kode live di
`main-menu.blade.php` tapi bagian "New Chat"-nya TIDAK ikut terbawa). Isi
zip (`app/Livewire/Chat/ChatDrawer.php`) sudah py fitur yang persis
diminta:
- `public $showNewChatModal` + `public $userSearchQuery` — toggle modal
  pencarian user.
- `availableUsers()` (`#[Computed]`) — `SecUser::where('activate', 1)
  ->where('login', '!=', Auth::user()->login)->where(name/login LIKE
  %query%)->limit(10)` — daftar nama yang bisa diajak chat, filter search,
  MAKSIMAL 10 (pertimbangkan naikkan/tambah pagination kalau user makin
  banyak).
- `startDirectChat($targetLogin)` — cek dulu apakah sudah ada thread 1-on-1
  dgn user itu (`is_group=0` + kedua login jadi participant), kalau ada
  buka thread itu, kalau belum `DB::transaction()` bikin `SysChatConversation`
  baru + 2 baris `SysChatParticipant` (punya sendiri `last_read_at=now()`,
  punya lawan bicara `last_read_at=null`).

**Catatan porting (BUKAN drop-in langsung)**: file referensi pakai
namespace/koneksi model GENERIK (`App\Models\SecUser`,
`App\Models\SysChatConversation`, dst — TANPA `Sys\` + tanpa `$connection`
eksplisit), sedangkan app ini sungguhan pakai `App\Models\Sys\SecUser` +
`App\Models\Sys\SysChat*` dgn `$connection = 'sys'` (lihat CLAUDE.md §2:
default DB `tm2sys`, model Sys/Master/Sdi/Acc). Referensi ini juga
komponen Livewire TERPISAH (`<livewire:chat.chat-drawer>`) sedangkan live
app menyatukan logicnya langsung di `pages::main-menu` (SFC) — porting
harus disesuaikan pola arsitektur project ini (properti `showNewChatModal`/
`userSearchQuery` + method `availableUsers()`/`startDirectChat()` masuk ke
class Livewire yang SUDAH ADA di `main-menu.blade.php`, bukan bikin
komponen Livewire baru terpisah), bukan copy file mentah-mentah.

**Desain UI (instruksi eksplisit)**: mirip WhatsApp — state kosong/awal
panel chat menampilkan **daftar nama pengguna** yang bisa diajak ngobrol
(bukan cuma teks "Belum ada percakapan"), klik satu nama langsung buka/
mulai thread. Cek dulu `resources/views/livewire/chat/chat-drawer.blade.php`
di dalam zip utk contoh markup modal "New Chat"-nya sebelum bikin dari nol.

**[TAMBAHAN instruksi user 2026-09-10] Tema WAJIB ikut pilihan tema user
yang sedang aktif, JANGAN hardcode.** Paket referensi `tmerp-chat-module.zip`
hardcode tema GELAP sendiri (`bg-[#101b30]`/`bg-[#1f2742]`, dst) yang beda
total dari shell utama — ini **TIDAK BOLEH dibawa apa adanya**. Saat porting,
warna drawer/modal chat harus tunduk ke sistem tema yang sudah ada
(`docs/themestandard.md`: `tm2sys.sys_theme`, 4 mode Pastel/Cerah/Dark/Hitam
x 3 corak Biru/Hijau/Merah, dipilih user lewat picker di dropdown avatar) —
sama seperti komponen shell lain (`main-menu.blade.php`), BUKAN palet warna
sendiri milik modul chat. Kalau user sedang pilih mode terang, drawer chat
harus ikut terang; kalau pilih Dark/Hitam, baru ikut gelap — bukan gelap
selalu apa pun tema aktifnya.

**Scope**: chat ini **internal antar pengguna yang sedang login** ke ERP
(karyawan/staff), BUKAN chat dengan pihak luar (customer/portal rekrutmen)
— jangan campur dgn `recruitment/portal` atau modul lain manapun.

**Belum diputuskan**: apakah daftar "New Chat" tampilkan SEMUA `sec_user`
aktif (bisa banyak kalau perusahaan besar) atau dibatasi per-modul/per-role
tertentu — cek dgn user kalau soal ini jadi relevan pas eksekusi.

---

---

## 14. DATA INTEGRITY — AUDIT KETERKAITAN IDENTITAS (sec_user ↔ emp_employee ↔ dst)

> **Instruksi user 2026-09-09**: "masukkan ke pekerjaan backlog bahwa hal
> ini tidak boleh terjadi, tulis apa saja yang harus di-check agar tidak
> terulang". **Dikerjakan (prosesnya) nanti malam** — entry ini HANYA
> menulis rencana pengecekan, BELUM ada eksekusi apa pun.

**Kronologi insiden**: user melapor lewat screenshot Self-Service Karyawan
— header profil masih menampilkan "Budi Santoso" / "Senior Heidelberg
Operator" padahal akun login (`sec_user` id=1, login `johiwan`) baru saja
dibetulkan namanya jadi "Johannes Iwan". Ditemukan bahwa header itu
sumbernya BUKAN `sec_user`, melainkan `emp_employee` (master HR) yang
ditaut lewat `sec_user.employee_id` — dan `employee_id` itu menunjuk ke
`emp_employee` id=1 (TM-00001) yang isinya masih 100% data dummy testing
dari `HrmMasterSeeder.php`. Root cause ganda:
1. **Niat seeder vs kenyataan DB sudah menyimpang tanpa jejak.**
   `HrmMasterSeeder.php:39-42` eksplisit menaut TM-00001 ke login **`admin`**
   (dulu juga kebetulan bernama "Budi Santoso", niatnya cuma sample data
   testing). Tapi di DB live, `admin.employee_id` = NULL dan yang tertaut ke
   TM-00001 justru **`johiwan`** (akun asli user) — perubahan ini tidak ada
   di kode/migration manapun (dicek via `grep`), berarti dilakukan manual
   (tinker/DB langsung) di sesi lampau tanpa dicatat.
2. **Tidak ada mekanisme yang mendeteksi mismatch.** Begitu `sec_user.name`
   dibetulkan tapi `emp_employee.full_name` yang ditaut tidak ikut, tidak
   ada validasi/dashboard/audit apa pun yang akan bunyi — baru ketahuan
   karena user kebetulan buka halaman itu di browser.

**Yang harus DICEK malam ini** (audit dulu, jangan langsung ubah data tanpa
lapor — ikuti pola konfirmasi yang sudah dipakai sesi ini):

1. **Audit semua `sec_user` dengan `employee_id IS NOT NULL`** — bandingkan
   `sec_user.name` vs `emp_employee.full_name` di baris yang ditaut untuk
   TIAP baris (bukan cuma id=1). Laporkan tiap ketidakcocokan nama, jangan
   langsung perbaiki sepihak.
2. **Cek tabel lain yang ikut menyimpan identitas per-`employee_id`**
   (`emp_family`, data attendance/payroll kalau sudah ada isinya untuk
   employee_id=1, dst) — pastikan tidak ada sisa data dummy lain yang
   nyasar ke identitas akun real user selain yang sudah dibetulkan sesi ini
   (email, keluarga, jabatan, NIK, NPWP, telepon, tanggal join).
3. **Cek balik akun dummy `admin`** (id_user=213, `employee_id` masih NULL)
   — sesuai niat asli seeder, apakah dia yang seharusnya ditaut ke TM-00001
   supaya portal self-service dummy tetap ada sample data untuk testing.
   Kalau iya: usulkan tautkan `admin` ke TM-00001 (JANGAN hapus/ubah isi
   TM-00001 — tetap dummy, cuma pindah pemiliknya — sesuai prinsip
   `feedback_keep_dummy_data`).
4. **Cari pola seeder/migration serupa** yang menautkan `employee_id`/
   entity lain berdasarkan ASUMSI (nama kebetulan sama, urutan insert,
   dsb.) tanpa constraint tegas — pola persis yang menyebabkan bug ini
   (lihat komentar `HrmMasterSeeder.php:39-42`). Kalau ada pola serupa di
   modul lain (Payroll `pyr_*`, Attendance `att_*`, Recruitment), tandai
   sebagai fragile, jangan langsung diubah.
5. **Usulkan (jangan langsung bangun — ini keputusan arsitektural, perlu
   konfirmasi user dulu) mekanisme pencegahan jangka panjang**, sampaikan
   opsi & trade-off, contoh kandidat:
   - Artisan command audit (`php artisan data:audit-identity` atau
     sejenis) yang expose mismatch `sec_user.name` vs
     `emp_employee.full_name` untuk semua baris tertaut, bisa dijalankan
     manual/terjadwal.
   - Validasi di layar yang mengubah `employee_id` (Master Employee/
     Security User): tampilkan preview nama employee yang akan ditaut,
     supaya operator sadar kalau salah SEBELUM simpan.
   - Aturan kerja (non-kode): setiap koreksi data identitas manual
     (tinker/DB langsung, di luar migration/seeder) WAJIB cross-check
     SEMUA tabel yang overlap (checklist poin 2 di atas) dalam satu sesi,
     bukan reaktif menunggu user menemukan sendiri per-layar.
6. Tulis hasil audit (temuan mismatch, keputusan `admin`↔TM-00001, opsi
   pencegahan yang dipilih) ke `docs/LOG_JOB_DONE.md`, lalu hapus/tandai
   selesai section ini. **Tidak ada perubahan data/kode tanpa persetujuan
   eksplisit user** untuk temuan apa pun di luar yang sudah dikonfirmasi
   sesi ini (email/keluarga/jabatan/NIK/NPWP/telepon/join_date TM-00001↔
   johiwan, itu sudah selesai & tidak perlu diulang).

---

---
## 13. I18N APP-WIDE -- STATUS & SISA PEKERJAAN

Rollout bertahap "7 malam" (2026-09-07 s/d 2026-09-11) sudah dituntaskan --
riwayat lengkap malam-per-malam + 2 checkpoint (§13.1 status akhir, §13.2
audit infrastruktur) dipindah ke `docs/LOG_JOB_DONE.md` 2026-09-10/11.

**Cakupan saat ini (~15-25% dari total string UI aplikasi)**: shell chrome,
Sales inti (Inquiry Hub/Quotation/CO list), Procurement (5 grid list + 5
entry lengkap), Inventory+Production inti, Finance+HRM inti (grid saja),
Master Data (grid saja). **Mekanisme i18n solid & production-ready** untuk
cakupan yang sudah diterjemahkan -- yang belum "ready" murni soal cakupan
konten, bukan mekanisme bocor.

### Arah (keputusan user 2026-09-30)
- Bahasa **ID dan EN tetap ada** (toggle `lang_code`) — berlaku untuk layar v2.
- **Istilah tombol & status TIDAK diterjemahkan**: selalu English satu kata
  (New/Save/Undo/Delete/Confirm/Cancel/Duplicate/Back to Draft/Back;
  Draft/Confirmed/Cancelled/Closed). Kalau kelak mau istilah Indonesia, dicari
  padanan **satu kata** yang mudah dipahami — bukan terjemahan harfiah.
- Layar v1 tidak lagi ditarik ke i18n (v1 cukup diganti v2); panel Filament
  `/admin` bukan arah ke depan — sisa i18n per-layar v1/Filament dihapus.

### Keputusan user 2026-10-01 -- DB English + kamus, angka/tanggal per bahasa, bahasa cetak
LOCKED (CLAUDE.md §3 "Bahasa: DATABASE & FORMULA = ENGLISH"): database & semua formula English; bahasa lain hanya
tampilan layar & cetakan lewat kamus `tm2sys.sys_dictionary` + `sys_dictionary_lang`. Angka & bulan 3 huruf ikut bahasa
tampilan; setiap cetakan punya pilihan **Bahasa cetak** (bebas dari bahasa layar). Semua nilai lewat OBJEK DevLib
(NumberValue/DlNumber, DateValue/DlDate, TimeValue/DlTime, DateTimeValue/DlDateTime, TextValue/DlText). Desain + audit:
`docs/multilanguage_design.md`.

**SUDAH (2026-10-01):** objek nilai PHP+JS + test (`LocaleNumberTest`, uji keseragaman JS); komponen DevLib (field
money/decimal/date/time/datetime, Lines, Sheet, Grid, Pivot, Print, Approval, NumberWords, Calendar, chart, Slider/Gauge/Kpi/Bullet);
form mengirim angka kanonik; server `num()` = `Locale::input`; layar v2 memakai Locale (number/decimal/d/dt); migration
`2026_10_01_000020_create_sys_dictionary` di **ddtsys saja**; DocShare "Bahasa cetak" (render ulang `?print_lang` di iframe);
teks objek PrintOut & DocShare lewat kamus Lang; demo devlib.test (Locale & Formats, Print ▾); contoh JPEG
`docs/mockups/print_lang_quotation.jpg` (menunggu OK user).

**SISA:**
1. Layar input kamus (Setup > Translation Dictionary: grid sheet source_en × kolom per bahasa, sel kosong disorot,
   Export/Import Excel) -- wajib §7.12, belum dibangun.
2. Setelah contoh JPEG disetujui: label layar/nota v2 yang masih teks Indonesia mentah -> sumber English + kamus, per dokumen:
   Inquiry (zona beku -- label saja, izin sudah ada utk ID->EN), ProDev, Quotation, CO, PR, PO, SPB, DO/Surat Jalan.
   Nota lama v1 yang dipanggil tombol "Nota" (quotation.print, co.print, pur.pr/po.print, dispatch.do.print) belum ikut print_lang.
3. Konversi data master Indonesia di DB ke English + kamus (tabel audit di dokumen desain §0.1) -- perlu rencana per tabel;
   data REAL (prod_process) & zona beku hanya dengan izin user.
4. Migration kamus ke tm2sys (tunggu izin); kamus `Devlib\Lang` (pasangan [ID, EN] di kode) untuk bahasa ke-3 diterjemahkan
   lewat `sys_dictionary` context `ui` (sumber = teks English) -- JS `T()` baru mengenal id/en.
5. Nama hari di pemilih tanggal & Calendar sudah Intl; komponen lain yang masih teks Indonesia (Grid toolbar, dsb) bertahap.
6. `purchase-quotation` (sesi Purchase) masih `number_format(',', '.')` -- dirapikan setelah sesi itu selesai.

### Gap desain lama (format angka sudah diputuskan di atas)
- ~~**Format angka tidak ikut bahasa**~~ (diputuskan 2026-10-01, lihat atas): `number_format()` di ratusan titik
  panggil HARDCODE format Indonesia terlepas `lang_code`. Pertanyaan yang
  perlu dijawab dulu: apakah Rupiah/angka bisnis ERP ini **memang harus
  selalu format Indonesia** (umum di ERP lokal, konsisten dgn faktur pajak)
  atau **ikut toggle bahasa**? Kalau jawabannya "selalu Indonesia", kolom
  `sys_languages.decimal_separator`/`thousand_separator` justru harus
  dihapus/diabaikan, bukan ditutup sbg gap.
- **`sys_languages.is_default` kosmetik**: baris `en` ber-`is_default=1`,
  `id` seharusnya. Cuma memengaruhi urutan tampil di Setup > Language,
  BUKAN default `sec_user.lang_code` (independen, sudah `'id'` di level
  DB). Bisa dibetulkan lewat UI Setup > Language sendiri kapan saja.

