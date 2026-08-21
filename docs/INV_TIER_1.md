# TIER 1 — INVENTORY / STOCK MOVEMENT · BUSINESS CONCEPT & SCOPE

> **Modul:** 02_inventory — submodul `stock_movement`, `stock_adjustment`, `stock_opname`
> **Database:** `tm2inv`
> **Status:** spec baru, disusun 2026-08-21 dari dump produksi `tminv_20260722_2018_sd.sql`
> **Prasyarat baca:** `GLOBAL_START_HERE.md` → `GLOBAL_RULES.yaml` → `INV_STATE.yaml` → `INV_RULES.yaml`
> **Terkait:** `PROC_TIER_1..4` (PV mengisi stok), `TIER_1..4_FLOW_ENGINE` (approval), `TIER_0_GLOBAL_ARCHITECTURE` (10 DB)

---

## 1. RUANG LINGKUP

### 1.1 Yang dikerjakan modul ini

Empat dokumen pergerakan stok, ditambah dua layar baca:

| Dokumen | Isi | Layar |
|---|---|---|
| **Stock In** | Barang fisik masuk gudang | grid + form |
| **Stock Out** | Bahan baku keluar ke Work Order | grid + form |
| **Stock Adjustment / Opname** | Saldo dikoreksi tanpa perpindahan fisik | grid + form + layar persetujuan terpisah |
| **Return from Production** | Bahan kembali dari lantai produksi ke gudang | grid + form |
| Kartu Stok | Riwayat mutasi per item/lokasi | grid baca |
| Saldo per Lokasi | Posisi stok terkini | grid baca |

### 1.2 Yang **bukan** milik modul ini

| Hal | Pemilik |
|---|---|
| Master item / bahan baku (`m_raw_material`, `m_product`, `m_product_parts`) | 09_master_data |
| Master gudang & lokasi (`m_warehouse`, `m_wh_loc`) | 09_master_data |
| Penerimaan barang dari supplier (PV/GRN) | 04_procurement — PV **memanggil** kontrak posting modul ini |
| Barang jadi keluar ke pelanggan | 05_dispatch — Delivery Note |
| Booking / reservasi bahan (`rm_booking`) | 03_production (MRP) — modul ini hanya **membaca** untuk hitung qty tersedia |
| Jurnal persediaan ke GL | 10_finance_accounting — di luar scope fase ini |

### 1.3 Tiga kelas stok

Struktur legacy diadopsi apa adanya (keputusan PROC-C, tidak dibuka ulang):

| Kelas | Header | Detail | Kartu stok | Item |
|---|---|---|---|---|
| **RM** — bahan baku | `rm_stock` | `rm_stock_d` | `rm_stockcard` | `m_raw_material` |
| **SFG** — setengah jadi / WIP | `sfg_stock` | `sfg_stock_d` | `sfg_stockcard` | `m_product_parts` |
| **FG** — barang jadi | `fg_stock` | `fg_stock_d` | `fg_stockcard` | `m_product` (+ `parts_id` opsional) |

Ketiganya berperilaku identik. Seluruh aturan di dokumen ini berlaku untuk ketiganya kecuali dinyatakan lain.

---

## 2. DOKUMEN, PREFIX & KATEGORI

Satu keluarga tabel, empat dokumen — dibedakan oleh pasangan `doc_type` + `doc_cat`:

| Dokumen | `doc_type` | `doc_cat` | `module_code` (flow) | Nomor | Efek kartu stok |
|---|---|---|---|---|---|
| Stock In | `1` | `STOCK` | `IN` | `IN/YY/#####` | `qty_in` |
| Saldo Awal | `1` | `BB` | `IN` | `IN/YY/#####` | `qty_in`, tipe kartu `BB` |
| Stock Out | `-1` | `STOCK` | `OUT` | `OUT/YY/#####` | `qty_out` |
| Stock Adjustment | `-1` | `AD` | `AD` | `ADJ/YY/#####` | `qty_adj` (bertanda) |
| Stock Opname | `-1` | `OP` | `OP` | `OPN/YY/#####` | `qty_adj` (bertanda) |
| Return from Production | `1` | `RETURN` | `RTP` | `RTP/YY/#####` | `qty_in` |

**Catatan `doc_cat = OP`.** Opname dipisah dari Adjustment di header (agar laporan bisa membedakan "hitung fisik terjadwal" dari "koreksi kejadian tunggal"), tetapi pada kartu stok keduanya menghasilkan tipe `AD` — sama seperti perilaku legacy. Perbedaannya administratif, bukan akuntansi stok.

