# GLOBAL_START_HERE — TMERP (baca ini pertama)

> Pintu masuk tunggal seluruh ERP. Sesi baru cukup: **"baca START_HERE"**.
> Pointer — detail di file lain. Baca `GLOBAL_RULES` + `<MODUL>_STATE` sesuai kerja.

---

## ⛔ KEPUTUSAN TERKUNCI — jangan ditanyakan ulang
Melanggar = sinyal saya tak baca file ini, bukan sinyal keputusan perlu dibahas ulang.

- **Stack**: Laravel + Livewire + Blade + Tailwind (compiled via Vite, BUKAN CDN di produksi). Herd (Windows), MySQL 8, 10 DB `tm2*`.
- **ScriptCase = LEGACY-ONLY**: hanya sumber migrasi Version 1. Bukan target, tak dikembangkan lagi. Costing V2 menggantikan engine `tmhpp`.
- **Rantai otoritas** (hilir tak ubah hulu): Inquiry → Imposition+Routing → Master Card → Costing → Quotation/CO → Production.
- **Urutan proses**: COATING DULU baru LAMINASI.
- **Tipe dokumen**: NEW / RECALC / REVISE (REVISE = copy-record, bukan edit confirmed).
- Repo `275iwan92/tmerp`, path `C:\Users\iwanp\Herd\tmerp`, branch kerja `main` (auto Claude Code: `auto/dev`).

---

## Peta modul & submodule (SCOPE BESAR)
Legenda status: 🟢 jalan · 🟡 sebagian · ⚪ belum. Tanda submodule: ✅ konfirmasi · 🔷 usulan (belum dikonfirmasi).

### 01 · SALES 🟡
| Submodule | Ket |
|---|---|
| inquiry_product ✅ | spec produk (NEW/RECALC/REVISE) — paling matang |
| imposition + routing ✅ | Product Designer: tata letak plano, up-count, urutan pekerjaan (BOP) |
| product_costing ✅ | Costing V2 — spec Inquiry/Imposition READ-ONLY; officer pilih baris harga + margin |
| sales_quotation ✅ | penawaran harga jual ke customer |
| customer_order / sales_order ✅ | CO/SO — konfirmasi order |
| sales_delivery_plan ✅ | rencana kirim (jadwal, alokasi) |
| submit_to_production ✅ | serah terima CO → Production |

### 02 · PROCUREMENT ⚪
| Submodule | Ket |
|---|---|
| purchase_requisition 🔷 | permintaan beli (dari MRP/manual) |
| purchase_order 🔷 | PO ke supplier |
| receiving / goods_receipt 🔷 | terima barang |

### 03 · INVENTORY ⚪
| Submodule | Ket |
|---|---|
| item_master 🔷 | data barang/material |
| stock_movement 🔷 | mutasi stok |
| stock_adjustment 🔷 | penyesuaian |
| stock_opname 🔷 | opname |

### 04 · PRODUCTION ⚪ (konsumen Routing/BOP)
| Submodule | Ket |
|---|---|
| mps ✅ | Master Production Schedule |
| mrp ✅ | Material Requirements Planning → feed Procurement |
| capacity_planning 🔷 | kapasitas mesin/lantai |
| production_order / work_order 🔷 | perintah kerja per proses |
| bop_execution 🔷 | eksekusi urutan pekerjaan (Potong→Cetak→QC→Coating→Laminasi→Plong→Kupas→Lem→Pack) |
| production_output 🔷 | pelaporan hasil / WIP |

### 05 · DISPATCH / DISTRIBUTION ⚪
| Submodule | Ket |
|---|---|
| picking_packing 🔷 | ambil & kemas |
| delivery_order (DO) ✅ | surat jalan |
| delivery_note (DN) ✅ | nota kirim |
| shipment 🔷 | CASE cross-CO consolidated (many-to-many CO:DN, harga di level line) |

### 06 · FINANCE & ACCOUNTING 🟡
> RESMI MASUK SCOPE PENUH sejak keputusan user 2026-08-19 (dikonfirmasi ulang 2026-08-26) — lihat `GLOBAL_RULES.yaml → modules_excluded.finance_accounting`. Bukan lagi pengecualian di luar rencana.

| Submodule | Ket |
|---|---|
| chart_of_account ✅ | COA live |
| cash_bank_journal ✅ | BKM/BKK/BBM/BBK (Draft→Post) live |
| journal_voucher ✅ | auto-jurnal posting (PV & Stock Out RM) live |
| invoice / dp_invoice ✅ | DP Invoice Event 1/2/4 live |
| AP 🔷 · AR (lanjutan) 🔷 · general_ledger (Trial Balance) 🔷 · period_close 🔷 | backlog normal, bukan out-of-scope |

### 07 · HUMAN RESOURCE ⚪
recruitment 🔷 · time_attendance 🔷 · payroll 🔷

### 08 · LOOKUP DATA ⚪
global_reference 🔷 · lookup 🔷 · parameter 🔷

### 09 · SECURITY & NOTIFICATION 🟡
user_role_management 🟢 · notification 🔷 · audit_trail 🔷 · opening_app/auth/shell 🟢

---

## Fondasi kode (repo `main`)
Laravel 13 + 10 DB `tm2*` + koneksi multi-DB ✅ · Inquiry pipeline stages ✅ · auth/shell ✅.
Commit terakhir: `Complete inquiry pipeline stages`.

## Struktur file knowledge (prefix nama = "folder")
```
GLOBAL_RULES.yaml        ← universal (konvensi DB/UI, rantai otoritas, urutan proses)
GLOBAL_START_HERE.md     ← file ini (scope besar + keputusan terkunci)
<MODUL>_RULES.yaml       ← aturan khas modul (mis SALES_RULES)
<MODUL>_STATE.yaml       ← progress modul (SATU-SATUNYA yang sering di-update)
<MODUL>_TIER_*.md        ← desain per fitur
```
Aturan: universal → `GLOBAL_`; khas-modul → `<MODUL>_`. Modul menunjuk global, tak menyalin.

## Keputusan menggantung (prioritas)
- **DEC-MARGIN** (Sales): margin per-part vs header — blokir DDL final `costing_part`.

## Cara kerja
Bahasa Indonesia · singkat-padat-jelas · baca file penuh sebelum dokumentasi · konsep sebelum kode ·
sumber kebenaran rumus = kode + test regresi. FIX → Claude Code; design → chat. Update `<MODUL>_STATE` tiap keputusan.
