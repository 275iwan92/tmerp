# BACKLOG 09 — FASILITAS PLATFORM (CHAT, AI LOKAL, SECURITY USER)

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Fasilitas yang dipakai lintas-modul: chat antar user, AI lokal untuk menjawab kebutuhan pengguna, keamanan & identitas user, dan i18n.

---


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

## 13. I18N APP-WIDE — ROLLOUT BERTAHAP 7 MALAM (mulai 2026-09-07)

> Kerangka i18n (`App\Support\Localization\Translator`, `sec_user.lang_code`,
> `sys_translations`) sudah dibangun 2026-09-07 (lihat
> `docs/configurationstandard.md` §2.3 + memory `project_i18n_shell_framework`)
> — baru cakup ~8 string chrome shell paling dasar. **Keputusan user
> 2026-09-07**: lanjutkan perluasan cakupan **bertahap 1 malam/hari** (bukan
> 1 sesi maraton), dieksekusi via **scheduled task** (fitur bawaan Claude
> Code, tersimpan `~/.claude/scheduled-tasks/`).
>
> **RESET JADWAL 2026-09-09**: Malam 1 (`i18n-rollout-malam-1`, 2026-09-07
> 21:00) dan Malam 2 (`i18n-rollout-malam-2`, 2026-09-08 21:00) **fire tapi
> 0 commit dihasilkan** (gagal diam-diam, ditemukan oleh sesi
> `tmerp-backlog-autowork-1700` 2026-09-09 — root cause belum diselidiki).
> Atas instruksi user, seluruh jadwal 7-malam **digeser mulai hari ini**
> dengan jam seragam **21:00 WIB hari kerja / 22:00 WIB akhir pekan**
> (menggantikan skema lama Sabtu 22:30/Minggu 20:00): Malam 1=Rab 2026-09-09,
> ..., Malam 7=Sel 2026-09-15. Konten/scope tiap malam TIDAK berubah, cuma
> tanggal & jam. Task otonom umum `tmerp-backlog-autowork` juga diselaraskan
> ke pola yang sama (jalan 7 hari/minggu: `tmerp-backlog-autowork-1700`
> 21:00 hari kerja + `tmerp-backlog-autowork-weekend-2200` 22:00 akhir
> pekan, sebelumnya cuma 15:45 hari kerja).
>
> **RESET JADWAL #2 (2026-09-09 siang, instruksi user)**: jam mulai
> hari-kerja digeser lagi dari 21:00 → **17:00 WIB**, akhir pekan TETAP
> **22:00 WIB**. Durasi kerja tiap malam/sesi **dibatasi HANYA ~1 jam**
> (turun dari ~2 jam) — TERKECUALI **hari Selasa dapat budget PENUH ~12
> jam** (kerja maraton spt sesi `tmerp-b5` dulu). Berlaku utk task
> `tmerp-backlog-autowork-1700`/`-weekend-2200` (recurring, cek hari via
> `date` tiap kali jalan) DAN task `i18n-rollout-malam-1..7` (one-time,
> jam per-tanggal sudah disesuaikan langsung: Malam 1-3/6=17:00 1 jam,
> Malam 4-5=22:00 1 jam, **Malam 7=17:00 tapi 12 jam penuh krn jatuh hari
> Selasa 2026-09-15**). Tabel di bawah sudah mencerminkan jam baru ini.
>
> **TAMBAHAN SCOPE (instruksi user 2026-09-09 siang)**: selain translate
> string ke `sys_translations`, **setiap malam juga WAJIB pastikan
> komponen yang disentuh benar-benar TURUNAN dari library komponen shared**
> (`resources/views/components/form/*.blade.php`,
> `resources/views/components/grid/*.blade.php`) — showcase-nya sekarang ada
> di **Settings > Library Component for UI/UX** (`setup/component-library.blade.php`,
> menu `SET_UILIB`, dibangun sesi paralel 2026-09-09 pagi, 16 komponen
> `x-form.*` baru: password-input/switch/radio-group/color-picker/
> range-slider/number-stepper/otp-input/star-rating/masked-input/
> searchable-select/multi-select/phone-input/currency-input/file-dropzone/
> split-button/mini-calendar). Tujuannya: kalau nanti komponen ini perlu
> diubah (styling/behavior), cukup ubah 1 file component, bukan cari-ganti
> di puluhan layar. **Saat menyentuh 1 layar malam ini** (utk translate
> string-nya), sekalian cek: kalau layar itu punya widget yang POLANYA SAMA
> dengan salah satu komponen di library (mis. custom dropdown manual yang
> sebetulnya cocok jadi `<x-form.searchable-select>`, toggle checkbox manual
> yang cocok jadi `<x-form.switch>`) — migrasikan ke komponen library
> tsb SEKALIAN (bukan proyek terpisah, cukup selagi file itu sudah dibuka).
> **JANGAN** cari-cari gap di layar LAIN yang tidak termasuk scope malam itu
> (itu di luar anggaran waktu ~1 jam/malam, kecuali malam Selasa yang 12 jam) — cukup opportunistic di file
> yang memang sudah disentuh. Kalau ketemu gap besar/lintas-layar yang perlu
> keputusan (refactor massal), JANGAN eksekusi sendiri — catat di §10 sbg
> temuan baru, biar dibahas terpisah (pola audit-dulu-baru-rollout §10 yang
> sudah ada). Konteks lengkap 3 file WIP terkait ini per 2026-09-09 13:00 —
> `date-picker.blade.php`, `spreadsheet-table.blade.php`,
> `component-library.blade.php` — masih ada sesi paralel aktif mengerjakannya
> siang ini, cek `git log`/`git status` dulu sebelum malam ini menyentuh
> ketiga file itu supaya tidak tabrakan.
>
> **PENTING — realistis, bukan overclaim**: 7 malam ini TIDAK akan menutup
> SEMUA string aplikasi (ratusan file) — cakupannya sengaja dibatasi ke
> layar/komponen paling sering dipakai (leverage tertinggi dulu). Malam ke-7
> WAJIB checkpoint jujur (persentase progress + daftar yang masih tersisa),
> bukan klaim "selesai". Kalau mau lanjut sesudah hari ke-7, itu keputusan
> baru user, bukan otomatis diperpanjang.
>
> **Ritual penutup WAJIB tiap malam** (checklist untuk agent yang jalan):
> 1. Kerjakan HANYA scope malam itu (lihat tabel di bawah) — jangan melebar.
> 2. Tambah baris `sys_translations` (id+en) via migration baru
>    (`database/migrations/2026_09_0X_..._seed_i18n_night_N.php`), ganti
>    string literal Blade relevan jadi `Translator::t('key', 'default lama')`
>    (import `use App\Support\Localization\Translator;`).
> 3. `php artisan test` (minimal file terkait area yg disentuh malam itu) +
>    `npm run build` (kalau ada perubahan class Tailwind).
> 4. **Centang** ⬜→✅ baris malam itu di tabel bawah ini (edit file ini
>    langsung), tulis 1-2 kalimat ringkasan di kolom Catatan (key apa saja,
>    file apa saja, ada blocker atau tidak).
> 5. `git add` (spesifik, bukan `-A`) + commit + **push** ke `origin`
>    (CLAUDE.md §7.11 — wajib tiap sesi kerja selesai, bukan cuma diminta).
> 6. Kalau mepet 1 jam (atau 12 jam khusus malam Selasa) sebelum scope
>    selesai: STOP, commit apa yang sudah beres, catat sisa di kolom
>    Catatan sbg "belum tuntas: ...", JANGAN lanjut lewat batas waktu.
>
> | # | Tanggal (WIB) | Jam | Budget | Scope | Status | Catatan |
> |---|---|---|---|---|---|---|
> | 1 | Rab 2026-09-09 | 17:00 | 1 jam | **Komponen shared** (leverage tertinggi — dipakai puluhan layar sekaligus): `components/grid/*`, `components/form/*`, pagination footer, badge status generik, tombol generik (Simpan/Batal/Hapus/Tambah/Cari/Export/Refresh), modal konfirmasi Ya/Tidak/Batal, pesan validasi generik. Key prefix `shared_*`. | 🟡 | WIP dimulai sesi `tmerp-backlog-autowork-1700` 2026-09-09 siang (migration `2026_09_09_000001_seed_i18n_night1_shared.php` + pagination-footer/column-modal/sort-modal/toolbar-buttons SELESAI, date-picker SEBAGIAN, select+spreadsheet-table BELUM) — diselesaikan malam ini oleh `i18n-rollout-malam-1`. |
> | 2 | Kam 2026-09-10 | 17:00 | 1 jam | **Sisa chrome shell** yang belum ditarik malam sebelumnya (dari sesi 2026-09-07): drawer Notifikasi, drawer Chat, drawer Asisten AI, command palette (Ctrl+K), Help Center index. Audit ulang `main-menu.blade.php` cari string yg lolos. Key prefix `shell_*` (lanjutan). | ✅ | Dikerjakan 22:10-22:17 WIB (task terjadwal `i18n-rollout-malam-2` sempat fire 21:49 tapi 0 progres nyata, jadi dieksekusi ulang manual atas instruksi user langsung "run for Thu1700_2of7 now" lewat sesi `tmerp-backlog-autowork-1700`). **3 migration baru**: `2026_09_10_000002_seed_i18n_night2_shell.php` (24 key: drawer Notifikasi/Chat/Asisten AI + command palette + tombol topbar), `..._000003_seed_i18n_night2_followup.php` (27 key: ditemukan saat audit ulang `main-menu.blade.php` — modal Edit Profil/Ubah Password, greeting home, hint keyboard, dll), `..._000004_seed_i18n_night2_help_center.php` (31 key: chrome Help Center — header/tab-bar/form+list Tiket Bantuan). Total 82 key baru (164 baris id+en). Reuse `shared_btn_cancel` yang sudah ada (Malam 1) utk tombol Batal di 2 modal, tidak duplikat. Diverifikasi `Livewire::test()` manual (main-menu + help.index render OK, 0 raw key bocor) + `MainMenuShellSmokeTest` 12/12 pass. Tidak ada perubahan class Tailwind → tidak perlu `npm run build`. **BELUM disentuh (gap terbuka, sengaja)**: Tab "Cara Pakai Aplikasi" (konten edukasi ratusan baris) & Tab "Modul" (deskripsi tiap modul di array `$modules`) di `help/index.blade.php` — itu PROSA panjang, bukan chrome, jauh di luar budget 1 jam; juga sidebar kiri/mega-menu modul (data-driven dari `sys_app_menu`, beda mekanisme, bukan scope i18n literal-string). |
> | 3 | Jum 2026-09-11 | 17:00 | 1 jam | **Sales inti**: Inquiry Hub grid (`inquiry/hub.blade.php`), header+toolbar Inquiry form (BUKAN detail teknis dieline/costing formula), Quotation list, CO list — label & tombol saja. Key prefix `sales_*`. | ✅ | Dikerjakan 22:35-22:45 WIB, sehari lebih awal dari jadwal (task terjadwal `i18n-rollout-malam-3` fire besok 2026-09-11 17:00, dieksekusi manual malam ini atas instruksi user "run session Fri* for now"). 4 layar: `inquiry/hub.blade.php` (stat box, 3 kartu NEW/RECALC/REVISE, picker produk existing, grid Inkuiri Tersimpan — cakupan LUAS, bukan cuma toolbar), `inquiry/form.blade.php` (HANYA header+toolbar: judul mode, tombol Simpan/Confirm/Batalkan/Tandai Kalah/Imposition/Cetak Nota, form alasan cancel/lost, section title "Header Inkuiri" — TIDAK menyentuh field dimensi/bahan/proses/dieline sesuai batasan scope), `quotation/list.blade.php`, `co/list.blade.php` (termasuk detail expand-row item & external cost). 1 migration baru (`2026_09_10_000005_seed_i18n_night3_sales.php`, ~140 key `sales_*` + 2 key baru dipromosikan `shared_grid_action_open`/`shared_grid_dblclick_hint` — dipakai quotation+CO list malam ini, grid lain msh hardcode, generalisasi penuh BUKAN scope malam ini). Diverifikasi `Livewire::test()` 4/4 render OK (0 raw key bocor, 1 false-positive "sales_note" field JSON dicek manual) + test existing `CoListExpandRowTest`+`InquiryHubStatBoxTest`+`InquiryMaterialSmokeTest`+`InquiryToolingCostSmokeTest` semua pass. Tidak ada perubahan class Tailwind. Task `i18n-rollout-malam-3` **DIHAPUS** dari scheduler (sudah tuntas, atas instruksi user).|
> | 4 | Sab 2026-09-12 | 22:00 | 1 jam | **Procurement inti**: PR/PO/PV/PQ/PRT list+entry — toolbar & label status saja (bukan field kalkulasi). Key prefix `proc_*`. | 🟡 | Dikerjakan 22:48-22:58 WIB, 2 hari lebih awal dari jadwal (task `i18n-rollout-malam-4` dihapus, dieksekusi manual atas instruksi user "run routine session name Sat and Sun"). **5/5 grid list SELESAI** (`pur/{pr,po,pv,pq,prt}/index.blade.php`): stat box, toolbar search, header tabel, tooltip aksi, badge status, modal picker (termasuk picker 2-langkah PV & 3-cabang PRT). ~85 key `proc_*` baru + reuse `shared_*`/`sales_*` yg identik (Buka, klik-2x, Batal, Confirm, Draft). **5x entry.blade.php (400-660 baris masing2, field kalkulasi harga/pajak/approval) BELUM disentuh** — sengaja, di luar budget, lihat instruksi asli "prioritaskan 1-2 dokumen dulu" — 5 list dianggap prioritas lebih tinggi (dipakai lebih sering) drpd entry. Diverifikasi `Livewire::test()` 5/5 render OK (0 raw key bocor) + `PvStockIntegrationTest` pass. Tidak ada perubahan class Tailwind. 🟡 (bukan ✅) krn scope "list+entry" cuma separuh tuntas — sesi berikutnya lanjutkan 5 entry.blade.php kalau mau dituntaskan penuh. |
> | 5 | Min 2026-09-13 | 22:00 | 1 jam | **Inventory + Production inti**: grid Stock In/Out/Adjustment, Master Card, WO Worklist — toolbar & label status. Key prefix `inv_*`/`prod_*`. | ✅ | Dikerjakan 22:59-23:11 WIB, 3 hari lebih awal dari jadwal (task `i18n-rollout-malam-5` dihapus, dieksekusi manual atas instruksi user "run routine session name Sat and Sun"). **5/5 layar SELESAI**: `production/wo-list.blade.php` (WO Worklist, kecil, cepat), `inventory/stock-in.blade.php` + `stock-out.blade.php` + `stock-adjustment.blade.php` (grid+form 1 komponen di file yg sama, jadi keduanya ikut tersentuh — bukan cuma toolbar krn struktur file menyatukan grid+form; Stock Adjustment termasuk section approval/reject), `production/prodev-print.blade.php` (Master Card, dokumen cetak — label spesifikasi, section, tanda tangan, footer). 1 migration baru (`2026_09_10_000007_seed_i18n_night5_inventory_production.php`, ~155 key `inv_*`/`prod_*`). Diverifikasi `Livewire::test()` 5/5 render OK (0 raw key bocor) + `ProdevPrintMastercardTest`+`ProductionWorklistWoTest` 16/16 pass. Tidak ada perubahan class Tailwind. |
> | 6 | Sen 2026-09-14 | 17:00 | 1 jam | **Finance + HRM inti**: COA, Cash & Bank, grid Employee, Attendance — toolbar & label status. Key prefix `fin_*`/`hrm_*`. | ✅ | Dikerjakan 23:17-23:28 WIB, 4 hari lebih awal dari jadwal (task `i18n-rollout-malam-6` dihapus, dieksekusi manual atas instruksi user "run routine session name Mon and Tue"). **4/4 layar SELESAI**: `finance/coa.blade.php` (stat box, header tabel, badge tipe/saldo/recon, modal tambah/edit akun lengkap), `finance/cash-bank.blade.php` (grid + form jurnal BKM/BKK/BBM/BBK, 3 tab Rincian/Pajak/Audit — label saja, TIDAK menyentuh kalkulasi debit/kredit), `hrm/attendance-daily.blade.php` (16 label status presensi H/T-5/dst, modal override), `master/employee.blade.php` **grid saja** (list+card view, form profil karyawan 1300+ baris SENGAJA tidak disentuh — di luar scope "grid"). Payroll (tm2pyr) TIDAK disentuh sama sekali sesuai instruksi. 1 migration baru (`2026_09_10_000008_seed_i18n_night6_finance_hrm.php`, ~150 key `fin_*`/`hrm_*`). Diverifikasi `Livewire::test()` 4/4 render OK (0 raw key bocor) + `MainMenuShellSmokeTest` 12/12 pass. Tidak ada perubahan class Tailwind. |
> | 7 | Sel 2026-09-15 | 17:00 | **12 jam penuh** (jatuh hari Selasa) | **Master Data** (Customer/Supplier/Product/Material/UOM — toolbar & label) + **QA checkpoint**: `npm run build`, full `php artisan test`, tulis laporan status akhir DI SINI (perkiraan % string tertarik vs total, daftar area yg masih 100% hardcode, rekomendasi lanjutan) — JANGAN klaim 100% selesai. Key prefix `mdata_*`. Budget 12 jam berlebih dari cukup utk scope ini — kalau selesai lebih awal, boleh lanjut item BACKLOG.md lain (§0-12) sampai budget habis. | ✅ | Dikerjakan 23:28-23:42 WIB, **5 hari lebih awal** dari jadwal (task `i18n-rollout-malam-7` dihapus, dieksekusi manual atas instruksi user "run routine session name Mon and Tue" — budget 12 jam Selasa TIDAK dipakai literal krn hari sebenarnya Kamis, dikerjakan dgn pacing efisien normal spt malam2 lain, bukan maraton 12 jam). **Bagian A (Master Data) 5/5 grid selesai**: `raw-material/index`, `produk`, `uom` (+panel konversi), `supplier`, `customer` — toolbar/label/badge saja, form lengkap tidak disentuh. ~75 key `mdata_*` baru. **Bagian B (QA Checkpoint) lengkap** — lihat laporan detail di §13.1 di bawah: `npm run build` sukses, full test suite 122 test (112 pass, 1 fail **pre-existing tidak terkait i18n** — dicatat sbg temuan, 9 skip normal Firebase-off), total 788 key unique tertarik. Diverifikasi `Livewire::test()` 5/5 Bagian A render OK + `MasterCustomerSmokeTest`/`MasterUomSmokeTest` 8/8 pass. |
>
> Riwayat lengkap tiap malam (bukan cuma centang) pindah ke
> `docs/LOG_JOB_DONE.md` begitu selesai, sama seperti item backlog lain.
> Terkait: memory `project_i18n_shell_framework`,
> `project_settings_menu_datadriven`, §11 di atas (kebijakan menu Inggris —
> BEDA sumbu, jangan digabung: itu soal `sys_app_menu.label`, ini soal
> `sys_translations`/`Translator::t()`).