**Format nomor berubah dari legacy.** Legacy memakai `26OU00014`; TMERP memakai pola global `PREFIX/YY/#####` yang sama dengan PR/PO/PV. Dokumen lama tidak dinomori ulang — pola baru berlaku untuk dokumen sejak go-live.

---

## 3. RANTAI DOKUMEN

```
                    ┌─── PV (Procurement) ──────┐
                    │                            ▼
 WO (Production) ───┼──────────────────────►  STOCK IN ──► kartu stok (+)
                    │                            ▲
 Manual (+lampiran)─┘                            │
                                                 │
 WO (Production) ──────► STOCK OUT ──► kartu stok (−)
                              │
                              └──► RETURN FROM PRODUCTION ──► kartu stok (+)
                                        (GOOD → lokasi normal)
                                        (SCRAP → lokasi waste)

 Hitung fisik ──► ADJUSTMENT / OPNAME ──[approval]──► kartu stok (±)
```

**Aturan rantai (mengikuti `authority_chain` global — hilir tak boleh mengubah milik hulu):**

1. **Stock Out wajib menunjuk Work Order.** Tidak ada pengeluaran bahan tanpa WO. Kebutuhan bahan ditarik dari `prd_wo_material`, bukan diketik ulang.
2. **Return wajib menunjuk dokumen Stock Out yang sudah POSTED.** Qty retur per baris dibatasi `qty_keluar − qty_sudah_diretur`. Ini yang mencegah bahan "muncul" dari produksi tanpa asal-usul.
3. **Dokumen POSTED tidak pernah diedit.** Koreksi hanya lewat Adjustment (bila salah qty/saldo) atau Return (bila fisik memang kembali). Tidak ada `REVISE` copy-record di modul ini — dokumen stok merekam **kejadian fisik**, dan kejadian fisik tidak bisa direvisi, hanya dikoreksi dengan kejadian baru.
4. **Pembatalan hanya untuk dokumen DRAFT.** Dokumen POSTED yang keliru dibatalkan dengan dokumen lawan (`CANCELLED` + Adjustment penyeimbang), bukan dengan menghapus baris kartu stok.

---

## 4. KENAPA POSTING PINDAH KE APPLICATION LAYER (INV-01)

Legacy memakai trigger MySQL `trg_rm_stock_confirm` / `trg_sfg_*` / `trg_fg_*`: `AFTER UPDATE`, menyala saat `stat_confirm` berubah 0→1, lalu meng-`INSERT` ke `*_stockcard`.

**Trigger ini di-DROP.** Alasannya bukan preferensi gaya, melainkan tiga cacat yang terbukti:

**a. Double-posting nyata di data produksi.** Dokumen `rm_stock.doc_id = 95` menghasilkan dua set baris kartu stok untuk `reference_detail_id` yang sama:

| `rm_stockcard.doc_id` | dibuat | ref detail | material | qty_bb | qty_out | qty_eb |
|---|---|---|---|---|---|---|
| 39 | 2026-07-17 08:18:11 | 62 | Art Paper | 180,00 | 130,00 | 50,00 |
| 42 | 2026-07-17 08:47:56 | 62 | Art Paper | 50,00 | 130,00 | **−80,00** |

Saldo menjadi negatif karena trigger menyala kedua kali saat `stat_confirm` di-set ulang. Trigger tidak punya penjaga idempotensi, dan tidak bisa punya — ia tak tahu apakah baris untuk detail itu sudah pernah dibuat.

**b. Trigger tidak bisa mengisi lapisan harga.** `inv_stock_layer` (FIFO/LIFO) dan konsumsi layer saat OUT butuh pembacaan berurutan lintas baris; ini di luar kemampuan trigger baris-per-baris tanpa risiko deadlock.

**c. Trigger tidak bisa ikut transaksi flow engine.** Adjustment baru boleh menyentuh kartu stok setelah persetujuan level terakhir. Approval ditulis di `tm2sys.flow_log`, kartu stok di `tm2inv` — dua database. Trigger MySQL tidak bisa membungkus keduanya dalam satu unit kerja; service Laravel bisa.

**Kontraknya tidak berubah:** `stat_confirm = 1` tetap berarti "sudah masuk kartu stok". Yang berubah hanya eksekutornya — `App\Services\Inventory\StockPostingService`. Penjaga idempotensi dipasang di level skema: `UNIQUE (reference_name, reference_detail_id)` pada tiap `*_stockcard`. Kalau service dipanggil dua kali, yang kedua gagal di database, bukan menghasilkan saldo palsu.

