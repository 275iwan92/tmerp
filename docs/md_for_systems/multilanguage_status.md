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

## 5. Di luar multi-bahasa (ditemukan, tidak diubah)
- `App\Services\Dispatch\DispatchService` baris 225-241: menyiapkan pesan hasil lalu melempar `RuntimeException('rollback')`
  tanpa ditangkap, sehingga user melihat "rollback". Perlu diperiksa pemilik modul Delivery.
- Route cetak lama `v2.pv.print` (`routes/v2.php` 673-711) tampaknya sudah digantikan `GoodsReceiptPrint`; tidak ada
  rujukan dari layar v2.

## 6. Cara menambah bahasa ke-3
1. Tambah baris di `tm2sys.sys_languages` (kode, pemisah angka, `intl_locale`, aktif) — bendera muncul sendiri.
2. Isi terjemahan di Setup > Translation Dictionary (kolom bahasa baru); kosong = tampil English.
3. Salin `lang/en/*.php` ke `lang/<kode>/` dan terjemahkan (pesan validasi).
4. Singkatan bulan: kamus konteks `date.month_short`.
