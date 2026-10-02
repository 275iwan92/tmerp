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
- Alamat tujuan dari kontak: SELESAI 2026-10-02 (kotak "Find contact" di dialog Email, lihat LOG_JOB_DONE). Catatan data:
  `cust_customer_contact` / `sup_supplier_contact` belum punya satu pun e-mail (0 baris terisi), jadi yang muncul sekarang
  e-mail utama customer (8) / supplier (20) dan user (156) -- kontak per orang muncul sendiri begitu diisi di master.

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

**FONDASI AUTO MULTI-BAHASA -- SELESAI 2026-10-01** (modul baru otomatis ID + EN):
A. Label komponen `x-dl::*` diterjemahkan otomatis di `DevlibComponent::translateTexts` (label/title/placeholder/hint/message +
   label kolom/stat/tab/signature + kunci parties/criteria); Button/Badge dikecualikan (istilah English). Helper `t()` + `@t()`.
B. Teks English baru tercatat otomatis ke `sys_dictionary` (akhir request web; label yang terdeteksi Indonesia tidak dicatat).
C. JS: `window.DL_DICT` (kamus 'ui' bahasa halaman) + `Devlib.h.t()`; teks JS baru dicatat lewat `POST /v2/dictionary/register`;
   `T(kunci)` bahasa ke-3 jatuh ke kamus.
D. Generator `make:tm-screen` + stub memakai `t('English')`; skill `tmerp-stack-rules`/`tmerp-screen` + CLAUDE.md §3 memuat cara membangun.
E. `MultiLanguageRatchetTest` (label Indonesia mentah 688, number_format gaya ID 2, format('d-M-Y') 1, toLocaleString id-ID 0 -- tidak boleh naik).
F. Migration kamus + menu dijalankan di **tm2sys dan ddtsys**.
+  Layar **Setup > Settings > Translation Dictionary** (`TranslationDictionaryLookup`, Grid Lookup: isi di sel, tempel dari Excel, kosong = English).
Test: `MultiLanguageAutoTest` (4), `LocaleNumberTest` (10), uji JS node.

**UTANG LABEL LAMA -- SELESAI SEBAGIAN BESAR 2026-10-01:** 643 label Indonesia mentah (layar v2, Grid DB, objek DevLib) diganti English
sesuai konteks (tag x-dl::* = English polos; HTML mentah / komponen lain = `t()` / `Devlib\Lang::text()`); teks Indonesianya persis diisi
ke kamus (`database/data/dictionary_ui_2026_10_01.json`, migration 000022-000024 di tm2sys + ddtsys; pasangan objek DevLib di `Devlib\Lang`).
Bukti: snapshot teks 68 layar v2 mode ID sebelum vs sesudah = IDENTIK (kecuali 3 label yang dulu English kini ikut diterjemahkan);
mode EN: baris Indonesia 1696 -> 824. Kolom `sys_dictionary.source_en` = utf8mb4_bin (kunci teks persis, "Unit price" != "Unit Price").
Teks bawaan JS grid/lines/sheet/form -> `T()` / `Tf()` + pasangan Lang (dijaga test kunci JS).

**SISA lain (bertahap, terukur di MultiLanguageRatchetTest):**
1b. SELESAI 2026-10-02: teks Indonesia di JS DevLib dipindah ke `T('kunci')` / `Tf('kunci', {..})` + pasangan `Devlib\Lang`
    (`js_id_text` 180 -> 44). Sisa 44 = separuh Indonesia tabel dua bahasa di `core.js` (LOGIN_T, AUTH_T, PRESET_T, kata bilangan)
    + 5 bukan teks layar; bukan utang. Daftar kata penjaga (`Dictionary::ID_WORDS`) diperluas 2026-10-02 (dulu meloloskan
    Departemen / Tipe / Sumber / Berlaku / Mingguan ...): label, kotak cari, nilai tampilan dan teks panduan kelas grid v2 serta
    46 label layar v2 ikut dikonversi; `id_label` tetap 0.
1c. Teks di luar pola ratchet: kolom `x-dl::table` bentuk ['kunci' => 'Label'], tab, teks isi Blade (`<p>`, `<th>`, `x-dl::alert`
    berisi kalimat), komentar/penampil kode. Ukuran kasar 2026-10-02: 242 baris `sys_dictionary` konteks `ui` belum punya
    terjemahan Indonesia; sebagian besar adalah teks sumber yang MASIH Indonesia di layar Inquiry / ProDev / Quotation
    (mis. "Alasan Pembatalan", "Batas Quotation", "Jenjang qty ditawarkan") -> konversi per layar seperti butir 2.