---

## 5. LAYAK PAKAI vs AFKIR (INV-02)

Bahan yang kembali dari produksi tidak selalu utuh. Kalau bahan sobek dicampur ke lokasi normal, MRP akan menghitungnya sebagai tersedia dan produksi berikutnya gagal di lantai.

**Keputusan:** afkir tetap **dicatat sebagai stok** (supaya kartu stok jujur dan nilai persediaan benar), tetapi disimpan di **lokasi yang tidak bisa dipakai**.

- `m_warehouse.warehouse_type = 'WASTE'` — kolom `warehouse_type` **sudah ada** di master, tidak perlu tabel baru.
- `m_wh_loc.is_usable` — kolom **[DELTA]**, `TINYINT(1) DEFAULT 1`. Lokasi di gudang WASTE di-seed `is_usable = 0`.
- `v_rm_available` menghitung `qty_available = 0` untuk lokasi `is_usable = 0`. Stok tetap terlihat di `v_rm_onhand` dan kartu stok, tapi tak pernah ditawarkan ke Stock Out atau MRP.

Alternatif "cukup pakai flag pada baris movement" ditolak: flag pada movement tidak ikut ke saldo, sehingga saldo tetap tercampur. Yang harus dipisah adalah **tempatnya**, bukan catatannya.

---

## 6. PERIODE STOK (INV-03)

Legacy memilih saldo awal (`qty_bb`) dengan mencari baris kartu stok terakhir berdasarkan `doc_date`. Konsekuensinya: posting mundur (backdate) menyisipkan baris di tengah rantai dan membuat seluruh `qty_eb` sesudahnya salah, tanpa ada yang menyadari.

**Keputusan:** tabel `inv_period` (`OPEN` / `CLOSING` / `CLOSED`). Posting hanya boleh ke periode `OPEN`. Menutup periode mengunci seluruh kartu stok di dalamnya. Koreksi terhadap periode tertutup wajib lewat Adjustment bertanggal periode berjalan — jejak audit tetap utuh, saldo historis tak berubah retroaktif.

Ini juga yang membuat Stock Opname punya arti: opname mengunci posisi akhir periode.

---

## 7. AKTOR & VISIBILITAS

| Aktor | Kewenangan |
|---|---|
| **Staff Gudang** | Buat & konfirmasi Stock In / Stock Out / Return. Buat (tidak menyetujui) Adjustment & Opname. |
| **Kepala Gudang** | Persetujuan Adjustment/Opname level 1 sampai batas nilai. Persetujuan Stock In manual. |
| **Manajer Operasional** | Persetujuan level 2 tanpa batas nilai. Akses penuh kolom nilai. |
| **Staff PPIC** | Baca saldo & ketersediaan. Menyetujui substitusi material pada Stock Out. |
| **Accounting** | Baca nilai persediaan, kartu stok bernilai, tutup periode. |
| **Direksi** | Persetujuan Adjustment di atas ambang tertinggi. |

### 7.1 Visibilitas harga — Sisi A / Sisi B (WAJIB)

Mengikuti aturan yang sudah terkunci di Procurement (`PROC-Q`):

- **Sisi B — layar gudang:** query **tidak menyertakan kolom harga sama sekali**. Bukan disembunyikan lewat CSS atau `@if` di Blade — kolomnya tidak ikut di `SELECT`. Staff gudang melihat item, qty, UOM, lot, lokasi, kondisi.
- **Sisi A — Manajer Operasional & Accounting:** `unit_price`, `qty_unitprice`, nilai kartu stok, dan lapisan FIFO/LIFO terlihat penuh.

Nilai **selalu tersimpan** di `*_stock_d` dan `*_stockcard`. Yang diatur adalah siapa yang boleh melihat, bukan apakah datanya ada.

---

## 8. INTEGRASI LINTAS MODUL

### 8.1 Procurement (`tm2pur`) — PV mengisi stok
PV tidak menulis ke kartu stok langsung. PV memanggil `StockPostingService::postIn()` dengan menyisipkan `rm_stock` + `rm_stock_d` (`ref_doc_type='PV'`, `reference_name='tm2pur.pv_line'`) lalu memposting dalam transaksi yang sama. Pola penanda legacy dipertahankan: `rm_stock.notes = 'Auto dari PV: <no>'`.

### 8.2 Production (`tm2prd`)
- **Baca:** `prd_wo_material` → kebutuhan bahan untuk Stock Out; `prd_wo` → validasi WO dirilis.
- **Tulis:** hasil produksi (FG) dan output antar-proses (SFG) masuk lewat Stock In `ref_doc_type='WO'`.
- **Umpan balik:** rekonsiliasi pemakaian (keluar − retur) vs standar dikembalikan sebagai data waste riil untuk Product Costing.

