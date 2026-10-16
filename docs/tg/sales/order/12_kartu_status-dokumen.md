# Kartu konsep: Status dokumen Sales dan tanda konfirmasi

> Ditulis 2026-10-16 (kelompok A). Lintas sub-modul Sales; ditaruh di folder Order karena memetakan juga status CO/DO. FAKTA = DB mockup (`ddt*` lewat `tm2*`, `--env=mockup`) dan kode bernomor baris. Kartu Inquiry/Quotation oleh penulis lain tetap rujukan utama tabelnya; di sini hanya status.

- **Jenis dokumen**: kartu konsep. **Modul/sub-modul**: Sales (Inquiry & Quotation, Order, Delivery, CRM).
- **Nama kanonik**: Status dokumen = kolom status/tanda per tabel dokumen; **Tanda konfirmasi (A.20)** = `iqp_inquiry.stat_confirm_sales / stat_confirm_prodev / stat_confirm_procost`.
- **Sinonim**: stat_confirm, doc_status, sub_state, tahap Inquiry, Confirmed/Cancelled/Closed, `stat_cancel`, WON/LOST.
- **BUKAN sama dengan**: log persetujuan (`flow_log`), jejak perubahan bernama `doc_state_log` (tidak ada, lihat Temuan 1).

## Fakta
| Dokumen | Kolom status | Nilai dan sebaran di mockup | Bukti |
|---|---|---|---|
| Inquiry (`iqp_inquiry`, 23 baris: NEW 21, REVISE 2, RECALC 0) | `stat_confirm_sales`, `stat_confirm_prodev`, `stat_confirm_procost` (+ `confirm_*_by/_at`), `stat_cancel`, `sub_state` (DRAFT, SUBMITTED, CONVERTED, LOST, CANCELLED) | CONVERTED 13 (ketiga tanda 1), DRAFT 2, SUBMITTED 7, LOST 1; kombinasi tanda tidak pernah `procost=1` tanpa `prodev=1`, dan `procost=1` selalu punya costing published (13 costing published = 13 CONVERTED) | FAKTA, `show columns`, query; `InquiryStage.php:12-16` |
| Quotation (`quo_quotation`, 20) | `doc_status` (DRAFT, PUBLISHED), `stat_cancel`; WON/LOST TURUNAN, tidak disimpan | PUBLISHED 18, DRAFT 2, batal 0. `outcome()`: WON bila ada item inquiry `CONVERTED`; LOST bila semua item `LOST` | FAKTA, `QuoQuotation.php:71-80`; `Quotation.php:35-42` |
| Customer Order (`co_order`, 25) | `doc_status` (DRAFT, SUBMITTED, CONFIRMED, REJECTED, CANCELLED), `stat_cancel`, `cancel_reason` | CONFIRMED 24 (1 di antaranya `stat_cancel=1`), DRAFT 1 | FAKTA, `CustomerOrder.php:25`; query |
| Jadwal kirim (`co_order_delivery`, 29) | `status` PLANNED / SHIPPED / CANCELLED; `posted_at` | PLANNED 29, posted 25 | FAKTA, query |
| Delivery Order (`do_delivery`, 3) | `status` DRAFT, CONFIRMED, SHIPPED, CANCELLED | DRAFT 1, CONFIRMED 2 | FAKTA, `DeliveryOrder.php:21`; query |
| Baris DO (`do_line`, 0) | `status` PLANNED, SHIPPED, CANCELLED | kosong | FAKTA, `DispatchService.php` |
| Complaint (`crm_complaint`, 4) | `stat_confirm`, `stat_close`, `stat_cancel` (+ `confirmed_at/by`, `closed_at/by`) | Draft 1, Confirmed 2, Closed 1 | FAKTA, `CrmComplaint.php:59-62` |
| POD, Activity | tanpa status dokumen (POD: `result`, `promise_kept`; Activity: `next_done`) | - | FAKTA, `show columns` |

**Cara tanda Inquiry dipasang (A.20)**: `stat_confirm_sales` dipasang layar Inquiry sendiri (`InquiryWriter.php:59`, `InquiryDerivedWriter.php:93`; mencatat pelaku/waktu di `IqpInquiry.php:96-104`). `stat_confirm_prodev` dihitung `InquiryStage::syncProdev`: naik ke 1 bila SEMUA part non-rakitan punya layout terbaru berstatus CONFIRMED; turun ke 0 hanya selama belum ada costing (`:37-50`). `stat_confirm_procost` dihitung `syncProcost` dari `iqp_costing.published_flag` (`:59-69`). Pemicu: event `saved` pada `ProdLayoutGroup` dan `IqpCosting` (`:99-105`). Hanya jenis NEW dan REVISE melewati ProDev (`PRODEV_TYPES`, baris 20); RECALC langsung ke ProCost. Grid tiap bagian = saringan jenis DAN tanda atas tabel yang sama (komentar baris 16).

