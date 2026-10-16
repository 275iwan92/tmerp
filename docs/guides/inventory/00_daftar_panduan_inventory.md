# Panduan Inventory: dari mana mulai

*Ditulis 16 Oktober 2026 dari membaca kode dan data mockup.test. Tiap bab diakhiri bagian "Belum terverifikasi". Aturan teknis untuk agen ada di `docs/tg/inventory/`.*

Menu Inventory punya dua cabang: **Stock** (dokumen, nilai, mutu) dan **Warehouse** (di mana barangnya dan pekerjaan fisiknya). Satu barang melewati keduanya: ia diterima dan diberi batch, diletakkan di bin, diambil untuk Work Order, lalu dicatat sebagai stok keluar di kartu stok.

| Saya ingin... | Baca |
|---|---|
| Mencatat stok masuk, keluar, atau retur dari produksi | `10_stok_masuk_keluar_retur.md` |
| Menyesuaikan stok atau melakukan opname, termasuk approval | `11_penyesuaian_dan_opname.md` |
| Melihat kartu stok, saldo, dan laporan stok | `12_kartu_stok_dan_laporan.md` |
| Memahami nilai stok, periode, landed cost, stok usang, rekonsiliasi | `13_valuasi_dan_periode.md` |
| Menerima barang dan meletakkannya di bin | `20_penerimaan_dan_putaway.md` |
| Mengambil bahan untuk Work Order atau memindahkan antar bin | `21_pick_list_dan_transfer.md` |
| Menghitung stok per area dan memakai Task Board | `22_hitung_stok_dan_tugas_gudang.md` |
| Memakai peta gudang, cetak label QR, dan pindai | `23_peta_gudang_label_dan_scan.md` |
| Menyiapkan gedung, area, dan bin | `24_master_lokasi_gudang.md` |
| Memahami batch dan status mutu (Released, Conditional, Rejected) | `30_batch_dan_status_mutu.md` |
| Memahami sampling QC, keputusan, dan First Article | `31_sampling_qc_dan_keputusan.md` |
| Menangani kurang kirim dan klaim supplier | `32_kekurangan_kirim_dan_klaim_supplier.md` |
| Mengirim ke subkon atau memakai material titipan customer | `33_subkon_dan_material_titipan.md` |

## Urutan baca untuk orang baru
Mulai dari bab 20 (barang datang), lalu 30 (batch), 10 (stok masuk), 21 (pick), dan 12 (kartu stok). Bab 13 untuk Finance dan Direksi.

## Hal yang perlu Jo ketahui lebih dulu
Ringkasan temuan dari penulisan panduan ini (rincian di bagian "Belum terverifikasi" tiap bab dan di kartu TG):
- Stok barang jadi (FG) hanya bergerak lewat Stock In manual, penyesuaian, dan hitung stok. Delivery Order yang Shipped tidak mengurangi stok FG, dan hasil produksi tidak menambahnya.
- Adjustment yang ditolak (Rejected) tidak punya tombol lagi di layarnya.
- "Hold" Work Order dari QC hanya tercatat; belum ada kode produksi atau picking yang membacanya.
- Kunci hitung stok hanya menghalangi transfer, putaway, pick, dan bin default Customer Material, bukan penerimaan barang.
- Data mockup berisi sisa uji (transfer ke bin terhapus, pick list batal, tugas hitung ke area terhapus), jadi alur gudang belum pernah jalan penuh dengan data dummy.
