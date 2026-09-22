---
name: screen_inqprod_new
description: Layar entry Inquiry Product BARU (dan render dasar mode NEW/RECALC/REVISE lama) — header, qty tier, part & dieline visual, bahan, tooling, biaya eksternal.
---

# Inkuiri Produk (Form BARU)

**File**: `resources/views/pages/inquiry/form.blade.php`
**Route**: `inquiry.form` (`/inquiry/new`, param opsional `id`, `mode`, `view` (dipetakan ke properti `crossView`), `fromPart`)
**Koneksi DB utama**: sdi (tm2sdi), baca lintas-DB ke mst (tm2mst) untuk customer/produk/material
**Modul**: Inquiry Product

## Tujuan
Layar entry utama untuk doc-type **BARU** (Inquiry dari nol, Master Product belum ada) — diisi Sales. Header sudah menerapkan alur baru 2026-09-04: **Kategori Produk Pasar** (shortcut bahasa awam, mis. "Box Kosmetik") di level Inquiry mengisi cepat **Tipe Produk** (juga level Inquiry, berlaku untuk semua Part sekaligus — bukan lagi per-part) + auto-terapkan template multi-part (Body/Lid) kalau relevan. Tiap Part harus melewati gerbang **Peran Part** (picker visual: Body/Lid/Base/Sleeve/Tray/Insert/Divider/Left/Right/MonoForm) sebelum Dimensi & Preview Dieline live aktif — tipe non-multi-part auto-role MonoForm. File ini juga dipakai untuk merender ringkasan read-only lintas-modul (`crossView=true`, dari wizard-bar ProDev/Costing/Master Card) via component terpisah `<x-inquiry.readonly-summary>` — BUKAN form input aslinya. `$mode` menentukan judul/badge (NEW/RECALC/REVISE) tapi field aktual untuk RECALC/REVISE sekarang ditangani di file terpisah (`recalc.blade.php`/`revise.blade.php`).

## Field & Kolom
**Header** (`iqp_inquiry`):
- `inquiry_no` (readonly, auto `IQ`+YY+NNNNN saat simpan), `inquiry_date`, `deadline_quotation` (default +14 hari kerja kalender), `pic_sales` (readonly + popup picker dari master `sales_person`, auto dari nama login), `source` (EMAIL/WA/PHONE/VISIT/MARKETPLACE).
- Customer: combobox scroll+search (`customer_id`, browse/filter live) + tombol "+ Buat Customer Baru" (quick-add popup: `newCustName`/`newCustType` local-import/`newCustPhone`/`newCustEmail`/`newCustAddress`, defaultkan `approval_status=DRAFT`, `credit_rating=C`, `kyc_status=PENDING` — tetap perlu review Finance/Credit di Master Customer). `is_prospect`/`prospect_name` masih ada di skema untuk baca data lama, tidak lagi bisa di-set true dari layar ini.
- `product_draft_name` (textarea autogrow, wajib, larang karakter `/@#%"'`), Kategori Produk (dropdown UI-only -> `applyProductCategory()`), Tipe Produk level-Inquiry (`setSharedProductType()`, berlaku semua Part) + banner saran template multi-part (`applyPartTemplate()`).
- `sales_note`, checkbox tunggal "Customer bawa bahan baku sendiri" (`material_source` OWN/CUSTOMER, derive `job_nature` PRODUCT/JASA & `pph_applicable` default), kalau CUSTOMER: `cust_material_spec`* + `cust_material_eta`.
- Tersembunyi dari UI tapi tetap ada di skema/logic: `tax_scheme`, `pph_applicable` (background saja), `discount_doc_type/value`, `discount_qty_value/min_ton` (ditunda ke CO, CLAUDE.md §4 diskon jarang).

**Qty Order Berjenjang** (`iqp_tier`, array `$tiers`): `qty` (angka), tepat 1 `is_primary`. Preset 3 baris (10rb/50rb/200rb) saat Inquiry baru.

**Part & Dieline** (`iqp_part`, array `$parts`, per baris):
- `part_name` (+ tombol picker Peran Part 🏷️ auto-isi nama = `slug-produk-peran`, beku setelah digenerate), `part_type` (DC/DC+SHEET/DC+SHEET/SHEET/KRAFT), `qty_per_sku`, Artwork upload (temp Livewire upload, JPG/PNG maks `INQUIRY_ARTWORK_MAX_MB`, preview+zoom, analisis warna otomatis).
- Dimensi: field dinamis dari `prod_type_param` (per Tipe Produk, ENUM atau angka) — HANYA aktif setelah Peran Part terisi (atau part lama yang sudah tersimpan, gate dilewati untuk data existing). Preview Dieline live via Alpine `dielinePart()` + `window.TMDieline`.
- Bentangan (Flat): `flat_w`/`flat_h`/`flat_area` (auto dari dieline atau manual toggle), saran ukuran dari metadata DPI artwork (`suggestSizeFromArtwork()` — hanya tampil kalau file punya DPI asli, tidak menebak dari resolusi pixel mentah).
- Bahan (`iqp_part_material`, sub-repeater): autocomplete cari `MatRaw` (min 2 karakter), `gram_weight`/`size_p`/`size_l` auto-fill, tepat 1 `is_primary`, saran bahan dari histori Inquiry Tipe Produk sama (`suggestMaterialFromHistory()`).
- Cetak/Warna: `cfg_color` (notasi sisiA/sisiB, dropdown 4/4-4/1-4/0-2/0-1/1-1/0) + turunan (`front`/`back`/`total`/`side`), `cfg_ink` (catatan Pantone). Saran warna dari analisis pixel artwork (GD, heuristik kasar) & saran finishing dari histori.
- Finishing Tambahan (`iqp_part_process`, sub-repeater baru 2026-09-04): dropdown dari master `ref_finishing_type` (reuse master ProDev), boleh >1 baris, replace-all saat simpan.

