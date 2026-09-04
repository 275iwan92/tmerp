---
name: flow_procurement
description: Alur PR -> PQ -> PO -> PRT -> PV dengan approval merged-into-entry, plus laporan Outstanding
---

# Flow: Procurement

Koneksi `pur` (tm2pur). Pola konsisten "approval merged-into-entry" (griduistandard.md, 2026-08-22): layar approval murni ANTRIAN (grid + klik 2x buka dokumen), keputusan approve/reject/hold selalu dilakukan di layar entry dokumen aslinya — bukan di layar approval terpisah (pola sama ME29N SAP / action pane Dynamics 365 / Odoo).

## Tahap -> Layar -> Aktor

| Tahap | Layar | Aktor |
|---|---|---|
| 1. Ajukan kebutuhan beli | `screen_pur_pr_index` (+ New Record via picker Departemen) → `screen_pur_pr_entry` | Staf departemen mana pun |
| 2. Approval PR | `screen_pur_pr_approval` (antrian) → keputusan di `screen_pur_pr_entry` | Approver (sesuai `FlowEngineService`) |
| 3. Cetak PR (setelah APPROVED) | `screen_pur_pr_print` | Staf pengaju/Purchasing |
| 4. Bandingkan penawaran supplier | `screen_pur_pq_index` → `screen_pur_pq_entry` → `screen_pur_pq_print` | Purchasing |
| 5. Terbitkan PO ke supplier terpilih | `screen_pur_po_index` → `screen_pur_po_entry` | Purchasing |
| 6. Approval PO | `screen_pur_po_approval` (antrian) → keputusan di `screen_pur_po_entry` | Approver |
| 7. Cetak PO | `screen_pur_po_print` | Purchasing |
| 8. Terima barang di gudang | `screen_pur_prt_index` → `screen_pur_prt_entry` | Gudang/Purchasing |
| 9. Approval PRT | `screen_pur_prt_approval` (antrian) → keputusan di `screen_pur_prt_entry` | Approver |
| 10. Bayar ke supplier | `screen_pur_pv_index` → `screen_pur_pv_entry` | Finance/Kasir |
| 11. Pantau dokumen belum selesai | `screen_pur_report_outstanding-po`, `screen_pur_report_outstanding-pr`, `screen_pur_report_outstanding-pv` | Purchasing Manager, Finance |

## Catatan
- PR dokumen INTERNAL (departemen→approval), PO/PRT dokumen yang melibatkan pihak eksternal (supplier) — kolom identitas grid PR pakai Departemen (bukan Supplier) sebagai konsekuensi.
- PR bisa lahir `MANUAL` (staf input sendiri) atau `MRP AUTO` (auto-generate dari proses MRP — MRP sendiri sebagian besar belum dibangun, lihat BACKLOG.md §6 F10-F14).
- Purchase Receive baru cover "Receiving dari PO" (PRT existing) — "Receiving dari Customer" (consignment) dan "Receiving dari Subkontraktor" (SFG kembali) belum dibangun (BACKLOG.md §4).
- Status siklus dokumen konsisten pola `stat_confirm`/`stat_cancel`/`stat_close` atau enum status (DRAFT/SUBMITTED/APPROVED/dst) tergantung tabel — approval matrix via `FlowEngineService` lintas semua 4 jenis dokumen (PR/PO/PRT/dan turunannya).
