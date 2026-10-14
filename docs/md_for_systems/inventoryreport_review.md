# Laporan stok semua kelas (ringkas)

> Diringkas 2026-10-14 dari 108 baris (keputusan Jo 13-Okt: ringkas). Salinan penuh: `tmerp_unused/docs/md_for_systems/inventoryreport_review.md`. Dirujuk `EngineRegistry` (engine `StockLedger`) dan `engines.json`, jadi berkas ini dipertahankan. Aturan user: rulebook A.23-A.24; acuan ERP: `inventoryreport_references.md`.

## Laporan (Inventory > Warehouse > Report; mockup.test dan tmerp.test, 2026-10-03)
| Laporan | Isi |
|---|---|
| Stock by Location | stok sekarang per item per bin, terkelompok per gedung; baris merah = dead stock |
| Stock Card | kepala = nama item + periode; rinci Date, Location, BB, In, Out, Adj, EB; baris Total per item. Versi gudang tanpa nilai |
| Stock Recapitulation | satu baris per item: awal, masuk, keluar, penyesuaian, akhir, nilai akhir |
| Stock Summary | nilai per kelas × kategori + porsi % |
| Stock Opname | qty sistem, qty fisik, selisih, nilai per dokumen per item (termasuk Draft / menunggu approval) |
| Stock Loss & Gain | selisih lebih / kurang per item per bulan (hanya penyesuaian Posted) |
| Stock in Inspection | menunggu QC, ditolak belum diretur, area QC Hold, bin diblokir |

Cara pakai: ubah **Period**, klik **Show**; bar saringan, **Group**, **Columns**; **Print** = A4 landscape. Stock Card dibatasi 20.000 baris di layar.

## Dari mana angkanya
- Semua angka periode dihitung satu tempat: `App\Services\Inventory\StockLedger` (Developer > Engines).
- Saldo awal = baris kartu stok terakhir tiap item per bin sebelum tanggal awal; saldo akhir = baris terakhir periode.
- Nilai pergerakan = nilai posisi sesudah baris dikurangi sebelum baris (harga rata-rata bergerak), sehingga awal + masuk − keluar + penyesuaian = akhir selalu cocok.
- Stock Transfer bukan masuk / keluar item (A.22): tidak dihitung di rekap; di Stock Card per lokasi = Out di asal + In di tujuan.
- Dicek di ddt: total nilai akhir Rekapitulasi = total view saldo (457.975.000).

## Keputusan agen yang menunggu koreksi Jo
1. Menu di Inventory > Warehouse > Report. 2. "Summary" = ringkasan NILAI per kelas × kategori; "recapitulation" = satu baris per item. 3. "Inspection stocks" dibaca dua arah: Stock Opname dan Stock in Inspection. 4. Loss & Gain dari penyesuaian Posted, harga rata-rata saat itu. 5. Kolom nilai terlihat semua yang boleh membuka Inventory (batasi ke Finance / Costing / Ops Manager bila perlu, backlog 05.34). 6. Kelas ditulis lengkap. 7. Cetakan tanpa salinan online + QR. 8. Penanda tangan cetakan perkiraan: Prepared By (Warehouse), Checked By, Approved By.

## Data dummy dan catatan
- Data contoh hanya di mockup.test: `php artisan db:seed --class=UatStockReportSeeder --env=mockup --force`, sesudah `UatWarehouseSeeder`. Set dummy yang dipulihkan tidak memuatnya kecuali set `stock-reports`.
- Sisa dummy lama (bukan salah laporan): Body Box dengan saldo berjalan tidak cocok, item bernilai 0 (harga rata-rata kosong), dokumen opname di `WH-TEST-01`, empat dokumen Draft yang sudah punya baris kartu stok (`26OP00014`, `26AD00014`, `26OP00016`, `26OP00018`); dibersihkan bersama backlog 05.17.
- Stock in Inspection membaca QC `PENDING` (bukan kosong); widget Incoming QC di opening board memakai aturan sama.

## Belum dibangun
Umur stok per rentang hari (05.30), analisis ABC (05.31), nilai stok vs saldo akun persediaan (05.32), diterima belum ditagih (05.33), kedaluwarsa / FEFO (05.7), stok dalam perjalanan (05.13), Stock On Hand gabungan Booked / Available / Incoming (05.14), lembar hitung kosong dan berita acara opname (05.36).

## Pertanyaan terbuka untuk Jo
Arti nama laporan (butir 2-3), siapa boleh melihat nilai stok (butir 5), penanda tangan cetakan (butir 8), rentang hari umur stok (0-30 / 31-60 / 61-90 / >90).