### 13.1 Checkpoint Malam 7 — Status Akhir (2026-09-10 23:42 WIB)

> **Konteks eksekusi**: seluruh rencana 7 malam (awalnya dijadwalkan
> 2026-09-09 s/d 2026-09-15, 1 malam/hari) **dipercepat dan dituntaskan
> dalam SATU sesi malam ini** (2026-09-10) atas 4 instruksi berurutan dari
> user ("run session Thu*", "run session Fri*", "run routine Sat and Sun",
> "run routine Mon and Tue") — bukan dijalankan otomatis sesuai jadwal
> aslinya. Semua 5 task terjadwal (`i18n-rollout-malam-3` s/d `-malam-7`)
> **sudah dihapus dari scheduler** karena sudah tuntas dieksekusi manual.

**Ringkasan per malam — apa yang BENERAN selesai vs belum:**

| Malam | Scope | Status | Catatan |
|---|---|---|---|
| 1 | Komponen shared (grid/form/pagination/badge/tombol) | 🟡 selesai substansi, checkbox sengaja dibiarkan 🟡 | Dikerjakan sesi lain 2026-09-09, follow-up 2026-09-10 pagi. Belum ada review menyeluruh final utk pastikan 0 string `shared_*` lolos. |
| 2 | Sisa chrome shell (drawer notif/chat/AI, command palette, modal profil/password, Help Center chrome) | ✅ tuntas | Tab "Cara Pakai Aplikasi" & "Modul" di Help Center (ratusan baris prosa) **SENGAJA tidak disentuh** — bukan chrome. |
| 3 | Sales inti (Inquiry Hub, header Inquiry form, Quotation list, CO list) | ✅ tuntas | ~140 key. Field kalkulasi HPP/costing formula tidak disentuh (sesuai batasan). |
| 4 | Procurement inti (grid PR/PO/PV/PQ/PRT) | 🟡 **PARTIAL** | **5/5 grid list selesai**, **5x `entry.blade.php` (400-660 baris/file) BELUM disentuh sama sekali** — field approval/kalkulasi harga & pajak di layar entri PR/PO/PV/PQ/PRT masih 100% hardcode Indonesia. Ini gap TERBESAR dari seluruh rollout. |
| 5 | Inventory + Production inti (Stock In/Out/Adjustment, WO Worklist, Master Card) | ✅ tuntas | Termasuk alur approval Stock Adjustment. |
| 6 | Finance + HRM inti (COA, Cash & Bank, Attendance Daily, grid Employee) | ✅ tuntas (grid saja utk Employee) | Form profil karyawan (1300+ baris: keluarga, riwayat, dst) **SENGAJA tidak disentuh**. Payroll (tm2pyr) **tidak disentuh sama sekali** sesuai instruksi. |
| 7A | Master Data (grid Customer/Supplier/Produk/Raw Material/UoM) | ✅ tuntas (grid saja) | Form lengkap tiap master (alamat/kontak/dimensi/dst) tidak disentuh. |
| 7B | QA Checkpoint | ✅ selesai (laporan ini) | Lihat detail di bawah. |

