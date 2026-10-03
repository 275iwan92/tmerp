# Multi-bahasa — status setelah sapuan 2026-10-03

Perintah user: *"Make sure multi language sudah jalan dengan sempurna"*, lalu *"make sure multi language ready on
mockup.test and tmerp.test, make sure connect to database and tables"*.
Aturan dasarnya tetap `docs/globaluserrules.md` B.3 dan CLAUDE.md §3 (database + formula English, bahasa lain lewat kamus).
Desain awal: `docs/multilanguage_design.md`. Alat uji: `tools/multilanguage/`.

## 1. Yang ditemukan
Test penjaga lama lulus, tetapi hanya mengenali sekitar 200 kata. Uji render 366 layar v2 di mockup.test dalam dua
bahasa menunjukkan:
- mode English masih menampilkan teks Indonesia: sekitar 1.600 baris kode (layar v2, `routes/v2*.php`, kelas
  `app/Support/V2`, objek DevLib, mockup-parts, pesan service);
- mode Indonesia menampilkan teks English yang tidak pernah lewat penerjemah;
- bahasa mengikuti cookie komputer, bukan akun; memilih bendera tidak memuat ulang halaman, sehingga menu dan label yang
  dirender server tetap bahasa lama;
- pesan validasi selalu English (folder `lang/` belum ada);
- label menu tidak diterjemahkan sama sekali;
- kamus `ui`: 243 (ddt) / 22 (tm2) teks tanpa terjemahan, sebagian teks Indonesia yang tercatat sebagai sumber English.

## 2. Yang dikerjakan
| Bagian | Isi |
|---|---|
| Bahasa ikut akun | `Devlib\Locale::langUsing()` -> `sec_user.lang_code`; urutan: `?print_lang` (cetakan) -> akun -> cookie (tamu) -> bawaan. `x-dl::lang-switch` membaca `sys_languages`, menyimpan lewat `POST /v2/lang` (`config devlib.lang_url`) lalu memuat ulang halaman; bila ada tab berisi input belum di-Save, bertanya dulu. `SetUserLocale` menyamakan locale Laravel (Carbon, validasi) dengan bahasa itu. |
| Pesan | Helper `tf('Text :key', [...])`. Semua pesan flash / error / abort / JSON di `routes/v2*.php`, kelas Data dan service diterjemahkan di tempat dibuat; layar menampilkannya sebagai teks jadi (`raw(session('v2_flash'))`, `raw($errors->first())`). |
| Validasi | `lang/en` (bawaan Laravel) dan `lang/id` (validation, auth, pagination, passwords). Nama isian lewat `t('English')`. |
| Teks layar | Layar v2, kelas `app/Support/V2`, DevLib (333 entri baru di `Devlib\Lang::DICT`), mockup-parts PHP + JS, service: teks English di kode. Pasangan English => teks Indonesia lama: `database/data/dictionary_ui_2026_10_02_sweep.json` (3.026 pasangan). |
| Menu | 54 label menu Indonesia -> English (`sys_app_menu.label`), teks lamanya masuk kamus; `AppMenu::label()` dan breadcrumb layar menerjemahkan lewat kamus. |
| Kamus | Semua teks `ui` dan `help` punya terjemahan Indonesia di ddt dan tm2 (0 kosong). 274 baris basi dihapus (teks Indonesia / data yang tercatat sebagai sumber English, termasuk `ERP TM`, `TELASIH MAKMUR`, `Tujuan`). |
| Penjaga | `Dictionary::looksIndonesian()` memakai `app/Support/Localization/indonesian_words.php` (2.100 kata, diturunkan dari isi kamus); teks hasil terjemahan `Devlib\Lang` tidak lagi tercatat sebagai sumber baru. `MultiLanguageRatchetTest`: `id_label` 0, `data_in_text_prop` 0. |
| Model | Peta label `ProdMachine`, `ProdMachineDowntime`, `ProdMasterCardFile`, `ProdProcess::OUTPUT_UOM`, `ProdProductAcc`, `QuoActivity` kini English; layar v1 yang menampilkannya memakai `t()` sehingga tetap Indonesia. |

Migrasi (sudah jalan di tm2sys dan ddtsys, `--path`): `2026_10_02_000300` .. `000305`.
Test: `LanguageAccountTest` (6), `MultiLanguageAutoTest`, `MultiLanguageRatchetTest`, `LocaleNumberTest`.

## 3. Bukti
- Snapshot teks 361 layar sebelum / sesudah (`tools/multilanguage/snapdiff.py`):
  - mode English: 455 teks Indonesia hilang, tidak ada teks Indonesia baru dari kode (sisanya nilai data);
  - mode Indonesia: tidak ada label yang berubah menjadi English, kecuali tombol / badge / status (aturan: selalu English)
    — contoh: "Tambahkan Otomatis" -> "Add Automatically", "Resmi · Quotation WIN" -> "Official · Quotation WIN".
