---
name: flow_inventory
description: Alur modul Inventory di tm2inv -- Stock In dari Purchase Receive, Kartu Stok, Stock Out (RM/SFG)/Stock Return, Stock Adjustment berapproval, sampai Laporan (Master Stock/Slow Moving).
---

# Alur Inventory (`tm2inv`)

**Modul**: Inventory — 3 kelas stok RM (Bahan Baku)/SFG (Barang Setengah Jadi)/FG (Barang Jadi), 7 layar transaksi/persetujuan + 2 layar Laporan cetak
**Koneksi DB utama**: `tm2inv` (+ `tm2mst` lintas-DB untuk master item/gudang, `tm2acc` untuk auto-jurnal pemakaian, `tm2mst.flow_log`/`flow_config*` untuk approval Adjustment/Opname)
**Sumber acuan**: `inventory_tier4.md`, `inventory_rules.yaml`, `griduistandard.md`

## Ringkasan
Modul Inventory mengelola pergerakan fisik & pencatatan nilai stok untuk 3 kelas item (RM/SFG/FG), masing-masing dilayani via URL `{category}` terpisah tapi 1 file Livewire yang sama (reuse kode, bukan 3 file duplikat). Semua tulis ke `*_stockcard` (ledger resmi) HANYA lewat 2 service: `StockDocumentService` (draft header+baris) dan `StockPostingService` (posting final, hitung saldo berjalan `qty_bb/qty_in/qty_out/qty_adj/qty_eb` + `avg_unitprice` moving-average) — Livewire component TIDAK PERNAH insert langsung ke tabel stok (`inventory_tier4.md §7`). Barang masuk dari Procurement (PV) belum terintegrasi otomatis ke Stock In (v1 manual saja); barang keluar dibedakan tegas per kelas: RM/SFG lewat Stock Out generik, FG HANYA lewat Delivery Order (modul Dispatch, di luar Inventory). Adjustment/Opname adalah satu-satunya alur yang wajib approval berjenjang (nilai absolut selisih menentukan level); Stock In/Out/Return langsung DRAFT→POSTED tanpa approval.

## Tahap -> Layar -> Aktor

| # | Tahap | Layar | Route | Aktor |
|---|---|---|---|---|
| 1 | Barang masuk gudang (manual; dari PV Procurement belum otomatis) | `stock-in.blade.php` | `inv.stock-in.index` (`/inventory/stock-in/{category}`) | Gudang (RM/SFG/FG) |
| 2 | Lihat histori pergerakan 1 item (ledger kronologis) | `print/stock-card.blade.php` | `inv.print.stock-card` (`/inventory/print/stock-card/{category}/{material?}`) | Gudang / PPIC |
| 3a | Barang keluar ke produksi (RM/SFG saja) | `stock-out.blade.php` | `inv.stock-out.index` (`/inventory/stock-out/{category}`) | Gudang |
| 3b | Barang jadi keluar ke customer | (di luar Inventory — Delivery Order, modul Dispatch) | — | Gudang FG / Sales |
| 4 | Retur sisa/cacat dari produksi kembali ke gudang (RM/SFG saja) | `stock-return.blade.php` | `inv.stock-return.index` (`/inventory/stock-return/{category}`) | Gudang |
| 5 | Koreksi stok / hitung fisik terjadwal, wajib approval | `stock-adjustment.blade.php` | `inv.stock-adj.index` (`/inventory/stock-adjustment/{category}/{doc?}`) | Gudang (entri & keputusan approval) |
| 5a | Antrian approval Adjustment/Opname | `stock-adjustment-approval.blade.php` | `inv.stock-adj.approval` (`/inventory/stock-adjustment-approval/{category}`) | Kepala Gudang / Manajer Operasional / Direksi (berjenjang sesuai nilai) |
| 6a | Laporan ringkas saldo per item per periode | `print/master-stock.blade.php` | `inv.print.master-stock` (`/inventory/print/master-stock/{category}`) | Gudang / PPIC / Purchasing |
| 6b | Laporan item bersaldo tapi tak bergerak lama | `print/slow-moving.blade.php` | `inv.print.slow-moving` (`/inventory/print/slow-moving/{category}`) | Gudang / PPIC / Purchasing |

## Alur Detail

1. **Stock In**: Gudang input dokumen masuk manual (`doc_cat` STOCK=penerimaan biasa atau BB=saldo awal), pilih gudang+lokasi per baris, isi qty & harga satuan. Simpan Draft dulu (`StockDocumentService::saveDraft()`, no. dokumen auto `IN/{YY}/{seq}`), lalu Konfirmasi & Posting (`StockPostingService::postDocument()`) — baru di titik ini baris `*_stockcard` benar-benar tercipta dan `cur_stock` master item ter-update. Berlaku untuk ketiga kelas RM/SFG/FG. Sumber PV (Purchase Receive) belum terhubung otomatis ke layar ini — gap yang dicatat untuk dikerjakan terpisah (`sidebarmenustandard.md §6/§6a`).

2. **Kartu Stok**: kapan saja, Gudang/PPIC cari 1 item lalu lihat seluruh baris `v_inv_movement` (view UNION lintas RM/SFG/FG) dalam rentang tanggal — kronologis, tidak sortable (saldo berjalan bergantung urutan), plus saldo awal periode. Ini layar baca-saja (cetak), bukan bagian dari alur transaksi, tapi jadi titik audit utama setiap tahap transaksi di bawah.

