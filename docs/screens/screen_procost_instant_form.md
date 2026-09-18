---
name: screen_procost_instant_form
description: Layar Kalkulasi HPP Mode INSTANT (standalone, tanpa Inquiry) — replika program HPP lama "UPDATE HPP V3.027" + mockup Kalkulasi_HPP_UAT.html; tombol Reverse ke Integrasi.
---

# Kalkulasi HPP — Mode Instant

**File**: `resources/views/pages/inquiry/costing-instant.blade.php`
**Route**: `inquiry.costing.instant` (`/costing/instant/{costing}`), grid: `inquiry.costing.instant.list`
**Acuan**: mockup `docs/mockups/Kalkulasi_HPP_UAT.html` + screenshot program lama (user 2026-09-18); deviasi disetujui = `docs/mockups/procost_instant_deviasi.jpg`; hasil build = `docs/mockups/procost_instant_hasil_build.png`. Rumus = `CostingEngine` (sama dgn Integrasi), verifikasi `procost_formula_library.md` §VII.

## Tata letak (persis program lama)
- Toolbar: ＋ New · Save/Simpan (`data-dirty-save`) · Delete | Hitung HPP · Confirm · Cancel/Batal | ⇄ Reverse (deviasi) · ⟳ · Back (`x-back-button`).
- Header 6 panel: **Identitas Dokumen** (Doc No, Doc Date, Status, Tipe Perhitungan NEW/RECALC/REVISE, No Rev) · **Customer & Product** (Pilih Customer → auto-isi nama, Nama Calon Customer/Lead = `customer_name_draft`) · **Dimensi Produk** (Product Name) · **Dimensi** (P/L/T/Lidah Lem) · **Parameter** (Qty Order, INCLUDE/EXCLUDE PPN, PPN%, Margin%, Tolerance%, Rounding NEAREST/CEILING/FLOOR) · **Note & Attachment** (Notes, Currency — hanya label, belum konversi kurs).
- Bar **Hasil Kalkulasi** (nilai per PC): Price Per Pcs (setelah toleransi+rounding), Total Hpp, Total Material/Process/Tooling, **Total After Margin**, **Total After Ppn**.
- Tab **Parts Details | Info** (Info = audit dokumen).
- Parts Details: toolbar part (New/Save/Delete, Hitung HPP Detail); panel **Input** (field tampil per Tipe Part mengikuti `map_hpp_parts.visible_type` legacy: warna biru=umum, hijau=sheet, oranye=laminasi); panel **Output** (HPP/PC, Description Calc, Jumlah Cetak (LC), Catatan Kalkulasi per part, grid Per PC, Info Harga Supplier); accordion **Detail per Order**; accordion **Coating**; pager part "Part 1 2 … [x of N]".

## Perilaku
- Setiap perubahan field langsung disimpan & dihitung ulang (pola onChange legacy); error engine tampil di banner merah.
- Auto-fill ala Ajax lama: Tipe Part → rate pisau; Gramatur → % DC; Rate SF EF → harga SF; Jenis Join/Packing → ongkos default.
- Confirm → `PENDING_APPROVAL` (read-only); Cancel → `CANCELLED`; Delete hanya saat masih editable.
- Reverse → `ReverseCostingService` (Inquiry BARU + Master Product draft + ProDev DRAFT, angka tetap) lalu pindah ke layar Integrasi.
- Breakdown/HPP dikunci ke `PROCOST_FORMULA_LOGINS`.

## Catatan
- Data dummy "SJJ IB GOLD STAR GM5Z-3B CV" (RAJA BAKERY) = replika legacy hpp_parts #51 → HPP/PC 1.487,4648.
- "Cfg Parts" di program lama belum dibuat (master konfigurasi part belum ada di V2).
- "Info Harga Supplier" dihitung utk DC+SHEET/SHEET (rumus DC+SF di legacy belum bisa diturunkan dari data).
- Test: `tests/Feature/CostingInstantModeTest.php`.
