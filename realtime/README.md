# realtime — server WebSocket (Laravel Reverb) untuk chat seketika

Aplikasi Laravel kecil yang **hanya** menjalankan server WebSocket. Dipisah dari tmerp karena Reverb membutuhkan pustaka HTTP
versi lain (Guzzle 7) daripada yang dipakai tmerp (Guzzle 8); di sini ia punya `vendor/` sendiri, tmerp tidak berubah.
Satu server ini bisa melayani banyak aplikasi sekaligus (tmerp.test, mockup.test, aplikasi lain yang memakai `packages/chat`).

## Menjalankan

```
cd realtime
php artisan reverb:start
```

atau klik dua kali `start.bat`. Server mendengarkan di port **8080**. Selama jendela itu terbuka, chat seketika.
**Server mati = chat tetap jalan**, hanya kembali bertanya tiap 5 detik sampai server hidup lagi (tersambung ulang sendiri
dalam 30 detik).

Server belum dijalankan otomatis saat komputer menyala. Pilihan: tugas terjadwal Windows "saat login", layanan Reverb di Herd
Pro, atau (server Linux) Supervisor / systemd.

## Memasang di komputer lain

1. `cd realtime && composer install`
2. Salin `.env.example` menjadi `.env`, `php artisan key:generate`.
3. Isi `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (bebas, acak) — **harus sama** dengan `CHAT_REALTIME_APP_ID`,
   `CHAT_REALTIME_KEY`, `CHAT_REALTIME_SECRET` di `.env` aplikasi pemakai.
4. `php artisan reverb:start`.

## Catatan

- Komputer lain di jaringan menyambung ke `ws://<nama-server>:8080`; port 8080 harus diizinkan firewall.
- Situs yang dibuka lewat **https** wajib memakai `wss`: jalankan Reverb di belakang proxy TLS (atau beri sertifikat), lalu
  set `CHAT_REALTIME_CLIENT_SCHEME=https` (+ `CHAT_REALTIME_CLIENT_PORT`) di aplikasi pemakai.
- Di Windows, PHP tanpa ekstensi event loop menangani sekitar 1.000 sambungan serentak — jauh di atas jumlah user saat ini.
- Folder ini sengaja dipangkas: tidak ada database, halaman, atau tes. Jangan menambah fitur aplikasi di sini.
