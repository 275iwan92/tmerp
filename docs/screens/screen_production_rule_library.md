---
name: screen_production_rule_library
description: Constraint Rule Library untuk scheduling (BATCHING/SEQUENCING), master-like dengan gate approval Manager Produksi untuk Aktifkan/Nonaktifkan.
---

# Rule Library

**File**: `resources/views/pages/production/rule-library.blade.php`
**Route**: `production.rule-library` (`/production/rule-library`)
**Koneksi DB utama**: prd (tm2prd) — model `App\Models\Prd\PrdSchedulingRule`; mst (tm2mst) untuk `ProdMachine`/`OrgDivision`
**Modul**: Production Scheduling & Product Development

## Tujuan
Kelola Constraint Rule Library untuk Scheduling Engine (`production_scheduling_tier1.md` §5A.7, tier4.md §1 — "Master-like, tapi butuh approval"). Dua tipe rule: BATCHING (gabung task setipe) dan SEQUENCING (penghalus urutan lokal). Berbeda dari master biasa: toggle-aktif standar diganti tombol Aktifkan/Nonaktifkan yang digate role (Tier1 §9.7: Manager Produksi/Operasional/Admin App ke atas) — siapa saja yang `can_input PROD` boleh membuat rule sebagai DRAFT (Tier2 §9 matrix, PPIC = boleh draft), tapi hanya role tinggi yang bisa mengaktifkan.

## Field & Kolom
Modal form:
- `rule_type` (BATCHING | SEQUENCING).
- `rule_name` (required, max:100).
- `applies_to_machine_id` (nullable, dropdown dari `machines()` — hanya mesin di divisi OFFSET yang aktif).
- `applies_to_process` (nullable text, alternatif ke machine_id).
- `condition_field` (required, max:50, mis. `dieline_ref`).
- `condition_operator` (EQUALS | SORT_ASC | SORT_DESC).

Grid: Nama Rule, Tipe (badge), Berlaku Untuk (machine name atau process atau "Semua"), Kondisi (field+operator mono), Status (DRAFT/ACTIVE/INACTIVE badge), Aksi (Edit + Aktifkan/Nonaktifkan). Stat-row 3 kotak: Total Rule, Aktif, Draft.

## Aksi / Method
- `rules()` [Computed] — with `machine`, order desc.
- `machines()` [Computed] — ProdMachine where `division_id` = divisi kode `OFFSET`, aktif.
- `statTotal()`, `statActive()`, `statDraft()` [Computed].
- `openNew()`/`openEdit(id)`/`closeModal()`.
- `save()` — rule baru SELALU lahir status DRAFT (komentar eksplisit: "ACTIVATE lewat aksi terpisah yang digate role").
- `activate(id)` — cek `Auth::user()->isProdManagerOrAbove()`, jika tidak dispatch toast error; jika ya panggil `PrdSchedulingRule::activate($login)` (method model).
- `deactivate(id)` — cek role sama, update `rule_status = INACTIVE`.

## Tabel & Model yang Disentuh
- `App\Models\Prd\PrdSchedulingRule` → `tm2prd.prd_scheduling_rule` (PK `rule_id`), punya method `activate()` sendiri di model.
- `App\Models\Master\ProdMachine` → `tm2mst.prod_machine` (filter by `division_id`).
- `App\Models\Master\OrgDivision` → `tm2mst.org_division` (lookup division_id dari kode `OFFSET`).

## Alur / Business Logic Penting
Gate approval berbasis role (`isProdManagerOrAbove()`) — bukan lewat toggle biasa seperti master lain, mencerminkan kebutuhan "master-like tapi butuh approval". Rule ini dikonsumsi oleh Scheduling Engine (Tool #2/#4/#10 di ProDev, atau engine scheduling-board) — TBD detail konsumsinya, perlu cek `ImpositionEngine.php`/scheduling service terkait untuk verifikasi lebih lanjut.

## Catatan
- Toast feedback pakai Alpine (`x-on:rule-toast.window`), bukan Livewire flash session biasa.
