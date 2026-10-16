# Penyesuaian Stok (Adjustment), Hitung Fisik (Opname), dan Persetujuannya

*Panduan untuk Jo, kepala gudang, dan petugas hitung. Ditulis dari membaca kode, konfigurasi approval, dan data mockup, 16 Oktober 2026. Hal yang tidak bisa saya pastikan ada di bagian 7. Kartu teknis: `docs/tg/inventory/stock/12_card_adjustment-and-opname.md`.*

Stok di sistem bisa berbeda dari stok di rak: salah hitung, rusak, hilang, ditemukan lebih. Perbedaan itu **tidak** diperbaiki dengan mengedit dokumen lama. Ia dicatat dengan dokumen **Stock Adjustment & Physical Count**, yang harus **disetujui** sebelum mengubah Kartu Stok. Ada dua jenis, dipilih saat dokumen dibuat dan tidak bisa diganti sesudahnya:

- **Adjustment (koreksi tunggal)**: untuk satu atau beberapa koreksi kecil.
- **Stock Opname (hitung fisik terjadwal)**: hitung fisik terjadwal, dengan petugas, waktu mulai/selesai, dan saksi.

Salah input Stock In atau Stock Out **bukan** urusan dokumen ini: itu dibatalkan dengan **Cancel** (bab 10, bagian 6). Adjustment hanya untuk selisih fisik.

## 1. Siapa melakukan apa

| Langkah | Siapa | Layar |
|---|---|---|
| Membuat dan mengajukan | Petugas gudang | Inventory > Stock > (kelas) > Stock Adjustment & Physical Count |
| Menyetujui atau menolak Adjustment | Jabatan **Operational Manager** (atau pelaksana tugasnya/PLT) | Adjustment Approval, atau dari form dokumen |
| Menyetujui atau menolak Opname | Jabatan **Warehouse Admin** (atau PLT) | sama |
| Posting ke Kartu Stok | Otomatis saat persetujuan terakhir | - |

Sebelum 30 September 2026 jumlah selisih menentukan berapa tingkat persetujuan. Sekarang, di data mockup, **hanya satu tingkat yang aktif untuk semua nilai**; tingkat Manajer Operasional dan Direksi sudah dinonaktifkan di Setting Approval (rincian angka ada di bagian khusus johiwan di kartu teknis).

## 2. Membuat dokumen (Adjustment)

1. Buka **Inventory > Stock > Raw Material > Stock Adjustment & Physical Count**, klik **New**.
2. Isi header: **Tanggal Dokumen**, **Jenis** (Adjustment atau Stock Opname), **Gudang**, **Alasan** (pilihan: Salah hitung/input sebelumnya, Rusak, Hilang, Ditemukan (lebih dari catatan), Kadaluarsa, Lainnya), **Diajukan oleh** terisi otomatis. Nomornya `ADJ/26/0000n` atau `OPN/26/0000n`.
3. Tambah baris: cari item (minimal dua huruf), lalu pilih **Lokasi**. Begitu bin dipilih, sistem mengisi **Qty Sistem** (saldo kartu terakhir item itu di bin tersebut) dan harga rata-rata. Ketik **Qty Fisik** hasil hitungan. Kolom **Selisih** muncul hijau (lebih) atau merah (kurang), dan **Nilai** menampilkan selisih mutlak x harga.
4. Anda mengetik **fisik**, bukan selisih. Sistem menyimpan selisih = fisik dikurangi sistem.
5. Klik **Simpan Draft**: "Draft ADJ/26/0000n tersimpan."

Kolom catatan per baris menampilkan "wajib" bila ada selisih, **tetapi sistem tidak menegakkannya dan catatan itu tidak disimpan**. Tulis alasan utama di **Catatan** header.

### Tambahan untuk Stock Opname
Blok **Opname** wajib diisi lengkap: **Petugas Opname**, **Mulai Hitung**, **Selesai Hitung**, dan saksi. Bila ada yang kosong: "Petugas, waktu mulai/selesai, dan saksi wajib diisi utk Stock Opname." Tombol **Tarik Semua Item Bersaldo** menarik semua item yang saldonya tidak nol di gudang terpilih ke baris dokumen (dialog "Tarik semua item bersaldo di gudang ini? Baris yang sudah diisi akan ditimpa."). Pilih gudang dulu; bila belum: "Pilih gudang dulu sebelum menarik seluruh item."

Hitung fisik yang dibuat dari tugas hitung di **Warehouse** (Count Task) menghasilkan dokumen Opname otomatis yang langsung diposting; itu dijelaskan di panduan Warehouse.

## 3. Mengajukan dan menyetujui

