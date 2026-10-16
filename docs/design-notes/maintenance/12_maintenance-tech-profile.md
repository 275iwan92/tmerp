# Technical Plan: Asset Maintenance Profile

Form Master Data yang disetujui di mockup: satu profil per mesin berisi apa yang dicek, spare part yang dibutuhkan, dan dokumennya.

## Isi catatan

1. Buka Asset Maintenance Profile dan pilih mesin. Form punya tab Profile, Watch Points, Spare Parts, Documents dan History. Tab PM Plans muncul saat Fase 2 selesai.
2. Untuk mesin dari Master Machine, kode, nama, kelas dan kritikalitas berasal dari master dan terkunci. Aset lain (kendaraan, AC) diketik di sini.
3. Di Watch Points tambahkan hal yang harus dicek pada mesin ini. Pilih komponen dari katalog kode, tulis cek dan caranya, lalu atur frekuensi dan siapa yang mengecek.
4. Ketik batas normal dari manual pabrikan, atau biarkan kosong. Sistem tidak pernah mengisi batas sendiri.
5. Copy from machine menyalin watch point mesin lain sebagai titik awal. Setelah itu Anda mengubahnya. Tidak pernah menimpa baris yang sudah ada.
6. Di Spare Parts tambahkan part yang dibutuhkan aset ini. Stok dari Inventory tampil saat Fase 3 menyambungkannya, jadi Fase 1 berisi daftar part dan saran minimum.
7. Save menyimpan perubahan. Undo membuang perubahan yang belum disimpan. History menampilkan siapa mengubah apa dan kapan.

## Istilah dan rincian

- **Profile completeness**: Menunjukkan berapa dari lima hal yang terisi, misalnya 3 / 5. Dihitung dari data, tidak diketik.
- **Component**: Pilih dari Maintenance Codes. Tidak ada pohon komponen: komponen hanya berupa kode.
- **What to check**: Teks singkat cek, misalnya level oli, tidak bocor. Teks bebas yang diketik user.
- **Normal limit**: Minimum dan maksimum opsional dengan satuan. Tanpa itu cek tidak pernah dinilai oleh sistem.
- **Frequency**: Setiap shift, Harian, Mingguan atau Bulanan.
- **Checked by**: Operator atau Technician. Cek oleh operator menjadi sumber Daily Check.
- **Suggested min stock**: Stok minimum yang disarankan Maintenance untuk part ini. Procurement tetap yang memutuskan pembelian.
- **Expected life**: Berapa lama part biasanya bertahan, dalam hari. Dipakai menilai keausan nanti.
- **Critical**: Menandai part yang bila habis mesin berhenti. Part kritis di bawah minimum tampil di Dashboard.
