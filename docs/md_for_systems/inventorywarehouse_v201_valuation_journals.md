# Inventory v2.01: jurnal valuasi dan akuntansi persediaan

Dibuat 2026-10-09 (sisi valuasi V-03, V-04, V-06, V-07, V-10; keputusan D8, D13, D17, D18). Kode: `app/Services/Inventory/Valuation/*`.
Database: hanya `ddt*` sampai rilis; migration `2026_10_09_000600_inventory_valuation_accounting.php` (tabel, konstanta, akun, menu).
Help (en + id): `resources/data/help/inventory_valuation_accounting.json`. Uji: `tests/Feature/{LandedCost*,GrIrAging,InvReconcile,Obsolescence,InvPeriodService}Test.php`.

Aturan umum: jurnal yang sudah posting **tidak pernah diubah**. Koreksi = jurnal baru (bertumpuk) yang tercatat di `tm2inv.inv_repost_log` dan tampil di laporan Repost.
Semua jurnal lewat `JournalPostingService::post()` (periode buku besar harus OPEN, jurnal harus balance). Akun khusus valuasi dibaca dari konstanta
(Settings > Constants, nilai = KODE akun); akun persediaan / HPP per bahan = akun item, lalu default kategori level 1 (aturan sama dengan `PvPostingService`).

## Daftar jurnal

| # | Kejadian | Kode jurnal / sumber | Debit | Kredit | Catatan |
|---|---|---|---|---|---|
| 1 | Penerimaan barang diposting (sudah ada, `PvPostingService`) | PUR / `PV` | Persediaan (akun item / kategori) | GR/IR Clearing 211900 | nilai = qty diterima x harga PO |
| 2 | Kurang kirim (sudah ada) | PUR / `PV_SHORT` | GR/IR Clearing | Persediaan | qty kurang x harga penerimaan |
| 3 | **Landed cost Confirm** | PUR / `LANDED` | Persediaan (bagian yang masih di stok) + Selisih Landed Cost 518000 (bagian yang sudah keluar) | Hutang Biaya Landed Cost 211950 (total) | tanggal = tanggal dokumen; tidak boleh sebelum tanggal penerimaan atau di periode inventory tertutup |
| 4 | **Landed cost Revise** | PUR / `REPOST` (journal_kind `CORRECTION`) | selisih per akun (Dr bila naik) | selisih per akun (Cr bila turun) | hanya selisih terhadap jurnal 3; jurnal 3 tetap |
| 5 | **Landed cost Cancel** | PUR / `REVERSAL` | kebalikan jurnal 3 | kebalikan jurnal 3 | ditolak bila selisih sudah ditutup ke HPP (jurnal 6) |
| 6 | **Tutup selisih landed cost** (akhir bulan) | GEN / `LANDED_CLOSE` | HPP 511000 | Selisih Landed Cost 518000 | jumlah `amount_to_variance` dokumen Confirmed bulan itu yang belum ditutup |
| 7 | **Penilaian ulang pemakaian** (Average berubah karena transaksi mundur) | USG / `REVALUE` | HPP bila biaya naik, Persediaan bila turun | kebalikannya | `RepostService::revalueIssued()`, satu jurnal per proses hitung ulang |
| 8 | **Provisi stok usang naik** (tombol Post provision) | GEN / `OBS_PROVISION` | Beban Penyisihan Persediaan Usang 620000 | Penyisihan Persediaan Usang 113900 | hanya selisih terhadap posting provisi sebelumnya |
| 9 | **Provisi stok usang turun** | GEN / `OBS_PROVISION` | Penyisihan 113900 | Beban Penyisihan 620000 | pembalikan sebagian |
| 10 | Pembalik umum (`JournalPostingService::reverse`) | jurnal asal / `REVERSAL` | kebalikan | kebalikan | dicatat di Repost bila lewat `RepostService::reverse()` |

Jurnal 1-2 tidak diubah oleh pekerjaan ini. Jurnal 7 tersedia sebagai kelas, tetapi **belum dipanggil** dari `StockPostingService` (lihat "Belum terhubung").

## Landed cost: cara membagi (D17)

1. Biaya dibagi ke baris penerimaan: **By value** (sebanding `qty diterima x harga PO`) atau **By quantity**. Selisih pembulatan masuk baris terakhir sehingga jumlah bagian = total.
2. Per item: rasio masih ada = `min(1, saldo item sekarang di semua bin / total qty diterima item itu pada dokumen)`.
3. `To Inventory = bagian x rasio` (Dr Persediaan), `To Variance = bagian - To Inventory` (Dr Selisih Landed Cost). Average tidak melacak lot, jadi rasio ini cara yang dipakai
   (sama dengan Odoo AVCO / SAP MAP: biaya terlambat hanya menyentuh sisa stok).
