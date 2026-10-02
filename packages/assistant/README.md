# telasih/assistant

Asisten aplikasi yang bekerja **tanpa internet**. Tiga pekerjaan: mencari layar, menjawab cara pakai dari bantuan aplikasi,
dan menjawab angka. Paket ini **tidak tahu apa pun tentang tabel aplikasi pemakainya**; aplikasi memberi tahu lewat adaptor.

## Prinsip

- **Angka tidak pernah dihitung model bahasa.** Satu pertanyaan angka = satu kelas `Contracts\Metric` yang bisa diuji.
- **Bantuan hanya dikutip** dari `Contracts\Knowledge`. Tidak ada jawaban = bilang tidak tahu (`Reply::UNKNOWN`), dicatat di log.
- **Hak akses milik aplikasi.** Semua "boleh / tidak" ditanyakan ke `Contracts\Asker` (level + hak modul).
- **Model bahasa lokal (Ollama) hanya pemilih**: diberi pertanyaan dan calon terdekat, menjawab satu nomor. Mati = asisten
  tetap jalan dengan pencocokan kata kunci (`Text`).

## Urutan keputusan (`Engine::ask`)

1. frasa angka cocok (dan bukan "buka ..." / "cara ...") → kelas metric, setelah gerbang modul + level;
2. ada kata tanya isian / cara / kegunaan → penjelasan isian, langkah, atau kegunaan dari bantuan;
3. nama layar cocok → layar (tombol Open + How to use);
4. topik bantuan cocok → kegunaan topik;
5. belum yakin dan model aktif → model memilih dari calon terdekat;
6. tidak tahu.

## Memasang di aplikasi lain

1. Daftarkan `Assistant\AssistantServiceProvider` dan jalankan migrasinya (tabel `message` dan `metric`; nama + koneksi di
   `config/assistant.php`).
2. Tulis adaptor dan ikat di container:
   - `Contracts\Navigator::screens($asker)` — layar yang boleh dibuka si penanya (key, label, path, url, words, about, help);
   - `Contracts\Knowledge::topics($asker)` — bantuan dalam bahasa si penanya (key, title, purpose, words, steps, fields).
3. Beri tahu siapa penanya dan dari mana setelan dibaca:

   ```php
   Assistant::askerUsing(fn ($user) => new MyAsker($user));       // implements Contracts\Asker
   Assistant::translateUsing(fn (string $en) => __($en));          // teks baku jawaban
   Assistant::settingsUsing(fn (string $key, $default) => ...);    // opsional; bawaan = config
   ```
4. Daftarkan kelas metric di `config('assistant.metrics')`. Baris katalognya (frasa, modul, level minimum) dibuat otomatis
   dari nilai awal kelas, lalu dirawat user di layar aplikasi.
5. Layar: kirim `AssistantService::boot($user)` ke layar asisten. Di DevLib: `x-dl::shell` / `x-dl::layer-shell` prop
   `assistant`. Aplikasi tanpa DevLib memanggil API-nya sendiri:
   - `POST <prefix>/ask` `{q, topic?}` → `{id, reply}` (`Reply::toArray()`),
   - `POST <prefix>/feedback` `{id, value: 1 | -1 | 0}`.
6. Jadwalkan `assistant:prune` harian (masa simpan riwayat per level).

## Setelan (`config/assistant.php` atau `Assistant::settingsUsing`)

| Kunci | Bawaan | Arti |
|---|---|---|
| `model_enabled` | 0 | 1 = tanya Ollama bila kata kunci tidak yakin |
| `model_url`, `model_name` | `http://127.0.0.1:11434`, `qwen2.5:3b` | server dan model Ollama |
| `model_timeout_seconds` | 60 | batas tunggu model |
| `history_days_top` / `_middle` / `_low` | 30 / 14 / 7 | masa simpan riwayat menurut level penanya |
| `level_top_min`, `level_middle_min` | 4, 2 | batas level kelompok itu |
| `ticket_page` | null | halaman kirim tiket dari jawaban "tidak tahu" |

Di ERP Telasih Makmur: adaptor di `app/Assistant/*`, pengikatan di `App\Providers\AssistantHostProvider`, setelan di
Settings > Constants kategori Assistant. Rancangan: `docs/md_for_systems/aiassistant_tier1.md`.
