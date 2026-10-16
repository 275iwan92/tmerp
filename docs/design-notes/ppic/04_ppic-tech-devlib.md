# Technical Plan: DevLib Work Needed First

Perubahan objek DevLib yang dibutuhkan layar penjadwalan. DevLib diubah lebih dulu, lalu layar mewarisinya, sesuai aturan proyek.

## Isi catatan

1. Gantt saat ini menampilkan jam dalam satu hari. Ia perlu sumbu multi hari (hari dengan tanda jam), pengelompokan baris per seksi (proses), dan piksel per jam yang sesuai periode.
2. Task Gantt mendapat segmen setup (bergaris, menit tampil bila cukup lebar), strip progres, ikon hot dan kunci, dan tiga titik gerbang (bahan, tooling, artwork).
3. Gantt mendapat dua penanda vertikal: Now dan Locked until. Keduanya digambar hanya di atas baris, tidak pernah di atas legenda.
4. Objek view switch (kontrol segmen dengan ikon) ditambahkan; saat ini hanya toggle mode Gantt yang mirip. Ia menyimpan tampilan dan filter terpilih di URL.
5. Calendar mendapat sel hari dengan bar beban dan baris jam setup; Sheet mendapat penanda kolom untuk sel diketik (kuning) lawan dihitung (abu).
6. Tiap perubahan masuk ke paket DevLib, ditampilkan di devlib.test dengan demo, baru dipakai layar. Katalog dan papan Build diperbarui dengan build:catalog.
