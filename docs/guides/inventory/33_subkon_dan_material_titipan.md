# Bahan di tangan subkon dan material titipan customer

*Panduan untuk Jo, staf gudang, dan Purchasing. Ditulis dari membaca kode dan layar, 16-Oct-2026. Rincian teknis: `docs/tg/inventory/batch-quality/16_card_subcontract-stock.md` dan `17_card_customer-material.md`. Hal yang tidak bisa saya pastikan ada di bagian 6.*

Dua jenis stok ini sama-sama "bukan di rak biasa", tetapi aturannya berlawanan:

| | Bahan di subkon | Material titipan customer |
|---|---|---|
| Milik siapa | **perusahaan**, tetap bernilai di stok | **customer**, tanpa nilai dan tanpa jurnal |
| Di mana dicatat | bin maya per subkon (`VIRT-SUB-<kode supplier>`) | bin di area Consignment (CONSIGN) |
| Lahir dari | menu Send to Subcontractor | penerimaan barang tipe dari customer |
| Boleh diambil untuk | pekerjaan apa saja (setelah kembali ke bin) | hanya pekerjaan customer pemiliknya |

## 1. Mengirim bahan ke subkon

Syarat: ada **PO Subkon** yang masih terbuka (jenis Subcontract, status Sent atau Partially Received, tidak dibatalkan). Hanya **bahan baku** yang bisa dikirim, **satu bahan per pengiriman** (untuk bahan kedua buat pengiriman baru).

Langkah: Inventory > Stock > Subcontract > **Send to Subcontractor** → **+ New Record**:
1. Pilih **Subcon PO** (daftar "nomor · supplier").
2. Pilih **Material and Bin**: bahan baku di bin gudang nyata (bukan area maya), tampil sebagai "Kode Nama | Bin | stok".
3. Isi **Quantity**, **Due Back** (tanggal harus kembali, boleh kosong), dan Notes.
4. Klik **Send**.

Yang terjadi: dokumen `SC` + tahun + 5 digit (mis. `SC2600001`) dibuat, dan stok dipindah lewat satu dokumen Stock Transfer yang langsung diposting (tanpa persetujuan) dari bin asal ke bin maya subkon. Stok **tetap milik perusahaan dan tetap bernilai**; rata-rata harga ikut pindah; tidak ada jurnal. Pesan sukses: "Sent. :no is now at the subcontractor."

Pesan penolakan: "Choose a Subcon PO that is still open." · "The material was not found." · "Choose the bin the material comes from." · "Fill in the quantity to send." · "Only stock owned by the company can be sent to a subcontractor." · bila stok bin tidak cukup, pesan dari Stock Transfer ("... not enough stock in :bin (:qty on hand).").

## 2. Mengurus bahan setelah di subkon

Buka dokumen dari daftar (Open). Tabel memperlihatkan Sent, Used, Returned, At Vendor (sisa). Untuk tiap baris yang masih ada sisa ada kartu **Settle** dengan kolom *Quantity* dan *Return To Bin*:
- **Return**: bahan belum terpakai kembali ke bin pilihan (bawaan bin asal; daftar bin hanya dari gudang asal). Pesan: "Returned to the warehouse." Kalau bin kosong: "Choose the bin the material goes back to."
- **Used**: bahan terpakai di subkon. Sistem membuat Stock Out RM dari bin subkon dan itu menjadi pemakaian biasa dengan jurnal pemakaian standar; ada konfirmasi "Record this material as used at the subcontractor? It leaves the stock as a normal usage." Pesan: "Recorded as used at the subcontractor."
- Jumlah harus lebih dari 0 dan tidak melebihi sisa: "The quantity must be more than 0 and not more than what is left at the vendor (:qty)."
- Bila semua baris sudah tidak bersisa, dokumen menjadi **Closed** otomatis.

**Terpakai otomatis saat hasil subkon diterima.** Ketika penerimaan dari subkon (Purchase Receive tipe subkon) diposting, bahan dianggap terpakai **sebanding** dengan bagian PO yang diterima: jumlah dikirim × (diterima ÷ jumlah baris PO), paling banyak sisa di subkon. Jika langkah ini gagal, penerimaan tetap sah dan sisa tetap terlihat di Stock at Vendor. Penerimaan subkon juga ditolak bila jumlahnya melebihi sisa PO: "Line :line: the qty exceeds what is left of the Subcon PO (:qty)."

