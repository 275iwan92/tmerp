---
name: screen_pur_pr_entry
description: Form entry Purchase Requisition (PR) — input baris item, submit approval, dan aksi approve/reject/hold digabung di layar yang sama.
---

# Purchase Requisition Entry

**File**: `resources/views/pages/pur/pr/entry.blade.php`
**Route**: `pur.pr.entry` — `/purchasing/pr/{pr}/edit`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Layar entry PR dipakai Departemen Peminta untuk membuat draft PR (header + baris item), lalu mengajukan (`submit()`) ke antrian approval. Layar ini juga menjadi tempat **approver** memutuskan (Approve/Reject/Hold) — bukan di layar terpisah, mengikuti pola "action pane di dokumen asli" (mirip SAP ME29N / Odoo). Field header & baris jadi read-only otomatis begitu status bukan lagi `DRAFT`.

Item bisa ditambahkan dari 2 sumber: **Item Master** (cascading kategori 5-level dari `mat_category`, atau pencarian cepat kode/nama `mat_raw`) atau **Teks Bebas** (`FREE_TEXT`, untuk pembelian sekali pakai seperti mesin/laptop/jasa tak terdaftar — wajib isi departemen & akun COA manual per baris).

## Field & Kolom
**Header** (`updateHeader()`, hanya saat `status === 'DRAFT'`):
- `i_doc_date` — tanggal PR (date-picker)
- `i_department_id` — Departemen Peminta (wajib, dropdown dari `OrgDepartment` where `activate=true`)
- `i_need_date` — tanggal dibutuhkan
- `i_notes` — catatan/justifikasi

**Item picker (tab Item Master)**:
- `itemSearch` — pencarian cepat kode/nama (live debounce 300ms, min 2 karakter) ke `MatRaw`
- `pick1..pick5` — cascading kategori dari `MatCategory::level(1..5)` (parent_id chained); `matchingMaterials()` resolve nama kategori terpilih lalu filter `mat_raw.category1..5` (masih kolom TEXT, bukan FK id)

**Item picker (tab Teks Bebas)**:
- `freeDescription`, `freeUom` — lalu `addFreeText()`

**Baris item** (`PrLine`, editable inline via `updateLine()` field whitelist: `i_description`, `i_qty`, `i_uom`, `i_est_price`, `i_department_id`, `i_coa_account_id`, `i_need_date`, `i_notes`):
- `line_no`, `i_item_source` (MASTER_ITEM/FREE_TEXT), `item_id`, `c_item_code`, `i_description`, `i_qty`, `i_uom`, `i_est_price`, `c_est_subtotal` (dihitung `recalcSubtotal()`), `i_department_id`, `i_coa_account_id`

**Approval**:
- `approvalNote` — catatan (wajib untuk Tolak/Hold)
- `approvalError` — pesan validasi client