4. Contoh: biaya 1.000, baris A nilai 6.000 (diterima 100 kg, sisa 50 kg), baris B nilai 4.000 (sisa penuh). Bagian A 600: 300 ke persediaan + 300 ke selisih. Bagian B 400: semua ke persediaan.
   Jurnal: Dr Persediaan 700, Dr Selisih 300 / Cr Hutang Biaya 1.000.

## GR/IR Aging (laporan, tidak menjurnal)

Sumber: baris stok penerimaan PO POSTED (`pv_line`) dan tagihan yang menunjuk PO (`fin_invoice_receipt_line.po_id`, Tanda Terima Tagihan Confirmed; ditambah `pv_line.c_qty_invoiced` bila terisi).
Per PO, tagihan menutup penerimaan **tertua** lebih dulu. Sisa penerimaan = Received not invoiced (umur dari tanggal terima); kelebihan tagihan = Invoiced not received (umur dari tanggal tagihan tertua).
Keranjang umur 0-30 / 31-60 / 61-90 / 90+ hari terhadap tanggal To. Catatan: nilai tagihan dianggap sebanding dengan nilai terima (PPN / diskon belum dipisah); belum ada tautan tagihan ke baris penerimaan.

## Rekonsiliasi harian (`php artisan inv:reconcile [--date=] [--no-save]`)

`Difference = Buku besar - (Kartu stok + Landed cost belum di kartu)`. Buku besar = debit - kredit akun persediaan kelas itu (`INV_RECON_ACCTS_RM` 113100,113200,113600 / `_SFG` 113400 / `_FG` 113500),
jurnal POSTED sampai tanggal posting. Kartu stok = `StockLedger` posisi akhir hari. Status DIFF bila |selisih| > `INV_RECON_TOLERANCE` (IDR 1). Hasil: `tm2inv.inv_reconcile_log` (satu baris per hari + kelas, dijalankan ulang = menimpa).
**Tidak dijadwalkan** di `routes/console.php` (menunggu keputusan user).

## Provisi stok usang

Umur = hari sejak pergerakan terakhir item selain transfer (`last_item_movement`, sama dengan laporan Slow Moving); tidak pernah bergerak = Dead. Kelas: `>= INV_AGING_SLOW_DAYS` (90) Slow,
`>= INV_AGING_NONMOVING_DAYS` (180) Non-moving, `>= INV_AGING_DEAD_DAYS` (365) Dead. Persen dari nilai stok per kelas: `INV_OBSOLETE_PCT_SLOW` 10, `_NONMOVING` 25, `_DEAD` 50 (nilai awal usulan, ubah di Settings > Constants).
Jurnal hanya saat Post provision; yang dijurnal = provisi yang dituju - total posting terakhir (`inv_obsolete_provision.provision_total`). Foto baris tersimpan di `inv_obsolete_provision_line`.

## Periode inventory (`InvPeriodService`)

Close berurutan (periode sebelumnya harus CLOSED). Open hanya periode CLOSED paling akhir dan hanya bila periode buku besar bulan itu belum CLOSED. Add Next Period membuat bulan sesudah periode terakhir.
CLOSED membuat `StockPostingService` menolak dokumen stok bertanggal di dalam atau sebelumnya (perilaku yang sudah ada).

## Belum terhubung / keputusan terbuka

1. **Nilai landed cost belum ditulis ke kartu stok.** Rantai Average (`StockCardChain` / `ItemAverageCost`, milik pekerjaan Average per item) belum punya baris "khusus nilai" (qty 0, nilai naik). Sampai itu ada,
   landed cost hanya di buku besar; `inv_landed_cost.card_synced = 0` dan rekonsiliasi menampilkannya di kolom terpisah. Bila rantai sudah mendukung, set `card_synced = 1` setelah menulis barisnya.
2. **Penilaian ulang (jurnal 7) belum dipanggil otomatis.** `ItemAverageCost::recalculate()` sudah menghitung `revalued_value`; `StockPostingService` hanya memberi peringatan. Menghubungkan keduanya
   (`RepostService::revalueIssued($materialId, $revaluedValue, $docNo, $login)`) menyentuh berkas pekerjaan Average, jadi diserahkan.
3. Akun baru dibuat migration di `fin_account` bila kodenya belum ada: 211950, 518000, 620000, 113900 (periksa bagan akun sebelum rilis ke tm2acc).
4. Persen provisi memakai tiga kelas dari konstanta aging; tabel lima tingkat di scope (0 / 25 / 50 / 100 persen) tidak dipakai.