**Batal kirim.** Hanya selama status Sent dan belum ada yang terpakai atau kembali: kartu **Cancel This Send** → isi *Reason* → tombol Cancel; stok kembali ke bin asal ("Send cancelled. The material is back in its bin."). Kalau sudah ada pemakaian atau pengembalian: "This send can no longer be cancelled: material was already used or returned." Tanpa alasan: "Fill in the reason for cancelling."

Status dokumen: **Sent**, **Closed**, **Cancelled**. Kolom *Check = Overdue* muncul bila status Sent dan tanggal Due Back sudah lewat (hanya tanda, tanpa pemberitahuan).

## 3. Laporan Stock at Vendor

Inventory > Stock > Subcontract > **Stock at Vendor**: bahan milik kita yang masih di subkon (dikirim − terpakai − dikembalikan), per subkon dan bahan, dengan nomor kirim, Subcon PO, tanggal kirim, **Age (days)** sejak tanggal kirim, Due Back, dan badge Overdue. Hanya dibaca.

Peringatan dari kode: pengiriman selalu ditautkan ke **baris PO pertama**. Pada Subcon PO dengan beberapa baris, penyelesaian otomatis saat penerimaan memakai baris yang salah. Nomor batch/lot juga tidak ikut tercatat pada pemindahan ke subkon.

## 4. Material titipan customer

**Menerima.** Di Procurement > Receive > Purchase Receive buat penerimaan tipe dari customer ("Penerimaan Bahan Baku Customer") dan arahkan baris ke **bahan baku** di master. Saat diposting, tiap baris bahan menjadi stok Raw Material berpemilik customer itu di bin pertama yang aktif dan bisa dipakai pada area **Consignment**, tanpa harga dan tanpa jurnal. Baris tanpa bahan hanya menjadi catatan, bukan stok.

Bila tidak ada bin yang bisa dipakai di area Consignment: "There is no usable bin in a Consignment area. Add one in Storage Areas and Bins first." dan **tidak ada yang diposting**. Bila posting stok ditolak, draf dokumen stok dibatalkan dan pesan kesalahan ditampilkan.

**Melihat.** Inventory > Stock > Customer Material > **Customer Material**: customer, item, bin, jumlah, satuan, Last Movement, dan Age (days) sejak gerakan terakhir. Dapat disaring per customer. Hanya posisi yang saldonya lebih dari 0 yang tampil.

**Memakai.** Mesin alokasi pick list hanya menawarkan stok titipan untuk pekerjaan **customer yang sama**; stok milik perusahaan boleh untuk siapa saja. Pemilik sebuah posisi (bahan di satu bin) ditentukan oleh kartu stok terbarunya.

**Kurang kirim dari customer.** Customer bisa menjadi sumber Shortage Request (panduan 32). Menurut kode, mengonfirmasinya mengurangi jumlah di penerimaan tetapi tidak mengoreksi kartu stok titipan.

## 5. Risiko yang perlu Jo ketahui
- **Memindahkan stok titipan antar bin (Stock Transfer atau putaway)** menulis kartu baru tanpa pemilik, sehingga bawaan tabel (COMPANY) berlaku. Setelah itu stok bisa diambil untuk pekerjaan siapa saja dan hilang dari laporan Customer Material. Ini terbaca dari kode; saya belum mengujinya di data.
- Bahan titipan dan bahan subkon tidak punya batch, jadi tidak bisa disampling atau ditolak lewat layar QC dan tidak ikut telusur maju.
- Tidak ada layar untuk mengembalikan, menagih, atau memusnahkan material titipan yang lama mengendap (usulan di catatan desain: masa retensi lalu customer memutuskan).

## 6. Belum terverifikasi
- Tidak ada pengujian dan perintah penulis yang saya jalankan. Data contoh: **nol** pengiriman subkon, hanya satu PO Subkon (Sent), **nol** stok milik customer, satu zona Consignment. Seluruh layar di panduan ini belum pernah saya lihat berisi data.
- Zona virtual subkon belum ada di mockup; dibuat otomatis pada pengiriman pertama.
- Tidak ada pengecekan hak khusus: siapa pun yang boleh membuka modul Inventory bisa kirim, kembalikan, pakai, dan batalkan bahan subkon.
- Nama tombol pada penerimaan dari customer dan tipe penerimaan di menu Purchase Receive tidak saya buka di layar.
- Apakah pindah bin ke area virtual atau bin diblokir ditolak aturan Stock Transfer tidak saya periksa.
- Pengiriman SFG atau barang jadi ke subkon belum dibangun; yang ada di Sales adalah Surat Jalan Subkon (dokumen terpisah, tidak tertaut ke menu ini dalam kode).
