# Multi-Language (N bahasa) — Usulan Desain

Status: **USULAN, menunggu keputusan user** (2026-10-01). Pemicu: user akan menambah bahasa
lain (mis. Jepang, Mandarin) selain ID/EN, jadi sistem tidak boleh terkunci pada pasangan ID/EN.

## 1. Referensi — cara ERP besar bekerja

| Sistem | Teks antarmuka (label) | Data master (nama barang, satuan, dst) | Format angka/tanggal | Bahasa dokumen cetak |
|---|---|---|---|---|
| **SAP** | Kamus teks per kunci + bahasa (OTR, text elements) | **Text table** terpisah: `MARA` (data) + `MAKT` (deskripsi, kunci `MATNR`+`SPRAS`) | Per **user** (`USR01.DCPFM`: 1.234,56 / 1,234.56 / 1 234,56) | Bahasa **mitra** (`KNA1-SPRAS`) menentukan bahasa output |
| **Oracle EBS** | Message dictionary per bahasa | Tabel **`_TL`** (`LANGUAGE`, `SOURCE_LANG`) + view `_VL` yang memilih `USERENV('LANG')` | Sesi `NLS_NUMERIC_CHARACTERS` | Bahasa mitra / parameter laporan |
| **Dynamics 365** | **Label file** (ID label → teks per bahasa) | Tabel translation (mis. terjemahan produk per `LanguageId`) | Per bahasa/region user | `CustTable.LanguageId` → faktur dicetak dalam bahasa customer |
| **Odoo 16+** | File `.po` per bahasa | Field `translate=True` disimpan **JSONB** `{"en_US": .., "id_ID": ..}` di tabel itu sendiri, `en_US` wajib sbg fallback | Per **bahasa** (`res.lang`: decimal_point, thousands_sep, date_format, direction) | `partner.lang` → dokumen dalam bahasa partner |
| **ScriptCase** | File lang per bahasa, dipilih per project | — (dibuat sendiri) | "Regional settings" per locale, bisa di-override | Ikut locale aplikasi |
| **Accurate / Zahir** | 2 bahasa tetap (ID/EN) | Tidak ada terjemahan data | Preferensi perusahaan (format desimal) | Terbilang ID/EN dipilih di preferensi |

Pola yang sama di semua ERP besar:
1. **Daftar bahasa = data** (tambah bahasa = tambah baris + isi terjemahan, bukan ubah kode).
2. **Label UI = tabel kunci → teks per bahasa**, dengan **fallback** (bahasa user → bahasa dasar → kunci).
3. **Data master terjemahan dipisah dari data inti** (SAP text table / Oracle `_TL`), kecuali Odoo (JSON, karena PostgreSQL JSONB kuat).
4. **Angka disimpan mentah** (DECIMAL); format hanya saat tampil, aturannya dari tabel bahasa/locale.
5. **Bahasa dokumen cetak mengikuti mitra** (customer/supplier), bukan bahasa layar operator.

## 2. Usulan untuk tmerp

### 2.1 Tabel bahasa — `tm2sys.sys_languages` (sudah ada, diperluas; pola Odoo `res.lang`)
Kolom sekarang: `code, name, decimal_separator, thousand_separator, is_default`. Tambah:
`native_name` (日本語), `intl_locale` (id-ID / en-US / ja-JP / zh-CN — dipakai browser `Intl`),
`fallback_code` (ja → en), `terbilang` (ada/tidak), `font_family` (font CJK untuk PDF, mis. Noto Sans JP),
`activate`, `sort_order`. Diedit di **Setup > Language** (aturan §7.12: semua data ada inputannya).

### 2.2 Teks antarmuka — `tm2sys.sys_translations` (sudah ada, 996 kunci × 2 bahasa; pola SAP/D365 label)
- Kamus DevLib `Devlib\Lang` sekarang pasangan PHP `[ID, EN]` → diganti **peta per kode bahasa**
  (`['save' => ['id' => .., 'en' => ..]]`), isi tambahan dari `sys_translations`. Bahasa baru cukup isi baris.
