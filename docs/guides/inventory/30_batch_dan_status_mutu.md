# Batch dan status mutu: dari mana batch lahir dan apa artinya statusnya

*Panduan untuk Jo, tim QC, dan staf gudang. Ditulis dari membaca kode dan layar (bukan dari dokumen lama), 16-Oct-2026. Hal yang tidak bisa saya pastikan ditandai "belum terverifikasi" dan dikumpulkan di bagian 7. Rincian teknis ada di Technical Guide: `docs/tg/inventory/batch-quality/10_card_batch-master.md` dan `11_card_conditional-stock-and-urgent.md`.*

## 1. Apa itu batch

Satu **batch** adalah sekelompok barang dengan asal dan mutu yang sama untuk satu item: kelas stok (Raw Material, Semi Finished Goods, atau Finished Goods), item, dan **lot dari supplier**. Setiap batch punya nomor otomatis berformat `BT` + 2 digit tahun + 5 digit urut, misalnya `BT2600032`. Nomor ini dibuat sistem, tidak diketik.

Aturan pentingnya: untuk satu item, satu lot hanya punya **satu** batch. Huruf besar-kecil dan spasi di tepi tidak dibedakan (`ab-12` dan ` AB-12 ` dianggap lot yang sama). Lot kosong dicatat sebagai batch bernama `NO-LOT` (nama ini bisa diubah di Settings > Constants, kunci `INV_NO_LOT_BATCH_CODE`). Supplier **tidak** ikut menentukan keunikan: dua supplier yang kebetulan memakai nomor lot sama untuk item yang sama akan berbagi satu batch.

## 2. Lima status dan artinya

Status batch menentukan apakah stoknya dihitung **Available** dan boleh diambil (dialokasikan) untuk pick list.

| Status | Dihitung Available | Boleh diambil | Artinya |
|---|---|---|---|
| Quarantine | tidak | tidak | menunggu sampling, belum boleh dipakai |
| Conditional | ya | ya, bertanda | boleh dipakai sambil sampling berjalan (penerimaan URGENT) |
| Released | ya | ya | lolos, stok normal |
| Rejected | tidak | tidak | ditolak QC |
| Blocked | tidak | tidak | ditahan QC tanpa klaim |

Dua catatan jujur dari kode: (a) batch **Conditional** tidak boleh dipakai untuk pekerjaan kelas A, yaitu bahan food grade atau bersertifikat FSC / PEFC; (b) stok lama yang belum punya batch dianggap boleh dipakai. Status **Quarantine** ada di semua layar dan aturan, tetapi tidak ada langkah di sistem yang membuat batch menjadi Quarantine; di data contoh juga tidak ada batch Quarantine (lihat bagian 7).

**Status tidak diketik.** Di form Batch, kolom Status hanya dibaca dengan petunjuk "Set by the quality decision, not typed". Status hanya berubah lewat keputusan QC (lihat panduan `31_sampling_qc_dan_keputusan.md`).

## 3. Dari mana batch lahir

1. **Penerimaan barang dari supplier (PO) yang diposting.** Ini jalur utama. Untuk tiap baris barang stok yang diterima, sistem memakai batch item + lot yang sudah ada atau membuat batch baru:
   - Baris **biasa**: batch lahir **Released**, dan sistem mencatat otomatis satu keputusan "Sampling at dock / Released" supaya riwayat lulus supplier terisi. (Hanya untuk batch yang benar-benar baru.)
   - Baris **URGENT** yang mutunya masih menunggu: batch lahir **Conditional**, tanpa keputusan; sampling menyusul.
   Baris dokumen stok dan kartu stok dari penerimaan itu ikut diberi nomor batch. Kalau pembuatan batch gagal, penerimaan tetap sah dan muncul peringatan "Batch for line ... was not created".
2. **Dibuat tangan di Inventory > Stock > Batch & Quality > Batch** (tombol + New Record).
3. **Dari data lama** lewat perintah `inv:backfill-batch` (sekali jalan, aman diulang): setiap lot di kartu stok dan palet menjadi satu batch berstatus Released dengan catatan "Created from existing lot data". Supplier tidak ikut terisi.

Penerimaan dari **subkon** dan penerimaan **bahan titipan customer** tidak membuat batch.

## 4. Langkah: melihat dan membuat batch

**Melihat daftar.** Buka Inventory > Stock > Batch & Quality > Batch. Kolom: Batch (dengan lot supplier), Item, Supplier, Production, Expiry, Status, Certification, Food Grade, On Hand (kg / unit), Pallets. Kotak statistik di atas berfungsi sebagai saringan: All, Quarantine, Conditional, Released, Rejected, dan **Expires soon**. Expires soon hanya untuk batch yang masih ada stoknya dan kedaluwarsanya dalam 30 hari atau kurang (konstanta `WH_EXPIRY_SOON_DAYS`). On Hand dihitung dari kartu stok batch itu (masuk - keluar + penyesuaian), semua pemilik dijumlahkan.

**Membuat batch baru.** Klik + New Record, lalu:
1. Pilih **Class** dan cari **Item Code** (pencarian kode atau nama; Class mengikuti item).
2. Isi Supplier, Supplier Lot, Production Date, Expiry Date, Retest Date.
3. Isi Certification (None / FSC / PEFC / Other), centang Food Grade bila perlu, pilih Grain Direction (hanya untuk kertas).
4. Boleh unggah **Certificate of Analysis** (PDF atau gambar, maksimal 5 MB; berkas baru mengganti yang lama).
5. Klik Save.

