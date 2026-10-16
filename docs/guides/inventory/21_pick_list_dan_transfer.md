# Gudang: Pick List, Stock Transfer, dan In Transit

*Panduan pengguna untuk Officer Gudang, Supervisor Gudang, dan PPIC. Ditulis 16 Oktober 2026 dari membaca kode dan data mockup.test. Layar tidak dibuka di browser dan tes tidak dijalankan; hal yang belum terbukti ada di bagian 9.*

Dua pekerjaan memindahkan stok dari bin: **Pick List** (mengambil bahan untuk Work Order) dan **Stock Transfer** (memindahkan antar bin atau antar gedung). Aturan teknis: `docs/tg/inventory/warehouse/13_card_pick-list.md` dan `15_card_stock-transfer-and-in-transit.md`.

# Bagian A. Pick List

## 1. Gambaran

| Langkah | Siapa | Layar | Hasilnya |
|---|---|---|---|
| 1 | PPIC | Production > Planning: **Material Booking** di-Confirm | Kebutuhan bahan per Work Order tercatat |
| 2 | Officer Gudang | Warehouse > **Pick List**: **+ New Record** | Pick List berstatus *Draft*; sistem mengalokasikan palet, batch, dan bin |
| 3 | Officer Gudang | Pick List: pindai palet, lalu bin | Baris menjadi *PICKED*; status *In progress* |
| 4 | Officer Gudang | **Confirm Pick** | Dokumen **Stock Out RM** ke Work Order terposting; status *Done* |

## 2. Membuat Pick List

1. Buka **Warehouse > Pick List**, tekan **+ New Record**.
2. Pilih **Work Order**. Daftar hanya berisi Work Order yang belum selesai, punya **Material Booking Confirmed**, dan belum punya Pick List terbuka. (Booking boleh milik WO itu sendiri atau milik Customer Order sumber WO.)
3. Pilih **Deliver To** (bin Staging produksi) atau biarkan kosong: sistem memakai bin Staging pertama di gedung asal bahan. **Catatan:** Deliver To hanya informasi dan tujuan garis di peta; Confirm Pick tidak memindahkan stok ke bin itu.
4. Tekan **Create**. Pesan: "Pick list created. Scan each pallet and its bin, then Confirm Pick."

Penolakan saat membuat:

| Pesan | Arti |
|---|---|
| Pick list :no is still open for this work order. | Satu WO hanya boleh punya satu Pick List terbuka |
| There is no confirmed booking for this work order. Confirm the material booking first. | Booking belum Confirm |
| The booking has no material left to pick for this work order. | Semua kebutuhan sudah terpenuhi atau dipegang Pick List lain |
| Work order not found. | WO tidak ada |

## 3. Cara sistem memilih palet

Untuk tiap bahan, sistem hanya mengambil dari palet **ACTIVE** yang berada di bin **boleh dipakai** (tidak diblokir, tidak dikunci hitung) pada area **Storage** atau **Consignment**, dengan syarat:

- Batch harus boleh dialokasikan. **Quarantine, Rejected, Blocked** tidak diambil. Batch **Conditional** hanya untuk pekerjaan kelas B dan C; batch food grade atau bersertifikat FSC/PEFC tidak pernah Conditional.
- Batch yang sudah **kedaluwarsa** tidak diambil.
- Syarat di spesifikasi booking harus cocok: **serat panjang/pendek** (long/short grain), **FSC/PEFC**, **food grade**. Bahan tanpa catatan batch tidak bisa dibuktikan, jadi ditolak bila ada syarat.
- Stok milik pihak lain tidak diambil, kecuali milik customer dari Customer Order itu.
- Urutan ambil mengikuti **Pick Order** area: **FIFO** (paling lama datang dulu) atau **FEFO** (paling cepat kedaluwarsa dulu).
- Palet yang sudah dipegang Pick List terbuka lain dikurangi.

Bahan yang tidak tertutup menjadi satu baris **Short** (merah) dengan catatan "Short ...: no allowed stock left. Open a Supplier Shortage Request or a substitute proposal."