## Aksi / Method
- `mount(int $pr)` — set `$prId`
- `pr()` [computed] — load `PrRequisition` + lines terurut `line_no`
- `pendingApproval()` [computed] — via `FlowEngineService::pendingForUser('PR', Auth::id())`, filter dokumen ini
- `approvalHistory()` [computed] — via `FlowEngineService::history('PR', $prId)`
- `departments()`, `coaAccounts()` (dari `tm2acc.fin_account` where `is_postable=1`), `cat1Options()`..`cat5Options()`, `matchingMaterials()`, `itemSearchResults()` — semua computed sumber dropdown/picker
- `setPick(int $level, ?string $value)` — set kategori level & reset level di bawahnya
- `addMasterItem(int $materialId)` — tambah baris dari Item Master, ambil default COA dari `MatCategory` level-1 (`default_coa_account_id`), `is_stock` dari `stock_class !== 'SERVICE'`
- `addFreeText()` — tambah baris FREE_TEXT; wajib `freeAccountId` (akun `is_postable=1`, dipilih di tab Teks Bebas). Tanpa akun: baris tidak dibuat, pesan di `freeError` (kolom `pr_line.i_coa_account_id` NOT NULL). Layar v2 memanggil method yang sama lewat `App\Support\V2\Data\PurchaseRequisition::addFree()` (field `free_account_id`)
- `updateLine(int $lineId, string $field, $value)` — update 1 field baris (whitelist), recalc subtotal. Kolom NOT NULL (`i_description`, `i_qty`, `i_uom`, `i_department_id`, `i_coa_account_id`) tidak bisa dikosongkan — nilai kosong diabaikan; akun harus `is_postable=1`
- `removeLine(int $lineId)` — hapus baris (hanya saat DRAFT)
- `updateHeader(string $field, $value)` — update 1 field header (whitelist)
- `recalcTotal()` [protected] — sum `c_est_subtotal` semua baris → `PrRequisition.c_est_total`
- `save()` — no-op selain refresh computed `pr` (tidak ada perubahan eksplisit, form sudah tersimpan per-field via `updateLine`/`updateHeader`)
- `submit(FlowEngineService $flow)` — validasi ada baris + departemen terisi, generate `doc_no` via `PrRequisition::nextDocNo()`, set status `SUBMITTED`, panggil `$flow->submit('PR', ...)`; jika tidak ada konfigurasi approval aktif, tampilkan `blockedMessage` (dokumen tetap tersimpan SUBMITTED tapi tak masuk antrian siapa pun)
- `approveDoc(FlowEngineService $flow)` — approve step pending; jika `$result['final']` true (flow selesai), panggil `PrAllocationService::createDefaultAllocationsForPr()` untuk siapkan alokasi baris (dipakai PO Entry)
- `rejectDoc(FlowEngineService $flow)` — wajib `approvalNote` terisi
- `holdDoc(FlowEngineService $flow)` — wajib `approvalNote`; status dokumen TIDAK berubah, tetap `SUBMITTED` & tetap di antrian approver yang sama
- `delete()` — hapus PR (hanya saat DRAFT), redirect ke `pur.pr.list`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PrRequisition` — tabel `pr_requisition` (tm2pur/pur)
- `App\Models\Pur\PrLine` — tabel `pr_line` (tm2pur/pur), FK dalam-DB ke `pr_requisition`
- `App\Models\Master\MatCategory` — tm2mst (lintas-DB dari tm2pur, query terpisah)
- `App\Models\Master\MatRaw` — tm2mst
- `App\Models\Master\OrgDepartment` — tm2mst
- `DB::connection('tm2acc')->table('fin_account')` — tm2acc, query raw lintas-DB (bukan Eloquent), ambil COA postable
- `App\Services\Flow\FlowEngineService` — engine approval generik (flow_log dsb, kemungkinan di tm2mst/tm2sys — lihat file service utk detail, TBD - perlu verifikasi lokasi tabelnya)
- `App\Services\Pur\PrAllocationService::createDefaultAllocationsForPr()` — buat `pr_line_alloc` default saat approval final

## Alur / Business Logic Penting
- Status siklus: `DRAFT` → `SUBMITTED` → (`APPROVED` | `REJECTED` | `CANCELLED` | `REVISION_NEEDED`) → `PARTIALLY_ORDERED` → `FULLY_ORDERED` → `CLOSED`. Badge warna beda per grup status (di header).
- Approval memakai `FlowEngineService` generik (bukan kolom `stat_confirm` per tabel PR) — submit/approve/reject/hold semuanya lewat service ini dengan doc-type string `'PR'`.
- Hold TIDAK mengubah status dokumen — beda dari Reject yang mengubah status via `$result['status']`.
- Saat flow approval PR selesai (final), sistem otomatis membuat alokasi baris default (`PrAllocationService`) — dipakai sebagai sumber saat PO Entry menarik baris dari PR.
- Semua field header & baris otomatis terkunci (readonly di Blade via kondisi `$this->pr->status !== 'DRAFT'`) begitu PR bukan DRAFT lagi — tidak ada guard eksplisit tambahan di beberapa method (`updateLine`/`updateHeader`/`removeLine`/`addMasterItem`/`addFreeText`/`delete` semua cek `status !== 'DRAFT'` return early).
- Print PR hanya tersedia begitu status di luar `DRAFT`/`SUBMITTED` (link tombol Cetak PR muncul kondisional).

## Catatan
- Tidak ditemukan komentar TODO/FIXME eksplisit di file ini selain catatan desain (pola approval-di-form yang sama dengan PR).
- `save()` method ada tapi isinya kosong (cuma `unset($this->pr)`) — kemungkinan sisa pola tombol "Simpan Draft" yang sebenarnya tidak perlu aksi eksplisit karena tiap field sudah auto-save per `wire:change`.