- Fallback: bahasa user → `fallback_code` → `en` → kunci. Sel yang belum diterjemahkan tetap tampil (tidak kosong).
- Layar terjemahan: grid spreadsheet (kolom = bahasa aktif, sel kosong disorot) + Export/Import Excel untuk penerjemah.
- Tombol & status tetap English satu kata di SEMUA bahasa (keputusan 2026-09-30) — tidak masuk kamus terjemahan.

### 2.3 Data master yang tercetak ke mitra asing (pola SAP text table / Oracle `_TL`)
Rekomendasi: **tabel anak `<tabel>_tl`** di database yang sama dengan induknya, contoh
`tm2mst.uom_unit_tl (id_uom_unit_tl BIGINT PK, id_uom FK, lang_code, name, UNIQUE(id_uom, lang_code))`.
- Kolom di tabel induk tetap = bahasa sumber (Indonesia); `_tl` hanya bahasa lain; tidak ada baris → pakai induk.
- Normal, ber-index, FK fisik (satu DB), sesuai aturan PK BIGINT. Dibuat **hanya** untuk tabel yang benar-benar
  tercetak ke mitra (kandidat: satuan, syarat pembayaran, nama proses/finishing, nama produk versi customer).
- Alternatif Odoo (kolom JSON) ditolak: di MySQL pencarian/index JSON lemah dan melanggar normalisasi.
- Transaksi tetap **snapshot** (§4): teks terjemahan dibekukan ke dokumen saat dokumen dicetak/Confirm bila perlu.

### 2.4 Angka & tanggal
- DB: selalu DECIMAL mentah. Form → server: selalu angka kanonik `1234.5` (DevLib mengubah sebelum kirim),
  jadi ganti bahasa tidak pernah merusak nilai tersimpan.
- Tampil: pemisah dari `sys_languages` bahasa aktif (id 1.234,56 · en 1,234.56 · ja 1,234.56 · zh 1,234.56).
  PHP `Devlib\Locale` dan JS `Devlib.h.fmt/parseNum` membaca aturan yang sama (dikirim ke halaman sekali).
- Tanggal: tetap dd-Mmm-yyyy bulan Inggris di semua bahasa (keputusan 2026-09-30) kecuali diputuskan lain.

### 2.5 Bahasa cetak
- Menu Print/Email: pilihan **Bahasa cetak** = daftar bahasa aktif (bukan hanya ID/EN).
- Default = bahasa mitra: tambah `cust_customer.lang_code` & `sup_supplier.lang_code` (pola SAP/D365/Odoo);
  kosong → bahasa layar user. Operator tetap bisa ganti sebelum cetak.
- Terbilang: ID & EN ada; bahasa baru tanpa modul terbilang → pakai EN (atau disembunyikan).

## 3. Keputusan yang diminta dari user
1. Terjemahan data master: tabel anak `_tl` (rekomendasi) atau kolom JSON ala Odoo?
2. Default bahasa cetak = bahasa customer/supplier (tambah kolom `lang_code`)? (rekomendasi: ya)
3. Format angka per **bahasa** (rekomendasi, pola Odoo) atau per **user** (pola SAP)?
4. Tanggal dd-Mmm-yyyy bulan Inggris tetap untuk Jepang/Mandarin?

## Sumber
- Odoo JSONB translated fields: https://github.com/odoo/odoo/pull/97692 · res.lang: https://www.braincuber.com/tutorial/define-translations-custom-module-odoo-18
- SAP MAKT-SPRAS: https://www.sapdatasheet.org/abap/tabl/makt-spras.html · USR01-DCPFM: https://answers.sap.com/questions/1922840/how-to-change-the-decimal-notationusr01-dcpfm.html
- Oracle EBS `_TL`/`_VL`: https://oracleerpappsguide.com/2013/09/multi-language-data-support-in-oracle-apps.html
- Dynamics 365 multilingual labels: https://learn.microsoft.com/en-us/dynamics365/fin-ops-core/dev-itpro/analytics/er-design-multilingual-reports
- ScriptCase regional settings: https://www.scriptcase.net/docs/en_us/v9/manual/06-applications/04-chart-application/08-chart-fields/05-decimal/
- Accurate bahasa terbilang cetakan: https://ultimasolusindo.com/pengaturan-bahasa-terbilang-say-pada-desain-cetakan/