**Penting:** sistem hanya melihat stok yang **sudah berlabel palet**. Barang yang belum dilabel (lihat `20_penerimaan_dan_putaway`) tidak ikut dialokasikan dan tampak sebagai Short.

## 4. Memindai dan Confirm Pick

1. Buka Pick List. Setiap bahan punya kartu "Material" dengan baris palet: Pallet, Batch, Bin, Received, Expiry, Pick Qty, Quality, Grain, Cert., dan kolom **Scan** (*Scan pallet, then bin* / *Pallet scanned, scan the bin* / *Scanned*).
2. Ambil palet dari bin yang tertera. Di kartu **Scan** ketik atau pindai ke **Scanned Code**, tekan **Scan**. Pindai **label palet** dulu, lalu **label bin**. Satu kotak menerima keduanya; kode yang tidak diharapkan ditolak: "Scanned code :code is not expected now. Scan the pallet of an open line, then its bin."
3. Pesan: "Pallet scanned. Now scan its bin." lalu "Bin scanned. Line picked." Pindai pertama mengubah status menjadi *In progress*.
4. Bila salah: "Wrong pallet scanned (:scanned). Take the allocated pallet from bin :bin." atau "Wrong bin scanned (:scanned). The pallet is in bin :bin."
5. Setelah semua baris *Scanned* tekan **Confirm Pick**. Pesan: "Pick confirmed. Stock Out :no posted to the work order."

Confirm Pick **ditolak** bila:

| Pesan | Arti |
|---|---|
| Some lines are short. Reallocate when stock arrives, or cancel this pick list. | Ada baris Short |
| Scan the pallet and the bin of every line first. | Ada baris belum dipindai |
| There is nothing picked yet. | Belum ada baris PICKED |
| The bin :bin is locked for counting. | Bin dikunci Count Task setelah alokasi |
| (pesan posting, mis. saldo kurang) | Stock Out ditolak; draf dibatalkan, tidak ada yang berubah |

Setelah berhasil: satu dokumen Stock Out RM per gedung terposting ke Work Order, tiap palet dilabel ulang dengan sisanya (0 = palet ditutup), dan jumlah yang sudah dikeluarkan di Booking bertambah.

## 5. Reallocate, Cancel, Print

- **Reallocate**: hitung ulang baris yang belum dipindai (stok baru tiba, batch berubah). Baris yang palet-nya sudah dipindai tetap. Pesan: "Allocation recalculated."
- **Cancel** (dengan alasan, wajib): melepas alokasi. Tidak bisa bila Stock Out sudah terbit: "The stock has already been issued for this pick list."
- **Print** di panel atas mencetak Pick List.
- Di **Warehouse Map** pilih **Pick allocation** lalu **Show** untuk melihat bin yang dikunjungi berurutan (1, 2, ...) dan garis putus-putus ke Staging.

# Bagian B. Stock Transfer

## 6. Memindahkan stok

1. Buka **Inventory > Stock > Stock Transfer** (atau tombol **Transfer** pada bin di Warehouse Map; dari Scan Track tombol **Move this Pallet**).
2. **+ New Record**: pilih **From warehouse**, **To warehouse** (sama = pindah antar bin), **Reason** (*Replenish a storage area*, *Consolidate / tidy up*, *Bring to production*, *Move dead stock aside*, *Other*), **Create**. Pesan: "Draft created. Add the items to move, then Confirm."
3. Di **Details** tekan **+ Item**: pilih *Item | Source Bin | On Hand*, pilih **Destination Bin | Free Capacity**, isi **Qty to Move**, **Lot No.** bila perlu. **Transfer Type**: *Direct (one step)* atau *Two-step (ship, then receive)*. **Save**.
4. Kartu **Bins & Capacity** menunjukkan bin asal, bin tujuan, dan bobot/volume tujuan setelah pindah. Tanda: **OK**, **Over capacity** (peringatan, boleh lanjut), **Dead stock** (merah; memindahkan tidak membuat stok "hidup" lagi), **No destination**.
5. **Confirm**. Mode langsung: "Confirmed. The stock has moved; the transfer is ready to print." Nomor `TFyy#####` lahir saat Confirm.