- Label yang dulu hanya English kini tampil Indonesia di mode Indonesia (panel Dieline, Imposition, ProDev workbench, footer
  Lines, pager, tab History). Bisa diubah di Setup > Translation Dictionary tanpa ubah kode.

## 4. Belum dikerjakan — menunggu keputusan user
1. **Teks yang ditulis service ke database** (tidak diubah, karena mengubah data bisnis yang tersimpan): uraian jurnal dan
   baris jurnal (`Pembalik: …`, `Penerimaan barang …`, `Kasbon …`, `Penyusutan & amortisasi …`), catatan kartu stok, catatan
   PR / PO otomatis dari ProDev dan subkon, `iqp_revise_change.field_label` / `impact`, nama default (`Part n`), catatan
   payslip, isi notifikasi. Daftar lengkap per file ada di laporan pekerja S1 / S2 (ringkasannya di memori serah terima).
   Usulan: tulis English + kunci kamus mulai tanggal tertentu; data lama dibiarkan.
2. **Data master yang isinya masih Indonesia** (tampil apa adanya di mode English): `uom_unit.uom_name`, dimensi UoM
   (PANJANG / LUAS / MASSA / WAKTU), `prod_category`, `acc` nama akun, `prod_scrap_type`, `sup_category`, `prod_type*`
   (label parameter dieline; pasangan terjemahannya sudah disiapkan di `database/data/dieline_type_labels_en_id.json`),
   deskripsi `sys_constant`, `ProdMachineModel::DRIVES`, nama posisi / departemen. Data real (mesin, proses, pegawai)
   hanya dengan izin user.
3. **Engine beku** (`CostingEngine`, `ReverseCostingService`, `SpecEngine`, `UsageRateEngine`): pesan dan label panel
   BOP / BOM / Ink masih Indonesia; tidak disentuh. Beberapa kata di sana juga dipakai sebagai kode (`KERTAS PLANO`,
   `TINTA`, `CUKUP` / `KURANG`, nama warna).
4. **Nilai yang sekaligus kode tersimpan**: `ApprovalSetting::PRINT_LABELS` (Diperiksa / Menyetujui / Mengetahui), sumber
   Inquiry (EMAIL, WA, …), `'Lainnya'` di biaya eksternal CO, peran BOM di workbench, `'Tunai'` pada PO Kontan.
5. **Lembar Proof Digital**: judul tercetak (NO, REVISION NOTE, DESIGN NUMBER, …) sama di dua bahasa seperti formulir
   kertas; hanya PRACETAK -> PREPRESS di English.
6. **Layar v1** tidak ikut (arah: diganti v2). Yang dibuka dari grid v2 (Master Mesin, Master Proses, Stock In / Out,
   Finance v1) masih Indonesia di mode English.
7. **Nama isian validasi** yang tidak diberi nama oleh layar tampil sebagai nama kolom (mis. "cancel reason").
8. **Badge status** dengan peta warna berkunci English (Maintenance, Petty Cash, Purchase Receive, Material Planning,
   Proof of Delivery, Customer Invoice) tetap English di dua bahasa — sesuai aturan status selalu English.
9. `Engine Catalog` (`EngineRegistry`) masih English saja.

10. **Menu v1 (shell lama)** menampilkan label menu English apa adanya (tidak lewat kamus) — v1 tidak ditarik ke multi-bahasa.

## 5. Di luar multi-bahasa (ditemukan, tidak diubah)
- `App\Services\Dispatch\DispatchService` baris 225-241: menyiapkan pesan hasil lalu melempar `RuntimeException('rollback')`
  tanpa ditangkap, sehingga user melihat "rollback". Perlu diperiksa pemilik modul Delivery.
- Route cetak lama `v2.pv.print` (`routes/v2.php` 673-711) tampaknya sudah digantikan `GoodsReceiptPrint`; tidak ada
  rujukan dari layar v2.

- **tmerp.test (tm2) — 9 layar v2 gagal dibuka karena migrasi modul lain belum dijalankan di tm2** (bukan soal bahasa; uji
  `php tools/multilanguage/siteprobe.php tmerp.test`): Master Mesin (form), Machine Model Catalog, Maintenance Codes,
  Maintenance WO Report, Material Booking, Material Planning, Demand Forecast, Material Usage Standard; juga form Purchase
  Requisition (`flow_config_step.activate` belum ada). 23 migrasi masih pending di tm2 (approval flow 2026_09_30_000006-10,
  machine model 2026_10_01_000010-13 / 31-33, speed engine 000030, cash PO 000035, maintenance 000040, material planning
  000070, dan beberapa menu / dummy khusus ddt). Menjalankannya di database live = keputusan user. mockup.test (ddt): semua
  layar terbuka.
