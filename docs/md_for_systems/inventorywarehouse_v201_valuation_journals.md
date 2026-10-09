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

Jurnal 1-2 tidak diubah oleh pekerjaan ini. Jurnal 7 dipanggil otomatis oleh `StockPostingService` dan `LandedCostCards` (lihat "Landed cost di kartu stok" dan "Penilaian ulang pemakaian").

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

## Landed cost di kartu stok (2026-10-10)

`LandedCostCards::sync()` dipanggil `LandedCostService` di akhir Confirm, Revise, dan Cancel (dalam transaksi tm2inv). Per baris landed cost satu baris kartu RM **khusus nilai**:
`doc_type = 'VA'` (`StockCardChain::VALUE_ADJUSTMENT`), qty 0, `value_in` = nilai. Migration `2026_10_10_000100_landed_cost_stock_cards.php` menambah `inv_landed_cost_line.card_value / card_rev / card_location_id`
dan menyesuaikan view (`v_inv_movement` membawa nomor landed cost sebagai doc_no baris VA; `v_rm_onhand.last_item_movement` tidak menghitung VA supaya penuaan stok tidak ter-reset).

- **Nilai yang ditulis = selisih**: target (`to_stock` bila Confirmed, 0 bila Cancelled) dikurangi `card_value`. Dipanggil ulang = tidak ada baris baru (idempoten). Kunci unik kartu: `tm2inv.inv_landed_cost_line#<card_rev>` + id baris landed cost.
  Revise = baris selisih (naik / turun); Cancel = baris pembalik (`-card_value`). `card_synced = 1` bila semua baris sama dengan targetnya; kolom "Landed cost not in cards" di rekonsiliasi hanya menghitung dokumen `card_synced = 0`.
- **Bin**: bin dengan saldo terbesar saat baris pertama ditulis, lalu bin yang sama untuk baris sesudahnya.
- **Tanggal kartu** = saat Confirm / Revise / Cancel (atau tanggal dokumen bila lebih baru), bukan tanggal dokumen yang mundur: bagian yang sudah keluar sudah masuk Selisih Landed Cost, jadi pengeluaran lama tidak dihargai ulang (tanpa hitungan ganda).
- **Rantai**: mode BIN dan ITEM menerima VA. BIN: harga rata-rata bin naik (`(saldo x Avg + value_in) / saldo`). ITEM: nilai item dan nilai bin naik sebesar `value_in`, Avg item = nilai / qty. Saldo qty tidak berubah (BB = EB bersambung).
  Bila qty (bin / item) 0, nilai tidak bisa dipikul dan baris dilewati; rekonsiliasi menampilkan selisihnya.
- **Layar kartu stok**: baris VA berlabel `Value adjustment` (bukan Opening), nilainya masuk kolom nilai penyesuaian laporan Stock Summary.

## Penilaian ulang pemakaian (jurnal 7) terhubung

`StockCardChain::recalculate()` kini juga mengembalikan `revalued_docs` (id baris kartu keluar => selisih nilai). `RepostService::revalueFromChain()` menjumlahkan hanya baris pemakaian bahan baku yang dijurnal HPP
(kartu `rm_stock_d`, selain pembalik penerimaan `PV_CANCEL` / `REVERSAL`), lalu memanggil `revalueIssued()` -- satu jurnal USG/REVALUE per item, tercatat di `inv_repost_log`.
Dipanggil dari `StockPostingService::postDocument()` (setelah commit; gagal dijurnal karena akun kosong atau periode buku besar tertutup = peringatan di `journal_warning`, posting stok tetap sah) dan dari `LandedCostCards::sync()` bila ada baris keluar bertanggal sesudah baris nilai.
Tidak ganda: satu dokumen hanya diposting sekali, `LandedCostCards` hanya menulis selisih, dan hanya baris yang harganya benar-benar berubah yang dilaporkan.
Perbaikan ikutan: baris keluar yang baru disisipkan (Avg masih kosong) tidak lagi dilaporkan sebagai "berubah harga" pada mode ITEM.

## Belum terhubung / keputusan terbuka

1. Hitung ulang rantai lewat jalur lain (transfer mode BIN, `StockReversalService`, batch) belum memanggil `revalueFromChain`; hanya `postDocument` dan landed cost.
2. Dokumen landed cost Confirmed yang dibuat sebelum 2026-10-10 (bila ada di tm2) tetap `card_synced = 0`; nilainya perlu ditulis ke kartu dengan memanggil `LandedCostCards::sync()` sekali per dokumen saat rilis.
3. Akun baru dibuat migration di `fin_account` bila kodenya belum ada: 211950, 518000, 620000, 113900 (periksa bagan akun sebelum rilis ke tm2acc).
4. Persen provisi memakai tiga kelas dari konstanta aging; tabel lima tingkat di scope (0 / 25 / 50 / 100 persen) tidak dipakai.
