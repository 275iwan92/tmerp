# Arsip CLAUDE.md (dipindah 2026-10-07 demi hemat token; isi asli utuh)

## 5. STATUS TIER 3A (per sesi ini)

**SELESAI & terpasang di MySQL lokal:**
- tm2mst: geo_* + fin_currency + cust_customer + prod_* (product/part/material/process/type/category) + mat_raw + uom_* (16 tabel).
- tm2sdi: `iqp_*` 12 tabel (inquiry/type/tier/part/part_material/part_process/tooling/external_cost/prove/prove_log/costing/costing_detail).
- FK internal (dalam-DB) tegak. FK lintas-DB = logis (Laravel).

**GAP tertunda (tambal via ALTER, non-blocking):**
1. Estimasi kecepatan F15 (est_days/speed/override/status) — ada di mockup, belum di DDL.
2. ~~Rate costing versioned (coating/film/sheet)~~ — **CLOSED**: `mat_coating_rate`/`mat_sheet_rate`/`mat_grammage` + `sys_constant` sudah ada & dipakai `CostingEngine.php` (Costing Hub live di `inquiry.costing`). `product_costing.html` yang tadinya dikira gap ini ternyata duplikat Costing Hub — dibatalkan.
3. Selaras diskon qty (mockup teks vs DDL angka).

---


> **Konvensi nama file `docs/screens/*.md` per-engine (DEC-NAMING-SCREEN 2026-09-05,
> fokus saat ini: inqprod/prodev/procost, berlaku juga utk engine lain nanti):**
> pola `screen_<engine>_<variant?>_<screentype?>.md`.
> - `<engine>` = kode pendek engine sesuai CLAUDE.md §4 (`inqprod`, `prodev`,
>   `procost`, dst) — **bukan** nama route/folder Livewire lama (`inquiry`,
>   `production_prodev`).
> - `<variant>` = doc-type/mode kalau 1 engine punya >1 layar entry yang
>   mengerjakan data beda (inqprod: `new`/`revise`/`recalc`). Tidak ada
>   variant → field ini dilewati.
> - `<screentype>` = **HANYA** ditambahkan kalau utk 1 unit `<engine>_<variant>`
>   yang sama ada LEBIH DARI 1 jenis layar (form/detail vs grid/worklist):
>   `_form` (entry/detail/workbench, dulu tanpa suffix), `_grid` (worklist,
>   dulu `_list`), `_print` (cetak/nota, penamaan lama tetap dipakai). Kalau
>   cuma ada 1 jenis layar utk unit itu (mis. 1 form tanpa grid terpisah),
>   **tidak perlu suffix**.
> - Contoh: `screen_inqprod_new.md`/`_revise.md`/`_recalc.md` (form-only,
>   masing-masing 3 doc-type), `screen_inqprod_grid.md` (worklist GABUNGAN
>   ketiganya — tidak ada `<variant>` karena bukan pasangan 1-ke-1 dgn salah
>   satu doc-type, jadi ditulis `<engine>_grid` langsung), `screen_prodev_form.md`
>   + `screen_prodev_grid.md` + `screen_prodev_print.md`, `screen_procost_form.md`
>   + `screen_procost_grid.md`.
> - **Print/nota lintas-variant** (bukan pasangan 1-ke-1 dgn 1 form/grid,
>   mis. nota cetak yang sama dipakai semua doc-type inqprod) pakai prefix
>   **`printout_`** (bukan `screen_`) + `<engine>` + nama dokumennya:
>   `printout_inqprod_nota.md` (2026-09-05, gantinya `screen_inquiry_nota.md`).
>   `_print` (di bawah prefix `screen_`) tetap dipakai kalau cetakannya
>   memang pasangan 1-ke-1 dgn 1 form/grid tertentu (`screen_prodev_print.md`).
> - **Resolved 2026-09-05:** `screen_inquiry_hub.md` → `screen_inqprod_grid.md`,
>   `screen_inquiry_nota.md` → `printout_inqprod_nota.md`. `screen_inquiry_imposition.md`
>   + `_list.md` (route `inquiry.imposition`) dikonfirmasi user **LEGACY/TIDAK
>   AKTIF** — digantikan `screen_prodev_form.md`/`_grid.md` (route
>   `production.prodev`). Kedua file lama **diarsipkan 2026-10-02** ke
>   `C:\Users\iwanp\tmerp_unused\docs\md_output\screens\` — jangan bangun
>   fitur baru di layar itu.


## 8. MULAI DARI MANA

Modul pertama: **Inquiry** (fondasi seluruh order). Layar pertama: **inquiry_new**.
Urutan: Model (master + iqp) → Livewire inquiry_new (CRUD + dieline preview) →
costing → quotation → proof. Costing engine & rate versioned menyusul.

