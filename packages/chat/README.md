# telasih/chat — chat antar user ala WhatsApp

Paket Laravel yang berdiri sendiri (user 2026-10-02: "harus modular, suatu ketika bisa saya ambil buat app yang berbeda").
Isinya **bagian server** chat satu lawan satu: pesan, lampiran file, tanda online, centang dibaca. **Layarnya** adalah objek
DevLib `x-dl::chat` (paket `telasih/devlib`); keduanya hanya bertemu lewat lima alamat API di bawah, jadi layar lain pun
bisa dipakai selama mengikuti bentuk jawabannya.

Paket ini **tidak mengenal** tabel user, menu, konstanta, atau kamus aplikasi mana pun. Yang khas aplikasi masuk lewat tiga
pintu: `config/chat.php`, kontrak `Chat\Contracts\Directory`, dan kait `Chat\Chat::settingsUsing()` / `translateUsing()`.

## Isi paket

| Bagian | File |
|---|---|
| Rute + migrasi + kait sign out | `src/ChatServiceProvider.php` |
| Logika (kirim, poll, lampiran, online, dibaca) | `src/ChatService.php` |
| API | `src/Http/ChatController.php`, `src/Http/ReadOnlySession.php` |
| Siapa usernya (kontrak + bawaan) | `src/Contracts/Directory.php`, `src/EloquentDirectory.php` |
| Kait setelan & terjemahan | `src/Chat.php` |
| Dorongan seketika (WebSocket, opsional) | `src/Realtime.php` |
| Tabel | `src/Models/*`, `database/migrations/*` |
| Setelan bawaan | `config/chat.php` |

Tabel: `sys_chat_conversations`, `sys_chat_messages`, `sys_chat_participants`, `sys_user_presence`. File lampiran disimpan di
disk privat (`chat.disk`, folder `chat/`), bukan di database, dan hanya bisa dibuka dua orang di percakapannya.

## Memasang di aplikasi lain

1. Salin folder `packages/chat` (dan `packages/devlib` bila layar DevLib dipakai).
2. Daftarkan autoload + provider. Salah satu:
   - `composer.json` aplikasi: `"Chat\\": "packages/chat/src/"` di `autoload.psr-4`, `composer dump-autoload`, lalu
     `Chat\ChatServiceProvider::class` di `bootstrap/providers.php` (cara tmerp); atau
   - repositori `path` ke `packages/chat` + `composer require telasih/chat` (provider terdaftar sendiri).
3. `php artisan migrate` — migrasi paket membuat keempat tabel (atau hanya menambah kolom yang kurang bila tabelnya sudah ada).
4. Bila perlu, buat `config/chat.php` di aplikasi berisi kunci yang berbeda saja: `connection`, `prefix`, `middleware`,
   `users_table` / `users_key`, `disk`, `guard`, `attachment_max_mb`, `poll_seconds`, `online_timeout_seconds`.
5. Tentukan siapa yang bisa di-chat. Bawaan (`EloquentDirectory`) = semua baris model user auth, nama dari kolom `name`.
   Aturan sendiri (hanya user aktif, jabatan, foto): buat kelas yang mengimplementasikan `Chat\Contracts\Directory` dan
   ikat di provider aplikasi: `$this->app->bind(Directory::class, PunyaSaya::class)`.
6. Taruh layarnya di halaman yang sudah login:

   ```blade
   @php
       $chat = app(\Chat\ChatService::class)->boot(auth()->user());
   @endphp
   <x-dl::chat :me="$chat['me']" :people="$chat['people']" :convos="$chat['convos']" :api="$chat['api']" />
   ```

   (`x-dl::layer-shell` / `x-dl::shell` menerima array yang sama lewat prop `chat`.)

Opsional: angka setelan dari tempat lain (mis. tabel konstanta) — `Chat\Chat::settingsUsing(fn (string $key, float $default) => ...)`;
teks galat lewat kamus aplikasi — `Chat\Chat::translateUsing(fn (string $english) => ...)`.

## API (nama rute `chat.*`, alamat `<prefix>/...`)