1. Pada dokumen DRAFT klik **Ajukan Persetujuan** (dialog: baris tidak bisa diedit lagi selama proses). Status menjadi **SUBMITTED**, dan dokumen muncul di antrean penyetuju. Pesan "Diajukan utk approval." menandakan berhasil.
2. Bila belum ada konfigurasi approval aktif untuk jenis itu, pesannya: "Tidak ada konfigurasi approval aktif untuk modul ... Dokumen tersimpan sebagai SUBMITTED tapi belum masuk antrian approval siapapun — hubungi Admin."
3. Penyetuju membuka **Inventory > Stock > (kelas) > Adjustment Approval** (semua kelas memakai satu antrean yang sama untuk Adjustment dan Opname). Setiap dokumen menampilkan ringkasan (kelas, alasan, nilai selisih mutlak, tanggal, jumlah item; untuk Opname juga petugas dan saksi), baris-barisnya hanya-baca, dan riwayat. Tombolnya **Approve**, **Reject**, **Hold**.
4. **Approve** pada tingkat terakhir langsung **memposting**: "Disetujui & diposting ke Kartu Stok." Selisih masuk ke kolom `Adj` Kartu Stok.
5. **Reject** wajib alasan (minimal 10 karakter: "Alasan penolakan wajib diisi, minimal 10 karakter."). **Hold** menahan keputusan tanpa mengubah status; dokumen tetap di antrean Anda.
6. Penyetuju hanya melihat dokumen yang ditujukan ke jabatannya (atau yang jabatannya ia gantikan sebagai PLT). Bila tidak: "This document is not waiting for your approval."

### Setelah posting
Dokumen POSTED tidak bisa diubah, dan **tidak bisa dibatalkan dengan Cancel**. Bila adjustment itu salah, buat adjustment baru yang membalikkan selisihnya.

## 4. Yang ditolak atau perlu diwaspadai

- **Periode ditutup.** Bila tanggal dokumen jatuh di periode inventory yang sudah ditutup, posting ditolak: "The document date is in a closed inventory period (closed through ...). Enter a Stock Adjustment dated in an open period instead." Karena penolakan baru muncul **saat persetujuan terakhir**, penyetuju melihat pesan itu, sementara dokumen **tetap SUBMITTED dan hilang dari antreannya** (catatan persetujuan sudah tertulis). Laporkan ke Admin; dokumen itu perlu ditangani di luar layar ini (tanggalnya tidak bisa diubah karena sudah tidak editable).
- **Saldo minus tidak ditolak** untuk Adjustment/Opname (pengecualian lama yang disengaja). Periksa baik-baik Qty Fisik.
- **Qty Sistem tidak diperbarui.** Angka itu disalin saat bin dipilih. Bila ada Stock In/Out di bin itu antara saat Anda memilih bin dan saat disetujui, selisih yang diposting tidak lagi sama dengan hitungan fisik. Ajukan dan setujui secepatnya; hindari pergerakan di bin yang sedang dihitung.
- **Baris dengan selisih nol dilewati** saat posting.
- **Pembuat boleh menyetujui dokumennya sendiri**: sistem tidak memeriksa. Pisahkan tugas secara organisasi.

## 5. Dokumen yang ditolak (REJECTED)

Menurut teks di layar, dokumen yang ditolak "dikembalikan ke pembuat untuk revisi". **Pada konfigurasi sekarang status hasil tolak adalah REJECTED, dan form hanya membolehkan edit untuk status DRAFT dan REVISION_NEEDED.** Akibatnya dokumen REJECTED terbuka hanya-baca tanpa tombol apa pun: tidak bisa diperbaiki, diajukan ulang, atau dibatalkan dari layar. Di data contoh ada satu dokumen seperti ini. Untuk saat ini: buat dokumen baru (isi ulang), dan laporkan ke Admin agar dokumen lama ditangani.

## 6. Setelah disetujui: laporan

- **Stock Opname** (Inventory > Stock > Reports): semua dokumen Adjustment dan Opname dalam periode, semua status, dengan Qty Sistem, Qty Fisik, Selisih (Gain/Loss/Match), dan nilai. Hanya yang POSTED yang sudah mengubah stok.
- **Stock Loss & Gain**: rekap bulanan per item dari adjustment yang sudah POSTED, dinilai dengan harga rata-rata saat itu.

Penjelasan keduanya ada di bab 12.

## 7. Belum terverifikasi

- Saya tidak membuka layar di browser dan tidak menjalankan tes. Tombol Approve/Reject/Hold di Adjustment Approval dibaca dari kode antrean; tampilan persisnya belum saya lihat.
- Belum diperiksa: apakah jabatan Operational Manager dan Warehouse Admin di data tmerp.test (bukan mockup) sama dengan di mockup.
- Pesan dan label di layar berbahasa Indonesia atau Inggris mengikuti pengaturan bahasa pengguna; saya menuliskan teks bawaan Indonesia.
- Perilaku persis "dokumen tetap SUBMITTED dan hilang dari antrean" setelah penolakan posting saya simpulkan dari urutan kode (catatan persetujuan ditulis sebelum posting), belum saya ulang di layar.
- Nilai ambang persetujuan lama dan nilai uang lain sengaja tidak ditulis di sini.