### 8.3 Sales / Dispatch (`tm2sdi`)
FG keluar ke pelanggan **hanya** lewat Delivery Note. Layar Stock Out modul ini menolak kelas FG — kalau dibuka, ledger FG terisi dua kali dan angka pengiriman tidak akan pernah cocok dengan tagihan.

### 8.4 Flow Engine (`tm2sys`)
`module_code`: `IN`, `OUT`, `AD`, `OP`, `RTP`. Approval dicatat di `flow_log` (polymorphic), aktor menunjuk `org_position` — bukan username. Badge `🔁 PLT` wajib muncul konsisten di log riwayat dan action bar. Kolom approval legacy (`stat_auth`, `current_approval_level`, `current_approval_login`) **deprecated**, tidak ditulis lagi.

### 8.5 Master (`tm2mst`)
Baca: `m_raw_material`, `m_product`, `m_product_parts`, `m_warehouse`, `m_wh_loc`, `m_uom`, `m_uom_conversion`.
Tulis balik: `m_raw_material.cur_stock`, `avg_unitprice`, `last_unitprice` diperbarui oleh service posting.

---

## 9. NOTA (CETAKAN)

| Nota | Dokumen | Tanda tangan | Sisi |
|---|---|---|---|
| Bukti Terima Barang | Stock In | Penerima · Kepala Gudang | B (tanpa harga) |
| Bon Pengeluaran Barang | Stock Out | Penyerah · Penerima (bagian produksi) | B |
| Berita Acara Stock Opname | Opname | Petugas hitung · Saksi · Kepala Gudang · Manajer Operasional | A |
| Berita Acara Penyesuaian Stok | Adjustment | Pengaju · penyetuju per level | A |
| Bukti Retur Bahan | Return | Bagian pengembali · Penerima gudang | B |
| Kartu Stok | laporan | — | A / B (dua varian query) |

Label tanda tangan diambil dari `flow_config_step.label` — bukan di-hardcode di Blade.

---

## 10. ISTILAH BAKU

| Istilah | Definisi |
|---|---|
| **Movement** | Satu dokumen pergerakan stok (header + detail) |
| **Kartu stok** | `*_stockcard` — satu baris per detail movement, memuat saldo sebelum & sesudah |
| **Posting** | Tindakan membentuk baris kartu stok; ireversibel |
| **On hand** | Saldo fisik di lokasi (`qty_eb` baris kartu stok terakhir) |
| **Available** | On hand − booking terbuka, dan 0 untuk lokasi tak terpakai |
| **Booking** | Reservasi bahan oleh WO (`rm_booking_d`) — belum menyentuh ledger |
| **GOOD / SCRAP** | Kondisi bahan yang diretur; menentukan lokasi tujuan |
| **Layer** | Lapisan harga perolehan per lot (`inv_stock_layer`) untuk FIFO/LIFO |
| **Periode** | Rentang tanggal terkunci untuk posting (`inv_period`) |

---

## 11. DI LUAR SCOPE (FASE INI)

- Jurnal persediaan otomatis ke GL (menunggu 10_finance_accounting).
- Transfer antar gudang sebagai dokumen tersendiri — sementara dilakukan sebagai pasangan Stock Out + Stock In.
- Serial number / barcode scanning.
- Penilaian ulang persediaan (revaluation) dan penyisihan barang usang.
- Konsinyasi dan stok milik pelanggan.
- `fg_stockmst` / `rm_stockmst` (snapshot saldo per periode) — tabel ada di legacy, ditandai "BELUM TENTU TERPAKAI"; **tidak dipakai** di fase ini karena `inv_period` + kartu stok sudah cukup.

---

## 12. REFERENSI

- Dump legacy: `tminv_20260722_2018_sd.sql` (13 tabel, 14 view, 12 procedure, 3 trigger)
- `PROC_TIER_1.md` §5.1–5.2 — adopsi struktur legacy & engine harga perolehan
- `PROC_TIER_3.md` §7.3 — delta `tm2inv` (nilai stockcard, `inv_stock_layer`)
- `TIER_1_FLOW_ENGINE.md` §4 — `module_code` `OP` & `AD` sudah terdaftar aktif
- Mockup: `mockup_stock_in.html`, `mockup_stock_out.html`, `mockup_stock_adjustment.html`, `mockup_stock_return.html`