- Test yang gagal bukan karena sapuan ini (per 2026-10-03): `NoHardcodedDatabaseNameTest` (`StageBuilder.php:105`),
  `EngineCatalogTest` (engines.json tertinggal dari `PalletService`), `HelpContentTest` (`stack-monitor` belum punya Help),
  `AccountingEngineTest::test_semua_layar_finance_bisa_dirender` (layar v1 receivables sudah diarsipkan),
  `MasterMachineMigratedDataTest` dan `PurchaseRequisitionFreeTextLineTest` (migrasi tm2 di atas).

## 6. Cara menambah bahasa ke-3
1. **Setup > Languages**: nyalakan saklar Active pada bahasa itu (Jepang `ja` dan Mandarin `zh` sudah disiapkan nonaktif;
   bahasa lain: developer menambah barisnya di layar yang sama — kode, nama, `intl_locale`, pemisah angka). Bendera dan
   kolom di Translation Dictionary muncul sendiri.
2. Isi terjemahan di Setup > Translation Dictionary (kolom bahasa baru); kosong = tampil English.
3. Salin `lang/en/*.php` ke `lang/<kode>/` dan terjemahkan (pesan validasi).
4. Singkatan bulan: cabang "Month names" di pohon kiri (kamus konteks `date.month_short`).

## 7. Translation Dictionary dua panel ala ScriptCase (2026-10-03)
User menunjukkan layar *Application language* ScriptCase (pohon kategori di kiri — Scriptcase Messages / Project messages —,
satu baris per kunci dengan satu kolom per bahasa di kanan) dan minta pola itu supaya ke depan mudah. Mockup disetujui:
`docs/mockups/translation_dictionary_v2.jpg`. Yang dibangun (ddt + tm2, migrasi `2026_10_03_000400`):

| Bagian | Isi |
|---|---|
| Pohon kiri | `x-dl::side-menu` di `x-dl::split side="left"`: *All messages*, *Untranslated*, cabang **DevLib messages** (kata bawaan objek DevLib), **Project messages** (teks aplikasi) — keduanya per kelompok Buttons / Menu / Grid / Form / Messages / Validation / Print / Help / Dates / General —, dan **Lookup values** (satu simpul per konteks `<db>.<tabel>.<kolom>` + Month names). Angka = jumlah baris. |
| Kelompok | Tabel `sys_dictionary_group` (code, label, icon, urutan, aktif) — layar **Setup > Message Groups**. `sys_dictionary.group_code` merujuknya. |
| Pengisian kelompok otomatis | Komponen DevLib yang menampilkan teks memberi petunjuk kelompok (`DevlibComponent::dictionaryGroup()` → `Devlib\Lang::hint`): Button → buttons, Grid/Table/Sheet → grid, Form/Field → form, Alert/Modal → messages, menu/breadcrumb → menu, cetakan → print. `tf()` → messages, `AppMenu::label` → menu, konteks `help` → help, `date.*` → dates, nilai lookup → lookup. Baris lama berkelompok General pindah sendiri begitu tampil lewat komponen berkelompok. Kolom **Group** (dropdown) di grid untuk memindahkan manual. |
| Used on | `sys_dictionary.first_seen` = slug layar / nama route tempat teks pertama kali tampil. Baris DevLib menampilkan "DevLib object · <terjemahan bawaan>". |
| Kamus proyek menang | `Devlib\Lang::text()` dan `Lang::t()` bertanya ke kamus aplikasi dulu (translator mengembalikan `null` bila tidak ada), baru `Lang::DICT`. Semua entri `DICT` disalin ke `sys_dictionary` (`origin = devlib`, 750-an baris + terjemahan Indonesia) sehingga tampak dan bisa ditimpa dari layar; DICT tetap cadangan untuk devlib.test tanpa database. Dijaga `DictionaryGroupsTest`. |
| Sel kosong | Kolom bahasa `emptyWarn` (objek Sheet DevLib): sel tanpa terjemahan berwarna kuning = tampil English. |
| Bahasa | **Setup > Languages** (`sys_languages`): user biasa hanya saklar Active (+ urutan); Administrator / grup DEVELOPER boleh mengubah kode, nama, locale, pemisah. English tidak bisa dimatikan. `ja` dan `zh` disiapkan nonaktif. Menyimpan membuang cache kamus (`Dictionary::flush`). |

Kelompok awal baris lama ditebak dari bentuk teks (label menu → Menu; kalimat berakhir `.`/`!`/`?` atau ber-`:kunci` → Messages;
`Search ...` → Grid; sisanya General) — pindahkan lewat kolom Group bila keliru. Satu kelemahan kunci-teks tetap: kata sama
beda arti dibedakan lewat `context`, bukan kelompok.