**Area besar yang MASIH 100% hardcode Bahasa Indonesia** (bukan generalisasi
"sisanya", ini daftar konkret per modul):
- **Procurement — 5 layar entry** (`pur/{pr,po,pv,pq,prt}/entry.blade.php`):
  seluruh field kalkulasi harga, pajak, approval PR/PO/PV/PQ/PRT.
- **Master Employee — form profil penuh** (`master/employee.blade.php`,
  1300+ baris): tab identitas, alamat, keluarga, riwayat pendidikan,
  kontrak, kedisiplinan detail, foto — hanya grid yang tertarik.
- **Semua form Master Data lainnya** (Customer/Supplier/Produk/UoM/Raw
  Material): field detail (alamat, kontak, dimensi fisik, konversi
  satuan, dst) — hanya grid+toolbar yang tertarik.
- **Help Center — konten edukasi** (`help/index.blade.php` tab "Cara Pakai
  Aplikasi" & "Modul"): ratusan baris teks panduan & deskripsi modul.
- **Payroll (tm2pyr) — seluruh 11 layar**: SENGAJA di luar scope 7 malam
  ini (risiko tabrakan, modul sedang aktif dikembangkan terpisah).
- **Production Scheduling** (Scheduling Board, MPS Spreadsheet, MTS
  Forecast, Rule Library, dll) — belum tersentuh sama sekali oleh
  rollout 7 malam ini (di luar scope yang diminta).
- **HRM lainnya** (Recruitment 9 layar, Org Chart, Shift Roster, Leave
  Request, Correction Request, GPS Approval) — belum tersentuh.
- **Dispatch/Delivery, Finance lanjutan** (DP Invoice, laporan-laporan
  Outstanding PR/PO/PV, Win-Rate) — belum tersentuh.
- **Semua layar cetak/print** kecuali Master Card (Nota Inquiry, Nota CO,
  cetak PO/PR/PQ/PRT, dll) — belum tersentuh.
- **Panel Filament `/admin`** (HRM cluster) — pakai native Filament
  labels, mekanisme i18n `Translator::t()` shell TIDAK terhubung ke panel
  Filament (arsitektur terpisah, `APP_LOCALE` env-based, bukan
  `sec_user.lang_code`).

**Perkiraan cakupan (KASAR, bukan presisi)** — metode: hitung baris
`sys_translations` (lang_code='id') dibagi 2 (krn tiap key = 1 baris
id + 1 baris en):
- **Total 788 key unik** tertarik (9 prefix domain: `sales_`134,
  `inv_`122, `fin_`102, `shell_`91, `proc_`81, `mdata_`75, `hrm_`60,
  `shared_`55, `prod_`52, sisanya varian lama `login_`/`btn_`/dst).
  Dipanggil via 987 titik `Translator::t()` di seluruh Blade (reuse key
  yang sama di banyak tempat, jadi titik panggil > jumlah key unik).
- Total file layar (`resources/views/pages/**/*.blade.php`): **135
  file**. Proxy kasar (grep atribut `label=`/`placeholder=`/`title=` yang
  BELUM diwrap `Translator::t()`, TIDAK termasuk teks polos di
  `<td>`/`<h1>`/tombol tanpa atribut/string Alpine JS): **masih ~1360
  atribut belum tertarik** — rasio proxy ini ~42% tertarik, TAPI proxy ini
  cuma subset kecil dari total teks UI beneran (tidak menghitung teks
  polos/JS/komponen `<x-form.*>` internal yang jumlahnya jauh lebih
  banyak). **Perkiraan realistis cakupan keseluruhan aplikasi: sekitar
  15-25% dari total string UI** — MASIH SEBAGIAN KECIL, BUKAN mayoritas.
  **Aplikasi ini TIDAK bisa diklaim "sudah multi-bahasa"** — toggle
  `lang_code` ID/EN baru benar-benar berfungsi penuh di area shell +
  9 modul/layar yang disebut di atas, sisanya (mayoritas modul transaksi,
  semua form detail, semua cetak/print kecuali Master Card, Payroll,
  Production Scheduling, Recruitment, panel Filament) masih 100%
  hardcode Indonesia apa pun pilihan bahasa user.

**QA — hasil `npm run build`**: sukses, 0 error baru (1 warning lama
tak terkait — parsing komentar CSS di `docs/mockups/login.html`, bukan
kode aplikasi, sudah ada sebelum sesi ini).

**QA — hasil `php artisan test` (full suite)**: 122 test, **112 pass**,
**1 fail**, 9 skip (skip normal — Firebase emulator off, baseline lama).
**1 kegagalan**: `ProductionSchedulingBoardSmokeTest::test_board_renders_seeded_operations`
— **BUKAN disebabkan perubahan i18n malam ini** (file yang disentuh
malam ini tidak menyentuh Production Scheduling sama sekali). Diinvestigasi:
data dummy WO2600231 ada di DB, tapi assert mencari string **"Plong 1"**
(nama mesin, dari migration `2026_08_28_000002_seed_prd_scheduling_board_dummy.php`
baris 30) sementara operasi tersimpan sekarang bernama proses **"Plong"**
(tanpa "1") — dan employee **"Budi Santoso"** (dicari migration yg sama
baris 68) **TIDAK DITEMUKAN** di `emp_employee` sama sekali. Indikasi kuat:
data Master Employee/Machine sudah berubah (rename/hapus) oleh sesi lain
sejak seeder dummy ini dibuat 2026-08-28, seeder tidak pernah di-refresh
mengikuti perubahan itu. **Dicatat sbg temuan terpisah, TIDAK diperbaiki
malam ini** (di luar scope i18n, perlu investigasi Master Employee/Machine
mana yang berubah dan kapan, keputusan ada di sesi lain/user).

**Rekomendasi lanjutan (kalau user mau meneruskan i18n)**, urut prioritas:
1. ~~Tuntaskan 5 entry.blade.php Procurement~~ — **SELESAI 2026-09-11**
   (instruksi user "run now" setelah checkpoint §13.2). PR/PO/PV/PQ/PRT
   semua label/placeholder/tooltip/confirm/error dibungkus `Translator::t()`,
   ~190 key baru (`2026_09_11_000001_seed_i18n_procurement_entry_screens.php`).
   Diverifikasi render PR/PO/PV via `Livewire::test()` data live; PQ/PRT
   (tabel kosong) pakai record dummy sementara lalu dihapus lagi (balik ke
   0 baris). Smoke test 12/12 tetap pass. Commit `659982c`.
2. **Form lengkap Master Data** (Customer/Supplier/Produk/Employee) — saat
   ini cuma grid, form detail (mayoritas field per-master) masih hardcode.
3. **Production Scheduling** — modul paling kompleks (Scheduling Board,
   MPS, MTS Forecast) sama sekali belum tersentuh, volume string besar.
4. **Recruitment (9 layar)** — modul besar berikutnya yang 100% hardcode.
5. **Semua cetak/print selain Master Card** — Nota Inquiry/CO, cetak
   PO/PR/PQ/PRT — dokumen resmi yang customer/supplier lihat langsung.
6. **Panel Filament `/admin`** — perlu keputusan arsitektur terpisah dulu
   (jembatani `Translator`/`sec_user.lang_code` ke Filament yang pakai
   `APP_LOCALE`, bukan sekadar "lanjut key baru").
7. Payroll (tm2pyr) baru masuk antrian **setelah** modul lain, sesuai
   alasan risiko tabrakan yang sudah dicatat berkali-kali di file ini.

### 13.2 Audit kesiapan infrastruktur multi-bahasa (2026-09-10 23:50 WIB)

> Instruksi user setelah checkpoint §13.1: "make sure app ready for
> multilanguage" — DIBACA sebagai audit kesiapan **mekanisme/infrastruktur**
> i18n (bukan menambah cakupan terjemahan string baru lagi, itu sudah
> dicatat lengkap di §13.1). Ditemukan 1 bug nyata (diperbaiki malam ini)
> + 2 gap desain yang butuh keputusan user (BELUM diperbaiki, sengaja).

**BUG NYATA ditemukan & DIPERBAIKI**: `App::setLocale()` **TIDAK PERNAH**
dipanggil di mana pun di aplikasi ini sebelum malam ini. Akibatnya
`Carbon::translatedFormat()` (dipakai ratusan kali utk tanggal di
hampir semua layar transaksi) SELALU render pakai `config('app.locale')`
statis dari `.env` (`APP_LOCALE=en`) — **TERLEPAS dari `sec_user.lang_code`
masing-masing user**. Efeknya nyata utk SEMUA user, bukan cuma yang pilih
EN: user ber-`lang_code='id'` (default, mayoritas — kolom defaultnya
memang 'id') tetap melihat nama hari/bulan Bahasa Inggris ("Thursday, 10
September") walau seluruh UI di sekitarnya Bahasa Indonesia. Dibuktikan
lewat tinker: `Carbon::parse(...)->translatedFormat('l, d F Y')` sebelum
fix = "Thursday, 10 September 2026" (locale selalu 'en'); sesudah
`App::setLocale('id')` = "Kamis, 10 September 2026" (benar).
**Perbaikan**: middleware baru `App\Http\Middleware\SetUserLocale`
(`app/Http/Middleware/SetUserLocale.php`) — baca `$request->user()->lang_code`
(fallback `'id'` kalau guest/kosong), panggil `App::setLocale()`. Didaftarkan
sbg alias `'locale'` di `bootstrap/app.php`, dipasang di grup route utama
(`Route::middleware(['auth', 'locale'])` di `routes/web.php`, dulu cuma
`'auth'`) **dan** di `authMiddleware()` panel Filament
(`app/Providers/Filament/AdminPanelProvider.php`) supaya tanggal di
`/admin` (Filament Table `->date()` columns, pakai Carbon jg) ikut
konsisten. `Translator::t()` sendiri **TIDAK terpengaruh bug ini** — itu
baca `sys_translations` langsung per `lang_code`, tidak lewat
`App::getLocale()`, jadi teks yang sudah diterjemahkan (§0-13 di atas)
tetap benar dari awal; ini murni soal FORMAT TANGGAL yang lolos.
Diverifikasi: HTTP kernel riil 2 user (`lang_code` id vs en) ->
`App::getLocale()` ikut berubah sesuai; full test suite 122 test tetap
112 pass/1 fail (sama persis, gagalnya yg sudah dicatat §13.1, tidak
bertambah) — 0 regresi dari perubahan middleware ini.

**GAP DESAIN #1 — format angka TIDAK ikut bahasa (BELUM diperbaiki,
butuh keputusan user)**: `sys_languages` punya kolom
`decimal_separator`/`thousand_separator` per bahasa (EN: `.`/`,` ala
"1,234.56", ID: `,`/`.` ala "1.234,56") yang bisa diedit lewat Setup >
Language (`setup/language.blade.php`) — TAPI kolom ini **tidak pernah
dibaca** oleh kode manapun. Setiap `number_format($x, 0, ',', '.')` di
seluruh aplikasi (ratusan titik panggil, semua angka uang/qty/dst)
HARDCODE format Indonesia, terlepas dari `lang_code` user. Ini **BUKAN
bug sesederhana tanggal** — mengganti semua titik panggil `number_format()`
jadi dinamis ikut `sys_languages` adalah pekerjaan besar (ratusan file)
DAN ada pertanyaan desain nyata yang perlu dijawab user dulu: apakah
Rupiah/angka bisnis di ERP ini **memang harus ikut format Indonesia
SELALU** (banyak ERP perusahaan Indonesia sengaja begitu, konsisten
dgn dokumen resmi/faktur pajak yang WAJIB format Indonesia terlepas
bahasa UI) atau **harus ikut toggle bahasa** juga? Kalau jawabannya
"selalu Indonesia" (dugaan paling masuk akal utk konteks ERP lokal),
maka kolom `decimal_separator`/`thousand_separator` di `sys_languages`
justru harusnya DIHAPUS/diabaikan (bukan gap yang perlu ditutup) —
**JANGAN mulai kerjakan ini tanpa konfirmasi user**, sesuai arahan
"jangan tebak keputusan arsitektural".

**GAP DESAIN #2 — `sys_languages.is_default` tidak konsisten (kosmetik,
prioritas rendah)**: baris `en` punya `is_default=1`, baris `id` py
`is_default=0` — padahal migration `2026_09_07_000008_add_lang_code_to_sec_user.php`
(komentarnya) menyatakan asumsi `sys_languages.is_default` SEHARUSNYA
`id` saat itu ditulis. Ditelusuri: kolom ini **cuma dipakai utk urutan
tampil** di 2 layar Setup (`setup/language.blade.php`,
`setup/localization.blade.php`, `orderByDesc('is_default')`) — TIDAK
memengaruhi `sec_user.lang_code` default (kolom itu py default `'id'`
sendiri di level DB, independen). Jadi ini murni salah label
display-order, bukan bug fungsional. **Tidak diubah malam ini** (data
production, bukan kode — di luar wewenang mengubah tanpa diminta
eksplisit), cukup dicatat di sini kalau user mau membetulkan lewat UI
Setup > Language sendiri (centang "Default" di baris Bahasa Indonesia).

**Kesimpulan kesiapan**: mekanisme INTI (`Translator::t()` + toggle
`lang_code` + fallback aman utk key hilang + sekarang date-locale ikut
benar) sudah **solid & production-ready** utk cakupan yang SUDAH
diterjemahkan (§0-13). Yang BELUM "ready" murni soal **cakupan konten**
(sudah dijelaskan detail di §13.1 — modul mana saja masih hardcode),
bukan lagi soal mekanisme yang rusak/bocor.

---

---