**Siklus Confirmed / Cancelled / Closed (A.12)**: tidak seragam. Lihat Temuan 2.

## Relasi
- **sumber**: layar tiap dokumen; `InquiryStage` untuk dua tanda hilir; `FlowEngineService` (`flow_log`, tm2mst) untuk approval CO/PR/PO; `CoOrderBuilder` untuk transisi CO.
- **dipakai_oleh**: grid ProDev/ProCost (saringan), Customer 360 (menghitung quotation/CO terbuka), Material Planning (CO Confirmed = firm), widget board.
- **mengalir**: Inquiry (Sales -> ProDev -> ProCost) -> Quotation PUBLISHED -> WIN (inquiry CONVERTED) -> CO -> jadwal -> DO -> POD. Detail engine perpindahan: kartu `20_engine_co-order-builder.md`.
- **zona beku**: Inquiry/ProDev/ProCost dan semua flow dokumen.

## Temuan (dicatat, tidak diperbaiki)
1. **`doc_state_log` tidak ada.** CLAUDE.md butir 5 ("transisi di `doc_state_log`") dan komentar migration lama menyebut tabel itu; pencarian `information_schema` seluruh server (nama berisi `doc_state`/`state_log`) hanya menemukan `iqp_costing_state_log` (0 baris, kolom `from_state`, `to_state`, `changed_at/by`). Jejak yang nyata: `flow_log` (approval), `co_delivery_negotiation` (CPDEL), `confirmed_at/closed_at` (Complaint), `confirm_*_by/_at` (Inquiry). Tidak ada log transisi umum. FAKTA.
2. **Tiga gaya status** berdampingan: (a) `stat_confirm/_close/_cancel` terpisah (Complaint, Inquiry sebagian); (b) satu kolom `doc_status`/`status` + `stat_cancel` (Quotation, CO); (c) hanya `status` (DO, jadwal). Tidak ada `stat_close` di Quotation, CO, DO. Aturan A.12 (tiap modul jelas Confirmed/Cancelled/Closed) hanya penuh di Complaint. FAKTA.
3. **WON/LOST disimpan di Inquiry, bukan Quotation**: status Quotation bergantung pada `sub_state` inquiry anak-anaknya (`outcome()`); Aturannya: WON bila ADA satu item `CONVERTED`; LOST hanya bila SEMUA item `LOST`; selain itu `null` (masih terbuka). Pemakai: `Quotation.php`, `CustomerOrder.php`, `Crm.php`. Tidak ada tes yang memanggil `outcome()` (grep `tests/`: nol). FAKTA.
4. **Penurunan `stat_confirm_prodev` dibatasi**: tanda turun hanya bila belum ada baris `iqp_costing`; sesudahnya tetap 1 walau layout diubah (`InquiryStage.php:47`). Sesuai komentar kode, bukan dokumen aturan (A.20 menyebut "Pro-Dev Confirm part demi part"). FAKTA.
5. **Penamaan**: CO memakai `doc_status`, bertentangan dengan butir penamaan CLAUDE.md (`stat_confirm/stat_cancel/stat_close`); sama untuk DO. Satu-satunya tanda berbentuk `stat_*` di `co_*`/`do_*` adalah `stat_cancel` CO. FAKTA.
6. **Data**: CO2600021 `CONFIRMED` + `stat_cancel=1` (kartu CO temuan 1); DO CONFIRMED tanpa baris (kartu DO temuan 1). FAKTA.

## Sudah ada? Dicari di mana
Dicari: kolom `stat_*`, `doc_status`, `sub_state`, `status` di `ddtsdi`, `ddtmst`, `ddtsys`; tabel `%state_log%`/`%doc_state%` di seluruh server; `InquiryStage`, `IqpInquiry`, `QuoQuotation`, `CoOrder`; CLAUDE.md dan `docs/ringkas/globaluserrules.md` (A.12, A.20). Hasil: tanda A.20 SUDAH ADA dan konsisten dengan data; tabel `doc_state_log` TIDAK ADA; tidak ada kolom baru diusulkan.