| Rute | Isi | Jawaban |
|---|---|---|
| `GET poll?after=<id>&open=<id orang>` | denyut online + pesan baru; `open` = percakapan yang sedang dilihat (langsung dibaca) | `{msgs, last_id, read, presence}` |
| `GET messages?peer=<id>&before=<id>` | satu halaman pesan (50), terbaru dulu | `{msgs, more, read}` |
| `POST upload` (`uid`, `offset`, `chunk`) | satu potong lampiran (`chat.chunk_bytes`) | `{received}` |
| `POST send` (`to`, `text`, `upload`, `name`) | kirim pesan / lampiran yang sudah di-upload | `{msg}` |
| `GET file/{id}` | isi lampiran; gambar tampil langsung, lainnya diunduh | file |
| `POST typing` (`to`) | kabari lawan "sedang mengetik" (hanya lewat WebSocket) | `{ok}` |
| `POST auth` (`socket_id`, `channel_name`) | izin langganan channel WebSocket milik user | `{auth}` |

Bentuk `msg` dan data awal `boot()` ditulis di kepala `Devlib\Components\Chat`.

## Seketika (WebSocket) — opsional

Tanpa setelan apa pun chat bekerja dengan bertanya berkala. Supaya **seketika** (pesan, centang dibaca, online, "sedang
mengetik…") jalankan satu server WebSocket berprotokol Pusher — di tmerp: Laravel Reverb di folder `realtime/` — lalu isi di
`.env` aplikasi pemakai:

```
CHAT_REALTIME_APP_ID=...      # sama dengan REVERB_APP_ID di server
CHAT_REALTIME_KEY=...         # sama dengan REVERB_APP_KEY
CHAT_REALTIME_SECRET=...      # sama dengan REVERB_APP_SECRET
CHAT_REALTIME_HOST=127.0.0.1  # alamat server dilihat dari PHP
CHAT_REALTIME_PORT=8080
CHAT_REALTIME_SCHEME=http
```

- Paket **tidak butuh pustaka broadcast** (`laravel/reverb`, `pusher-php-server`, Laravel Echo): `Realtime` menandatangani
  sendiri kabar yang dikirim ke server dan izin langganan channel; layar DevLib menyambung dengan `WebSocket` bawaan browser.
- Channel privat `chat.<scope>.user.<id>` (kabar untuk satu user) dan `chat.<scope>.everyone` (siapa yang online). `scope`
  bawaan = nama database chat, sehingga beberapa aplikasi boleh memakai satu server tanpa tertukar.
- Kabar: `chat.message {from, to, msg}`, `chat.read {reader, id}`, `chat.typing {from}`, `chat.presence {id, online, seen}`.
- Selama tersambung, layar tetap bertanya tiap `realtime_poll_seconds` (30) sebagai jaring pengaman dan denyut online.
  **Server mati / putus:** kabar yang gagal dikirim hanya dicatat di log, layar kembali bertanya tiap `poll_seconds` dan
  mencoba menyambung lagi tiap 30 detik. Tidak ada pesan yang hilang: semuanya tetap dari database.
- Situs https memerlukan `wss`: `CHAT_REALTIME_CLIENT_SCHEME=https` (+ `CHAT_REALTIME_CLIENT_HOST` / `_PORT` bila berbeda).

## Cara kerja singkat

- Layar bertanya ke `poll` tiap `poll_seconds`; pertanyaan itu juga denyut "sedang login".
  Online = `signed_in` dan denyut terakhir masih dalam `online_timeout_seconds`. Sign out (event `Logout` di guard chat)
  langsung mematikan `signed_in`.
- `poll`, `upload`, `typing`, dan `auth` memakai `ReadOnlySession`: sesi dibaca tetapi tidak ditulis, supaya permintaan latar tidak menimpa
  flash / old input layar lain dan sesi tetap bisa kedaluwarsa.
- Lampiran dikirim berpotong (`chunk_bytes`, bawaan 1 MB) sehingga tidak bergantung pada `upload_max_filesize` PHP.
- Satu percakapan per pasangan user dijamin `pair_key` UNIQUE (`"<id kecil>:<id besar>"`).
- Dibaca = `last_read_message_id` peserta; diterima = denyut lawan bicara lebih baru daripada pesan.

## Di tmerp

`config/chat.php` (koneksi `tm2sys`, alamat `v2/chat`, gerbang `auth` + `locale` + `force.password`),
`App\Services\Chat\UserDirectory` (user `sec_user` aktif + disetujui, jabatan pegawai, kunci = login supaya cocok dengan panel
chat menu v1), kait di `AppServiceProvider` (angka dari Settings > Constants kategori Chat, teks lewat `t()`), dan tes
`tests/Feature/ChatTest.php`.

## Belum ada

Grup, voice, hapus / ubah pesan, pemberitahuan di luar aplikasi.
