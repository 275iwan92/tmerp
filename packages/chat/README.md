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

Bentuk `msg` dan data awal `boot()` ditulis di kepala `Devlib\Components\Chat`.

## Cara kerja singkat

- **Tanpa websocket.** Layar bertanya ke `poll` tiap `poll_seconds`; pertanyaan itu juga denyut "sedang login".
  Online = `signed_in` dan denyut terakhir masih dalam `online_timeout_seconds`. Sign out (event `Logout` di guard chat)
  langsung mematikan `signed_in`.
- `poll` dan `upload` memakai `ReadOnlySession`: sesi dibaca tetapi tidak ditulis, supaya permintaan latar tidak menimpa
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