Pesan penolakan yang mungkin muncul:
- "Item code :code was not found in :class." (kode item tidak ada di kelas itu)
- "This item already has batch :no for that supplier lot." (lot sudah punya batch)
- "The expiry date is before the production date."
- "This item has a shelf life: fill in the expiry date." (bahan yang di master punya masa simpan wajib diisi kedaluwarsa)

Catatan: batch yang dibuat tangan lahir berstatus **Released** tanpa baris keputusan di riwayat. Kelas, kode item, dan batch yang sudah tersimpan tidak bisa diganti kelas/itemnya; yang boleh diubah adalah supplier, lot, tanggal, sertifikasi, food grade, arah serat, dan catatan. Status Active tidak bisa diubah dari form ini (tombol aktif/nonaktif disembunyikan dan rute menyimpan nilai lama).

**Membaca satu batch.** Form punya tiga tab: *Batch* (data di atas), *Stock of this batch* (saldo per gudang, lokasi, palet, pemilik Company atau Customer, tanggal tiba, jumlah, satuan, dan total on hand), dan *Quality decisions* (keputusan dan hasil First Article). Tanggal kedaluwarsa diberi warna: merah bila sudah lewat ("This batch has expired.") dan kuning bila segera ("This batch expires soon."). Tab History memperlihatkan gerak kartu stok dan tiap perubahan status ("Released → Rejected").

## 5. Akibat status bagi gudang

- Saat pick list disusun, mesin alokasi hanya menawarkan palet dari batch yang boleh dialokasikan, tidak kedaluwarsa, dan milik perusahaan (atau milik customer pekerjaan itu). Batch Rejected, Blocked, atau Quarantine tidak pernah ditawarkan. Detail pick list ada di panduan Warehouse.
- Penempatan (putaway) juga mengikuti aturan yang sama.
- Sistem **tidak memindahkan** stok batch yang ditolak ke area Reject atau QC Hold; itu tindakan fisik yang dilakukan petugas. Yang berubah hanya status dan filter alokasi.

## 6. Penerimaan URGENT: stok boleh langsung ke produksi

Penerimaan URGENT dipakai bila bahan harus langsung ke mesin sebelum hasil mutu keluar. Alurnya di **Procurement > Receive > Purchase Receive**, bagian kartu "URGENT and Sampling":

1. Pada penerimaan yang masih Draft atau QC Pending, pilih baris barang stok.
2. Pilih **Work Order** tujuan dan isi **URGENT Reason** (minimal 5 karakter), lalu klik **URGENT**. Tombol **Clear** mencabut tanda.
3. Yang boleh menandai: PPIC (grup `PROD_PLANNING`), Manajer Produksi (`PROD_MGR`), serta Ops Manager ke atas. Grup `PROD_SPV` (Supervisor Produksi) dikenali kode tetapi belum ada di Setup > Security, jadi belum ada orangnya.
4. Bahan **FSC / PEFC atau food grade** (dari baris itu atau dari batch lama dengan item + lot yang sama) butuh persetujuan **Kepala QC** atau Ops Manager ke atas lewat tombol **Approve**, selama pengaturan `INV_URGENT_FSC_APPROVAL` bernilai 1 (nilainya 1 sekarang). Mengubah alasan atau work order menghapus persetujuan lama.
5. Saat penerimaan diposting, baris URGENT tidak ditahan oleh mutu yang masih Pending. Sistem menahan posting bila: "Line :line is URGENT but has no reason or work order.", atau penandanya tidak berhak, atau "Line :line is FSC / food grade material: the head of QC or an operations manager must approve the URGENT receipt first." QC kuantitas tetap harus selesai.
6. Hasilnya batch **Conditional**. Pantau di Inventory > Stock > Batch & Quality > **Conditional Stock** dan laporan **URGENT Usage** (siapa meminta, untuk WO mana, siapa menyetujui, hasil keputusan terakhir).

Sistem menghitung umur Conditional dalam jam jam-dinding. Setelah 4 jam tanpa keputusan, pemberitahuan lonceng dikirim ke Ops Manager; setelah 8 jam, ke QA, PPIC, dan Ops Manager dengan pesan "A usage decision is required now.". Perintah pengirimnya berjalan tiap jam. Keputusan dibuat di halaman Conditional Stock (panduan 31).

## 7. Belum terverifikasi

- Saya **tidak menjalankan** pengujian maupun perintah yang menulis data; isi panduan ini dari membaca kode dan data contoh (mockup).
- Di data contoh ada 82 batch semuanya Released (81 dari backfill, 81 tanpa supplier); tidak ada batch Conditional atau Quarantine, tidak ada baris URGENT. Layar Conditional Stock dan URGENT Usage belum pernah saya lihat berisi data nyata. Penerimaan PO yang sudah Posted di data contoh tidak memiliki tautan batch.
- Database live (tm2) tidak berisi batch sama sekali; belum jelas kapan backfill dijalankan di sana.
- Batch Quarantine tidak punya langkah pembuatnya; apakah memang dimaksudkan, perlu keputusan Jo.
- Pencarian lot memakai item + lot tanpa supplier; apakah dua supplier boleh berbagi batch belum pernah dibahas dalam dokumen yang saya baca.
- Transfer stok antarbin tidak membawa nomor batch ke kartu baru menurut kode; dampaknya pada On Hand batch belum saya uji (dibahas Technical Guide gudang).
- Apakah penampilan layar sudah sesuai mockup `inv_v201_batch_grid` / `inv_v201_batch_form` tidak saya periksa.