2. Setelah contoh JPEG disetujui: label layar/nota v2 yang masih teks Indonesia mentah -> sumber English + kamus, per dokumen:
   Inquiry (zona beku -- label saja, izin sudah ada utk ID->EN), ProDev, Quotation, CO, PR, PO, SPB, DO/Surat Jalan.
   Nota lama v1 yang dipanggil tombol "Nota" (quotation.print, co.print, pur.pr/po.print, dispatch.do.print) belum ikut print_lang.
3. Konversi data master Indonesia di DB ke English + kamus (tabel audit di dokumen desain §0.1) -- perlu rencana per tabel;
   data REAL (prod_process) & zona beku hanya dengan izin user.
4. Kamus `Devlib\Lang` (pasangan [ID, EN] di kode) untuk bahasa ke-3 diterjemahkan
   lewat `sys_dictionary` context `ui` (sumber = teks English) -- JS `T()` baru mengenal id/en.
5. Nama hari pemilih tanggal: satu helper `Devlib.h.dow2()` (Indonesia Mg/Sn/Sl/Rb/Km/Jm/Sb, bahasa lain Intl) -- 2026-10-02.
   Toolbar Grid, Permission Matrix, bantuan Sheet, kotak konfirmasi bawaan sudah lewat kunci Lang (2026-10-02).
6. **Kamus hanya berisi label (2026-10-02, SELESAI):** nilai data di prop teks dibungkus `raw()`; nomor dokumen / teks hasil
   terjemahan tidak dicatat; baris data lama dihapus (rulebook B.3, `LOG_JOB_DONE.md`). **Sisa terbuka, dicek 2026-10-02 malam
   (belum dikerjakan, user pindah sesi):**
   a. Label `ui` tanpa terjemahan Indonesia: **243 di ddtsys, 22 di tm2sys** -> tampil English dalam mode Indonesia. Sebagian
      besar dari layar yang dibangun 2026-10-02 (papan widget, Customer Invoice, Proof of Delivery). Usulan: susun daftar +
      usulan terjemahan untuk disetujui user, lalu isi lewat migrasi (ddt + tm2).
   b. Label Indonesia `Tujuan` tercatat sebagai sumber English (ddtsys, 2026-10-02 18:25, salah satu grid Delivery): ganti
      labelnya ke English di layar / kelas grid, tambahkan kata itu ke `Dictionary::ID_WORDS`, hapus baris kamusnya.
   c. Nama aplikasi `ERP TM` tercatat di kamus tm2sys (judul `x-dl::layer-menu` = data, bukan label): bungkus `raw()` di
      `packages/devlib/resources/views/components/layer-shell.blade.php` (`:title`), hapus baris kamusnya (juga `TELASIH MAKMUR` di ddt).
   d. Pesan flash yang dikirim sebagai variabel utuh (`:message="$error"`, `session('v2_flash')`) tidak bisa dibedakan dari
      label: yang bernomor dokumen tertahan `Dictionary::looksLikeData()`, yang berisi nama masih bisa tercatat. Perlu
      keputusan: pesan flash dianggap teks jadi (`raw()` di semua alert flash) atau diterjemahkan di tempat pesan dibuat.
   e. Engine Catalog (`App\Support\Engines\EngineRegistry`) hanya English; terjemahan Indonesia belum ada.

7. **Sapuan multi-bahasa 2026-10-03 (SELESAI, lihat LOG_JOB_DONE dan `md_for_systems/multilanguage_status.md`):** butir 1c, 2
   (label layar; nota v1 tidak), 6a-6d selesai. **Sisa terbuka, semuanya menunggu keputusan user** (rincian di dokumen status §4):
   a. teks yang ditulis service ke database (uraian jurnal, catatan kartu stok, catatan PR / PO otomatis, `field_label` revisi);
   b. data master Indonesia (satuan, kategori, nama akun, tipe scrap, label parameter dieline, deskripsi konstanta);
   c. pesan dan label engine beku (CostingEngine, SpecEngine, UsageRateEngine, ReverseCostingService);
   d. nilai yang sekaligus kode tersimpan (PRINT_LABELS approval, sumber Inquiry, peran BOM, 'Tunai', 'Lainnya');
   e. Engine Catalog masih English saja (butir 6e lama); nama isian validasi tanpa nama dari layar; layar v1 yang dibuka dari grid v2.

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