**Tooling** (`iqp_tooling`, array `$toolings`): `tooling_kind` (PLATE/PISAU/MATRAS/FILM), `tooling_status` (ADA/ADA_WARNING/AUS/TIDAK_ADA), `is_new_charge` toggle (cost dikunci 0 kalau tidak), `cost`, `remark`.

**Biaya Eksternal** (`iqp_external_cost`, array `$externalCosts`): `description`, `cost_kind` (TRUCKING/EKSPEDISI/JASA_LUAR/LAINNYA), `amount`, `tax_treatment` (TAXABLE/REIMBURSEMENT).

## Aksi / Method
- `mount()` — load existing (`$id`) atau init draft baru (preset tier, PIC Sales dari login, 1 part kosong); `crossView=true` skip isi state form, hanya tandai untuk ringkasan read-only.
- Customer: `selectCustomer`, `clearCustomer`, `openNewCustomerModal`/`closeNewCustomerModal`/`setNewCustType`/`saveNewCustomer` (+ `generateQuickCustomerCode()` format `QC{YYMM}NNNN`).
- Sales picker: `openSalesPicker`/`closeSalesPicker`/`selectSalesPerson`.
- `setProspect`, `jobNature()`, `setMaterialSource`, `setTaxScheme`, `setPph`, `setDiscountDocType`.
- Tier: `addTier`, `removeTier`, `setPrimaryTier`, `setTierDiscountType`.
- Part/Tipe/Peran: `addPart`, `removePart`, `openRolePicker`/`closeRolePicker`/`selectPartRole` (+ `applyRoleToPart()` protected — generate nama beku), `partTemplateSuggestion`/`applyPartTemplate` (template multi-part per Tipe Produk), `applyProductCategory` (shortcut Kategori Produk -> Tipe Produk), `setSharedProductType` (Tipe Produk level-Inquiry, semua Part), `selectPartType`, `applySizePreset`.
- Warna: `colorBreakdown`, `applySuggestedColor`.
- Bahan: `addMaterialRow`, `removeMaterialRow`, `setMaterialPrimary`, `materialResults`, `selectMaterial`, `clearMaterialRow`, `applySuggestedMaterial`.
- Finishing Tambahan: `addFinishingRow`, `removeFinishingRow`, `applySuggestedFinishing`.
- Artwork: `updatedArtworkUploads` (lifecycle hook), `removeArtwork`, `persistPendingArtwork` (protected), engine saran (`refreshArtworkSuggestions`, `suggestSizeFromArtwork`+`readJpegDpi`/`readPngDpi`, `analyzeImageColors`, `suggestMaterialFromHistory`, `suggestFinishingFromHistory` — semua protected).
- Tooling: `addTooling`, `removeTooling`, `setToolingNewCharge`.
- Biaya Eksternal: `addExternalCost`, `removeExternalCost`.
- Simpan/State: `save()`, `confirm()` (set `stat_confirm`/`sub_state=SUBMITTED`, kunci form), `cancel()`/`openCancelForm`/`dismissCancelForm`, `markLost()`/`openLostForm`/`dismissLostForm` (sub_state=LOST, dipisah dari CANCELLED untuk laporan win-rate).
- `sendToProDev(int $index)` — Mode #1 link Inquiry->ProDev: seed `ProdProduct` draft (kalau `parent_product_id` belum ada) + `ProdPart` dari dims kasar, salin referensi artwork ke `ProdPartAttachment`, redirect ke `production.prodev`. Wajib Inquiry sudah `stat_confirm`.
- `#[Computed] wizardSteps()`, `theme()`, `partSummary(IqpPart $part)`/`partSummaries()` (data untuk `<x-inquiry.readonly-summary>`), `customerResults`, `salesPersonResults`, `productTypeOptions`, `finishingTypeOptions`, `artworkMaxMb`.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry` (`iqp_inquiry`, sdi) — header utama, relasi `parts`, `tiers`, `toolings`, `externalCosts`, `customer` (lintas-DB pola 1).
- `App\Models\Sdi\IqpPart` (`iqp_part`, sdi) — per part, kolom `dieline_spec` (JSON DSL beku), `prod_part_id` (link setelah `sendToProDev`).
- `App\Models\Sdi\IqpPartMaterial` (`iqp_part_material`, sdi), `App\Models\Sdi\IqpPartProcess` (`iqp_part_process`, sdi, dipakai utk Finishing Tambahan), `App\Models\Sdi\IqpTier` (`iqp_tier`, sdi), `App\Models\Sdi\IqpTooling` (`iqp_tooling`, sdi), `App\Models\Sdi\IqpExternalCost` (`iqp_external_cost`, sdi).
- `App\Models\Master\CustCustomer` (`cust_customer`, mst, lintas-DB) — search/quick-add.
- `App\Models\Master\MatRaw` (`mat_raw`, mst, lintas-DB) — autocomplete bahan.
- `App\Models\Master\ProdType`/`ProdTypeSize` (`prod_type`/`prod_type_size`, mst) — Tipe Produk, dieline DSL, preset ukuran.
- `App\Models\Master\ProdProduct`/`ProdPart`/`ProdPartAttachment` (mst) — dibuat oleh `sendToProDev()`.
- `App\Models\Master\RefFinishingType` (`ref_finishing_type`, mst) — dropdown Finishing Tambahan.
- `App\Models\Master\SalesPerson` (`sales_person`, mst) — picker PIC Sales.
- `App\Models\Sys\SysConstant` — `INQUIRY_ARTWORK_MAX_MB`.
- Query lintas-DB semua pola 1 (Eloquent relationship/query terpisah), termasuk validasi `exists:tm2mst.cust_customer,id_customer`.

## Alur / Business Logic Penting
- **Snapshot beku**: `dieline_spec` (DSL prod_type + dims dipilih user) dibekukan ke `iqp_part.dieline_spec` saat simpan — bukan re-join ke `prod_type` (master boleh berubah, transaksi lama tetap).
- **Penomoran**: `IQ`+YY+5-digit, counter per tahun, `lockForUpdate()` saat generate.
- **Confirm = kunci total**: `stat_confirm=true` -> `readOnly` mengunci SELURUH form (bukan berdasar teks `sub_state`).
- **Gerbang Peran Part**: dimensi & preview dieline live baru aktif setelah `part_role` terisi (kecuali part lama yang sudah tersimpan sebelum fitur ini ada — ditampilkan apa adanya). Tipe non-multi-part auto-MonoForm.
- **Tipe Produk level Inquiry**: 1 tipe berlaku semua Part (koheren karena template multi-part yang didukung — Box with Lid/Round Box/Shallow Box — generatornya memang dipakai bareng Body+Lid).
- **Engine suggests, officer confirms**: saran ukuran (dari DPI artwork), warna (heuristik pixel GD), bahan & finishing (histori Inquiry Tipe Produk sama) — semua tombol "Pakai", tidak auto-terapkan.
- **Mode #1 ProDev**: `sendToProDev()` adalah SATU-SATUNYA jalur kode yang membuat `prod_part` dari Inquiry (gap CLAUDE.md §4 lama sudah ditutup) — wajib Inquiry `stat_confirm` dulu (spec harus FIX).
- **crossView**: dipaksa read-only total lintas navigasi (dari ProDev/Costing/Master Card); properti Livewire SENGAJA dinamai `crossView` bukan `view` — nama `view` bentrok dengan variabel internal Livewire/Blade (`View` instance di-`extract()` ke scope template, `EXTR_SKIP` menutupi properti publik, menyebabkan kedua cabang `@if` render bersamaan — bug nyata 2026-09-04).
- **markLost vs cancel**: LOST (kalah bersaing, dipakai laporan win-rate) terpisah dari CANCELLED (batal sepihak internal) — boleh dari `sub_state` manapun selama `stat_confirm` & belum terminal.

## Catatan
- Galeri visual popup dieline (ala Pacdora) sementara dilepas dari scope saat pemindahan Tipe Produk ke level Inquiry — belum dibangun ulang di lokasi baru.
- `part_role` TIDAK punya kolom fisik di DDL — disimpan hanya di state Livewire (dipakai untuk generate nama beku), bukan dipersist ke `iqp_part`.
- `flat_manual` juga tidak ada kolom di DDL — heuristik: tipe tanpa `dieline_spec.elements` dianggap manual.
- Mockup generator 3D (Pacdora-style upload+composite) BELUM ADA sama sekali di layar ini (0 upload capability) — di luar scope sesi ini, butuh keputusan desain terpisah (library sendiri vs API pihak ketiga).
- Skema PPN/PPh23 dan Diskon Dokumen sengaja disembunyikan dari UI (properti/kolom DB tetap ada) — dipindah nanti ke Customer Order.
- Jenis Packing dan estimasi kecepatan (F15) masih gap/menyusul sesuai CLAUDE.md §5.
