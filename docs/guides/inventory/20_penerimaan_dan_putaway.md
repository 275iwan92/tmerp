# Gudang: Penerimaan barang dan Putaway

*Panduan pengguna untuk Officer Gudang dan Supervisor Gudang. Ditulis 16 Oktober 2026 dari membaca kode dan data mockup.test, bukan dari dokumen lama. Layar tidak dibuka di browser dan tes tidak dijalankan; hal yang belum terbukti ada di bagian 7.*

Artikel ini menjelaskan apa yang terjadi pada barang sejak Surat Penerimaan Barang (SPB) diposting sampai palet duduk di bin tujuannya: batch lahir, palet diberi label, lalu **Putaway**. Aturan teknisnya ada di Technical Guide `docs/tg/inventory/warehouse/12_card_receiving-and-putaway.md`.

## 1. Gambaran

| Langkah | Siapa | Layar | Hasilnya |
|---|---|---|---|
| 1 | Admin Pembelian / Gudang | Procurement: **SPB** (Posted) | Stok masuk ke satu bin; batch lahir otomatis |
| 2 | Officer Gudang | Warehouse > Labels & Scan > **Print Pallet Label (QR)** | Setiap palet yang datang punya nomor dan label |
| 3 | Officer Gudang | Warehouse > **Receiving & Putaway** | Daftar tugas putaway, lengkap dengan usulan bin |
| 4 | Officer Gudang | Receiving & Putaway: pindai palet, pindai bin, **Confirm** | Palet pindah ke bin; dokumen Stock Transfer terbit sendiri |

Tugas putaway hanya muncul untuk **palet berlabel yang masih berada di area Receiving atau QC Hold**. Barang yang datang tanpa label palet tidak menghasilkan tugas.

## 2. Sebelum mulai

- Hak modul **Inventory** (INV) diperlukan untuk semua layar gudang.
- Area bertipe **Receiving** dan **QC Hold** sudah ada di Storage Areas (di data contoh: gedung A10 punya `RCV` dan `QC`, gedung A5 punya `RCV`).
- Baris SPB harus menyebut bin. Sistem memakai kode lokasi yang diketik di baris SPB; bila kosong memakai lokasi bawaan item; bila keduanya tidak ada, posting ditolak dengan pesan galat. Agar putaway berjalan, bin itu harus berada di area Receiving atau QC Hold.

## 3. Setelah SPB diposting: batch lahir

Setiap baris SPB yang bertanda stok menghasilkan satu **batch** per item dan nomor lot supplier:

- Baris **biasa**: batch langsung **Released** (hasil QC dok sudah diputuskan sebelum posting).
- Baris **URGENT** yang QC mutunya masih menunggu: batch **Conditional**. Barang boleh langsung dibawa ke lini produksi, sampling berjalan paralel.
- Bila pembuatan batch gagal, SPB tetap terposting dan Anda hanya melihat peringatan "Batch for line ... was not created".

Status batch menentukan apa yang boleh dilakukan di Putaway (bagian 5).

## 4. Memberi label pada palet yang datang

1. Dari form SPB tekan tombol ke **Pallet Labels of this Receipt**, atau buka **Warehouse > Labels & Scan > Print Pallet Label (QR)** dan pilih gedungnya.
2. Daftar **Pallet Label Work** menampilkan stok yang belum cocok dengan label: **Arrival label** (barang datang tanpa label) dan **Relabel after use** (isi sudah terpakai).
3. Buka satu baris stok. Di **New Pallets** satu baris = satu palet. Isi **Qty**, salin **Supplier pallet code** seperti tercetak di label supplier, isi **Lot** bila beda, dan **Expiry** (diusulkan dari tanggal datang ditambah umur simpan item; kosong bila item belum punya umur simpan). Tombol **+ Pallet** memecah jumlah.
4. Jumlah semua palet **tidak boleh melebihi** stok yang belum berlabel di bin itu. Bila melebihi Anda melihat ":item: only :qty in this bin is not on a pallet label yet."
5. Pilih **Label size** dan **Print On** (roll atau A4), tekan **Create & Print**. Lembar label terbuka untuk dicetak.

Pilihan **Kind of the new units**: otomatis, **Pallet**, atau **Pack** (sisa). Kertas lembaran yang tinggal 1.000 lembar atau kurang (2 rim) otomatis menjadi **Pack** (batas ini diatur di konstanta `WH_REMNANT_MAX_SHEETS`).

## 5. Mengerjakan Putaway

1. Buka **Warehouse > Receiving & Putaway**. Layar menyusun daftar tugas setiap kali dibuka.
2. Kotak statistik: **All today**, **Waiting sampling**, **Ready to put away**, **URGENT to line**, **Done**. Kolom penting: **Quality**, **Suggested Bin** (beserta alasannya, mis. "empty bin · 1 item/bin · FIFO · capacity 1,500 kg ok"), dan **Action**.
3. Arti kolom **Action**:

| Action | Arti | Boleh dikerjakan? |
|---|---|---|
| **Putaway** | Batch Released; ada usulan bin | Ya |
| **Send to line** | Batch Conditional (URGENT); tujuannya area WIP lalu Staging | Ya |
| **Waiting QC** | Batch Quarantine, menunggu keputusan sampling | Tidak |
| **Not available** | Batch Rejected atau Blocked | Tidak |
| **Done** | Sudah selesai hari ini | - |

4. Tekan **Open** pada baris. Panel menampilkan palet, batch, dan tabel usulan bin (nomor, Bin, Area, Free (kg), Why).
5. Isi **Scan pallet** (pindai label palet atau ketik nomornya, mis. `PL2600001`) lalu **Scan bin** (pindai label bin atau ketik kodenya, mis. `A10-PPR-L01-01`). Bila bin yang dipilih **bukan usulan teratas**, isi **Reason**.
6. Tekan **Confirm**. Pesan sukses: "Pallet :no moved to :bin." Sistem membuat dokumen **Stock Transfer** sungguhan (alasan *Other*, catatan "Putaway WT... / PL...") dan langsung memostingnya; saldo bin asal turun, bin tujuan naik, dan palet ikut pindah. Putaway tidak memakai persetujuan Stock Transfer.

### Aturan yang menolak Anda

| Pesan di layar | Penyebab |
|---|---|
| Scan pallet :no first. The scanned code does not match. | Palet yang dipindai bukan palet tugas ini |
| The scanned code is not a bin. | Kode kedua bukan bin yang dikenal |
| The bin :bin is blocked. | Bin diblokir (Unusable) |
| The bin :bin is locked for counting. | Bin sedang dihitung (lihat `22_hitung_stok_dan_tugas_gudang`) |
| The destination is the bin the pallet is already in. | Bin tujuan sama dengan asal |
| The bin :bin is not in an area where this pallet can go. | Putaway hanya ke area **Storage**; Send to line hanya ke **WIP** atau **Staging**; area virtual ditolak |
| The area of bin :bin does not take this stock class. | Area khusus kelas lain (mis. area FG untuk bahan baku) |
| The bin :bin allows one item only and already holds :item. | Aturan Mixing area **One item per bin** |
| The bin :bin allows one batch only and already holds another batch. | Aturan **One batch per bin** |
| This is not the suggested bin: fill in the reason. | Bin bukan usulan teratas dan Reason kosong |
| The batch is still waiting for the quality decision. | Batch Quarantine |
| The batch is not available (Rejected or Blocked). | Batch ditolak atau diblokir |
| The pallet is no longer where this task expects it. | Palet sudah dipindah orang lain |
| This task is already closed. | Tugas selesai atau dibatalkan |

Tidak ada yang berubah bila Confirm ditolak.

### Bagaimana bin diusulkan

Urutan aturan (sama dengan kotak "How the bin is suggested" di layar): batch harus boleh dialokasikan; area aktif bertipe Storage yang menerima kelas stok; bin aktif, boleh dipakai, tidak terkunci hitung; aturan Mixing; kapasitas berat, jumlah palet, dan batas tumpukan area (kapasitas hanya menggeser usulan, bukan larangan saat memindai); urutan gedung yang sama dengan palet, urutan area, bin kosong lebih dulu, lalu kode bin. Usulan untuk beberapa palet sekaligus saling memperhitungkan, jadi dua palet tidak diusulkan ke tempat yang sama bila bin itu hanya muat satu.

## 6. Melihat di peta

Di **Warehouse > Map & Monitor > Warehouse Map** centang **Layer: Putaway** lalu **Show**: setiap bin usulan tugas putaway terbuka bertanda lingkaran putus-putus **P**. **Task Board** juga menampilkan tugas ini (kartu "PUTAWAY", prioritas **High** untuk URGENT).

## 7. Belum terverifikasi

- Layar belum dibuka di browser; urutan tombol dan teks di atas dibaca dari kode.
- Data contoh tidak punya palet di area Receiving/QC Hold, jadi daftar Putaway kosong dan alur Confirm belum dicoba dengan data nyata. Tes `WarehouseTaskServiceTest` (15 tes) membahasnya tetapi tidak dijalankan di sesi ini.
- Form SPB (Procurement): apakah pilihan bin otomatis mengarah ke area Receiving **tidak diperiksa**.
- Nama persis kolom isian palet baru (Qty, kode palet supplier, lot, expiry) diambil dari data form, bukan dari tampilan layar.
- Pemindai USB/Bluetooth dan kamera HP tidak dicoba.
- Siapa yang boleh Confirm: hanya hak modul Inventory yang terlihat di kode; pembatasan peran lebih halus tidak ditemukan.