Yang menolak Confirm: "Add at least one line.", "Line :n: fill in the quantity.", "Line :n: choose the destination bin.", destination bin bukan di gedung tujuan atau diblokir, bin tujuan sama dengan asal, ":item: only :qty on hand in the source bin.", aturan Mixing area tujuan, dan "Line :n: the bin :bin is locked for counting."

**Cancel** wajib alasan: stok kembali ke bin asal bila masih ada di bin tujuan. **Delete** hanya untuk Draft; **Duplicate** menyalin ke Draft baru. Tab riwayat dan cetak tersedia di panel atas.

### Persetujuan (bila diaktifkan)

Persetujuan Stock Transfer (modul **TF**) **mati** secara bawaan. Bila dinyalakan di Setting Approval, **Confirm** hanya mengajukan: nomor lahir, status *Waiting for approval*, stok **belum** pindah; stok pindah saat level terakhir menyetujui. Pemeriksa memakai **Stock Transfer Approval Center** (Approve, Reject, Hold; Reject dan Hold wajib alasan). Transfer yang ditolak tidak memindahkan apa pun; gunakan **Duplicate**. Bila persetujuan mati, layar Approval Center hanya menampilkan pemberitahuan itu.

# Bagian C. Two-step dan In Transit

## 7. Untuk gedung yang berjauhan

1. Pada transfer pilih **Transfer Type: Two-step**. **Confirm** berarti **kirim**: stok keluar dari bin asal dan masuk bin virtual **In Transit**. Pesan: "Shipped. The stock is in transit: receive it in Warehouse > In Transit."
2. Di **Warehouse > Transfer > In Transit** buka baris. Di kartu **Receive** isi jumlah yang tiba per baris (bawaan = yang dikirim; tidak boleh lebih), tekan **Receive** lalu setujui pertanyaan. Pesan: "Received. Any difference stays in In Transit until it is settled."
3. Bila kurang, status menjadi **Discrepancy** (kartu Task Board prioritas High). Di **Settle Differences** pilih **Loss** (kekurangan dibukukan ke bin virtual Loss; tetap ada di pembukuan sampai Stock Adjustment menghapusnya) atau **Return** (kembali ke bin asal). Setelah semua selesai status **Closed**.
4. Transfer yang masih *In transit* masih bisa di-Cancel (stok kembali); setelah diterima tidak bisa: "A two-step transfer cannot be cancelled after it was received. Make a new transfer instead."

Persetujuan TF tidak berlaku untuk dua langkah.

## 8. Yang perlu diingat

- Pindah bin **selalu lewat dokumen**. Hanya urutan tumpukan (naik/turun di panel bin Warehouse Map) yang tanpa dokumen.
- Palet berlabel ikut pindah bila baris menyebut palet itu, atau seluruh isi posisi pindah, atau jumlah palet tertua pas sama dengan qty. Selain itu palet tetap di bin lama dan selisihnya tampil di Print Pallet Label sebagai pekerjaan label.
- Bin yang sedang dihitung (Count Tasks) menolak transfer, baik sebagai asal maupun tujuan.

## 9. Belum terverifikasi

- Tombol dan teks diambil dari kode; layar tidak dibuka dan tes tidak dijalankan (`PickListServiceTest` 13 tes, `StockTransferServiceTest` 6, `TransitTransferTest` 8).
- Data contoh: semua 16 Pick List berstatus Cancelled (sisa uji pada WO `ZZWO12`), 11 dari 15 transfer menunjuk bin yang sudah dihapus, dan tidak ada transfer dua langkah. Jadi alur nyata Pick List, Receive, dan Approval belum pernah terlihat dengan data hidup.
- Tombol **Back to Draft** dan isi tab riwayat Stock Transfer tidak diperiksa.
- Konfirmasi Pick pada pick lintas dua gedung: kode tidak atomik (lihat temuan di kartu Pick List); jangan dicoba tanpa pengawasan.
- Siapa yang boleh Confirm Pick atau Cancel: hanya hak modul Inventory yang terlihat.