3. **Stock Out (RM/SFG) / Delivery Order (FG)**: untuk RM/SFG, Gudang catat barang keluar ke departemen/proses produksi tertentu (`issued_to_dept` wajib), Draft→Posting sama seperti Stock In tapi TANPA approval. Posting RM/SFG otomatis memicu jurnal pemakaian (Dr HPP/Cr Persediaan, `journal_code='USG'`) via `StockPostingService::postUsageJournal()` — baris tanpa akun HPP/Persediaan lengkap di master dilewati dari jurnal (peringatan ditampilkan, posting stok tetap jalan). FG TIDAK PERNAH lewat jalur ini — barang jadi keluar gudang murni lewat Delivery Order (di luar modul Inventory), keputusan bisnis eksplisit bukan gap.

4. **Stock Return (RM/SFG)**: kalau ada sisa RM/SFG yang tidak terpakai atau SFG cacat dari proses produksi, Gudang buat dokumen retur yang WAJIB mereferensikan 1 dokumen Stock Out POSTED (via modal picker, hanya dokumen dengan sisa `qty - qty_returned > 0` yang muncul). Tiap baris punya kondisi GOOD (kembali ke gudang biasa) atau SCRAP (ke lokasi ber-flag `is_usable=false`, keterangan wajib). Posting menambah stok balik DAN mengakumulasi `qty_returned` di baris Stock Out sumber (logika khusus, di luar `StockPostingService` generik) — supaya retur ganda melebihi sisa tercegah di pengecekan berikutnya. Tanpa approval (sumbernya sudah tervalidasi POSTED).

5. **Stock Adjustment / Opname**: berbeda dari 3 alur di atas, dokumen ini (koreksi tunggal `AD` atau hitung fisik terjadwal `OP`, berlaku ketiga kelas RM/SFG/FG) WAJIB approval berjenjang sebelum posting. Gudang isi baris (qty sistem otomatis dari stockcard terakhir vs qty fisik hasil hitung), simpan Draft, lalu "Ajukan Persetujuan" — `FlowEngineService::submit()` mengirim ke step approval pertama yang cocok berdasar `SUM(ABS(qty_physical-qty_system) * unit_price))` (nilai absolut selisih) terhadap threshold `flow_config_step` (Lvl1 Kepala Gudang ≤5jt, Lvl2 Manajer Operasional 5-25jt, Lvl3 Direksi >25jt). Approver melihat dokumen ini masuk antrian di `stock-adjustment-approval.blade.php` (klik 2x membuka layar entri yang sama untuk approve/reject — bukan layar approval terpisah). Approve di level BUKAN-final meneruskan ke step berikutnya; approve di level FINAL langsung memicu `StockPostingService::postDocument()` (baru di sinilah stok benar-benar berubah). Reject (wajib alasan ≥10 karakter) mengembalikan dokumen ke status `REVISION_NEEDED` untuk direvisi & diajukan ulang.

6. **Laporan**: dua laporan cetak baca-saja, sama-sama per kelas stok (`{category}` di URL) dan sama-sama keluarga "Laporan" (header navy+Back, landscape — bukan "Nota"):
   - **Master Stock** (`print/master-stock.blade.php`): ringkasan per item (saldo awal/masuk/keluar/penyesuaian/saldo akhir) dalam rentang tanggal, sumber `v_inv_movement`, dengan indikator "RENDAH" vs `min_stock`.
   - **Slow Moving** (`print/slow-moving.blade.php`): item yang masih bersaldo (`qty_onhand > 0`) tapi `last_movement`-nya melewati ambang 3/6/9/12 bulan pilihan user, sumber view snapshot `v_rm_onhand`/`v_sfg_onhand`/`v_fg_onhand` (BUKAN `v_inv_movement`) karena butuh kondisi SEKARANG, bukan rentang tanggal.

## Catatan Arsitektur Lintas Layar

- **3 kelas stok, 1 file per fungsi**: setiap layar transaksi/laporan di atas melayani RM/SFG/FG lewat parameter route `{category}`, bukan file duplikat per kelas — tapi tetap 3 MENU sidebar terpisah (`sidebarmenustandard.md §5`, keputusan user 2026-08-22) karena record fisiknya memang masuk ke tabel berbeda (`rm_stock`/`fg_stock`/`sfg_stock` dst).
- **Satu-satunya jalur sah menulis stok**: `StockDocumentService` (draft) dan `StockPostingService` (posting final ke `*_stockcard` + sinkron `cur_stock` master item, NULL-safe via `COALESCE`). Tidak ada layar yang insert langsung.
- **FG asimetris dengan sengaja**: Stock In FG ada (barang jadi tetap masuk gudang seperti biasa), tapi Stock Out dan Stock Return FG TIDAK ADA — keluarnya FG murni domain Delivery Order/Dispatch, di luar modul Inventory.
- **Approval hanya di Adjustment/Opname** — Stock In/Out/Return sengaja tanpa approval (In/Out dianggap operasional harian rutin; Return sumbernya sudah tervalidasi POSTED). Adjustment/Opname butuh approval karena mengoreksi saldo sistem berdasar klaim fisik yang belum tentu benar, dengan risiko finansial sebanding nilai selisihnya.
- **SFG/FG masih data uji** pada beberapa layar (Kartu Stok, Slow Moving) — Stock In/Out sungguhan untuk kedua kelas ini belum digarap penuh saat layar-layar tersebut ditulis; RM adalah kelas yang paling matang end-to-end.
- **FlowEngineService lintas-DB**: dipakai Adjustment/Opname (module code `AD`/`OP`), baca-tulis `tm2mst.flow_log`/`flow_config`/`flow_config_step` meski dokumennya sendiri hidup di `tm2inv` — dicocokkan di PHP (bukan JOIN Eloquent lintas koneksi), sesuai pola standar lintas-DB `CLAUDE.md §2`.
