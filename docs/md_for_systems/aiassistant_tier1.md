# Asisten AI — Tier 1

> Status 2026-10-02: **Help terpasang di ddt + tm2** (bagian 6). **Asisten tahap 1–2 DIBANGUN di database dummy (mockup.test)**:
> paket `packages/assistant`, drawer chat, cari layar, bantuan dari Help, angka Sales today + Deadstock, penjaga pertanyaan
> gaji, log, masa simpan riwayat. **Belum**: `tm2` (menunggu user selesai menguji di mockup.test), sambungan Ollama belum
> diuji (Ollama belum terpasang), 17 angka lain di bagian 5. Mockup: `docs/mockups/ai_assistant_drawer.jpg`.

## 1. Tujuan

Asisten di dalam ERP yang **bekerja tanpa internet**, menjawab dari isi database:

1. **Mencari menu / layar** ("surat jalan subkon di mana?") lalu membukakannya.
2. **Bantuan cara pakai** ("cara bikin CO tanpa quotation", "Due Date diisi apa?") dari Help.
3. **Angka untuk manajemen** ("sales hari ini", "nilai deadstock sekarang") dari data transaksi.

## 2. Keputusan user (2026-10-02)

| # | Keputusan |
|---|---|
| 1 | Lambat tidak masalah; kecepatan diukur setelah jalan. Spesifikasi server produksi belum diketahui. |
| 2 | **Sales today** = nilai rupiah Customer Order yang masuk hari ini. |
| 3 | **Deadstock** = stok yang tidak bergerak lebih dari **6 bulan**, dinilai dengan **harga beli rata-rata**. |
| 4 | Siapa saja boleh bertanya, jawaban **sesuai level** masing-masing. |
| 5 | Bentuk: **chat pribadi** antara asisten dan user yang bertanya, di **drawer sendiri**. |
| 6 | Help wajib dibuat untuk semua layar input dan laporan: cara pakai, penjelasan tiap isian, dan untuk apa tiap laporan — masing-masing terpisah, semua masuk Help supaya mudah dicari AI lokal. |
| 7 | Daftar pertanyaan Direksi dibuatkan dulu (bagian 5); user menyusulkan tambahannya. |
| 8 | Asisten harus **modular**: bisa dipindah ke aplikasi lain dan langsung menyesuaikan. |
| 9 | Deploy di Ubuntu 24 ke atas. Bahasa lain selain PHP boleh bila perlu. |
| 10 | Jawaban **Sales today** merinci: **Draft** sekian, **Confirmed** sekian, lalu **Total** sekian (CO Cancelled tidak dihitung). |
| 11 | Riwayat percakapan disimpan **30 hari** untuk top management, **14 hari** untuk middle management, **7 hari** untuk level terbawah (user lebih dari 100, menghemat storage). |
| 12 | Help dipasang juga ke `tm2` (tmerp.test) — sudah dijalankan 2026-10-02. |
| 13 | **Level memakai user security yang sudah ada** (grup + `mgmt_level` + hak modul), bukan pembagian baru. |
| 14 | Perjalanan user + drawer disetujui; user menguji setelah masuk mockup.test. |
| 15 | **Pertanyaan gaji hanya untuk General Manager dan Direksi.** Gaji sendiri dilihat user di profilnya (asisten menunjuk ke sana). |

## 3. Perjalanan user (untuk disetujui)

1. User klik **Asisten AI** di menu akun (ikon tongkat, sudah ada) → drawer kanan terbuka. Isinya percakapan pribadi user itu dengan asisten; riwayatnya tersimpan dan hanya bisa dibaca user itu sendiri.
2. Di atas ada sapaan dan beberapa **contoh pertanyaan** yang sesuai levelnya (staf: cara pakai layar; Direksi: angka).
3. User mengetik pertanyaan dengan bahasa biasa (Indonesia atau English).
4. Asisten menjawab dengan salah satu bentuk:
   - **Layar** — nama layar, letaknya di menu, satu kalimat kegunaannya, tombol **Open …** (membuka tab layar itu) dan **Cara pakai**.
   - **Cara pakai** — langkah-langkah dari Help, sumbernya disebut, tombol **Open …** dan **Open Help**.
   - **Angka** — nilai besar, rinciannya, satu kalimat definisi (dihitung dari apa, per jam berapa), tombol **Open …** ke laporan tempat angka itu bisa dicek.
   - **Di luar hak** — menyebut level / akses yang diperlukan, tanpa menampilkan angkanya. Bila sebagian boleh (mis. jumlah dokumen tanpa rupiah), yang boleh saja yang ditampilkan.
   - **Tidak tahu** — berkata terus terang, tidak menebak; menawarkan **Kirim sebagai Tiket Bantuan**.
5. Tiap jawaban punya jempol naik / turun. Pertanyaan yang tidak terjawab atau dijempol turun masuk daftar di **Setup > Assistant Log**, supaya admin menambah Help atau kata kuncinya.
6. Jawaban layar dan angka muncul langsung (tidak menunggu AI). Hanya pertanyaan bebas yang menunggu model lokal; selama itu tampil "sedang mencari…".

## 4. Arsitektur

### 4.1 Prinsip: AI tidak menghitung dan tidak menulis SQL

Model lokal berukuran kecil dan bisa mengarang. Karena itu:

- **Angka** selalu dihitung kelas PHP yang teruji (satu pertanyaan terdaftar = satu kelas *metric*). AI hanya memilih metric dan parameternya (hari ini / bulan ini / customer X).
- **Cara pakai** selalu dikutip dari Help. AI hanya merangkai kalimat dari potongan Help yang ditemukan, dan menyebut sumbernya.
- **Layar** dicari langsung di menu (tanpa AI).
- Model mati atau belum terpasang → pencarian layar, Help, dan metric lewat kata kunci **tetap jalan**; yang hilang hanya pemahaman kalimat bebas.

### 4.2 Modular: paket terpisah + adaptor (keputusan 8)

```
packages/assistant/            <- paket; tidak tahu apa pun tentang tabel ERP ini
  src/Core/                    PHP murni, tanpa Laravel
    Engine                     menerima pertanyaan -> memilih sumber -> menyusun jawaban
    Contracts/Asker            siapa yang bertanya: id, nama, bahasa, level, boleh(modul)
    Contracts/Navigator        mencari layar yang boleh dibuka si penanya
    Contracts/Knowledge        mencari potongan bantuan
    Contracts/MetricCatalog    daftar pertanyaan angka + menjalankannya
    Contracts/LanguageModel    model bahasa (driver Ollama; driver kosong = tanpa AI)
    Contracts/Settings         alamat model, nama model, batas waktu
  src/Laravel/                 jembatan Laravel: provider, route /assistant/*, migration tabel sendiri
app/Assistant/                 <- adaptor milik ERP ini (satu-satunya bagian yang tahu tabel ERP)
  ErpAsker, ErpNavigator (sys_app_menu), ErpKnowledge (sys_help_*), ErpSettings (sys_constant)
  Metrics/SalesToday, Metrics/DeadstockValue, ...
packages/devlib                <- tampilan drawer = objek DevLib x-dl::assistant (aturan "DevLib dulu")
```

Pindah ke aplikasi lain = pasang paketnya, tulis adaptornya (penanya, menu, bantuan) dan kelas metric aplikasi itu. Mesin, tabel
percakapan, dan sambungan ke model tidak diubah. Aplikasi bukan Laravel memakai `Core` saja; aplikasi bukan PHP memanggilnya
lewat HTTP (`/assistant/ask`).

### 4.3 Model bahasa lokal

- **Ollama** (satu program, jalan sebagai service di Ubuntu 24.04 dan di Windows untuk pengembangan). ERP memanggilnya lewat
  `http://127.0.0.1:11434`. Tidak perlu Python / bahasa lain.
- Nama model = setelan (bisa diganti tanpa ubah kode). Mulai dari model kecil (kelas 3–8 miliar parameter) lalu diukur.
- Internet hanya dibutuhkan sekali untuk mengunduh model; sesudah itu mandiri.
- Laptop pengembangan (i5-10310U, 16 GB, tanpa GPU): hanya kuat model kecil. Waktu jawab **belum diukur**.

### 4.4 Level = user security (keputusan 13)

Asisten tidak punya aturan hak sendiri. Semua "boleh / tidak" ditanyakan ke user security (`App\Assistant\ErpAsker`):

- **Layar**: hanya yang ada di Menu v2 user itu (`AppMenu::modules`, aturan yang sama dengan menu).
- **Help**: terbuka untuk semua.
- **Angka**: tiap pertanyaan punya **modul pemilik**; penanya wajib boleh membuka modul itu (`SecUser::canAccessModule` = izin
  grup + `sec_module.min_mgmt_level`). User yang boleh membuka **Laporan Manajemen** (modul `MGMT`: Direksi, General Manager)
  boleh menanyakan angka semua bagian. Tiap pertanyaan juga bisa diberi **Minimum level** (`mgmt_level`) di Setup >
  Assistant Questions; 0 = hak modul sudah cukup.
- **Rupiah penjualan** mengikuti `SecUser::canViewSalesPrice()` (aturan A.9); yang tidak berhak mendapat jumlah dokumen saja.
- **Gaji** (keputusan 15): di bawah `ASSISTANT_SALARY_MIN_LEVEL` (4 = General Manager) asisten menolak dan menunjuk ke profil
  user. Menanyakan letak / cara pakai layar payroll tetap dijawab pencarian layar biasa sesuai menu user.
- **Riwayat chat** (keputusan 11): `mgmt_level` >= 4 disimpan 30 hari, 2–3 = 14 hari, 1 = 7 hari; batas dan harinya konstanta
  kategori Assistant. Dibuang terjadwal tiap malam (`assistant:prune`).
- Semua pertanyaan tercatat (siapa, level, dijawab apa) di Setup > Assistant Log.

Level saat ini (`sec_group`): Direksi 5 · Manager Operasional 4 · Manager per bagian 3 · Supervisor / Officer 2 · Staf 1.

### 4.5 Tabel baru (paket asisten; nama dan koneksi lewat config)

| Tabel | Isi |
|---|---|
| `sys_assistant_conversation` | satu percakapan per user |
| `sys_assistant_message` | pertanyaan + jawaban + sumber + jempol + lama jawab |
| `sys_assistant_metric` | katalog pertanyaan angka: kunci, kelas, frasa pemicu (ID / EN), modul, level minimum, aktif |

Layar input: **Setup > Assistant Questions** (frasa, level, aktif), **Setup > Assistant Log**; setelan (alamat + nama model, batas
waktu, hidup / mati) di Setup > Constants kategori ASSISTANT.

## 5. Daftar pertanyaan manajemen (usulan pertama)

Definisi nomor 1 dan 8 sudah ditetapkan user. Yang lain **usulan**; kolom Data = hasil cek sekilas ke database, wajib dicek lagi saat dibangun.

| # | Pertanyaan | Dihitung dari | Level usul | Data |
|---|---|---|---|---|
| 1 | Sales hari ini | Nilai Customer Order bertanggal hari ini, tanpa yang Cancelled; dirinci Confirmed / Draft | rupiah: 2+ Sales; jumlah dokumen: semua | ada |
| 2 | Sales bulan ini / tahun ini (dan dibanding bulan lalu) | sama dengan no. 1, rentang lain | 3 | ada |
| 3 | Customer terbesar bulan ini (5 teratas) | nilai CO per customer | 3 | ada |
| 4 | Order yang belum terkirim | CO Confirmed, qty order − qty terkirim | 2 | ada |
| 5 | Quotation yang masih terbuka dan win rate | quotation published belum WIN / LOST; WIN ÷ (WIN + LOST) | 3 | ada |
| 6 | Kiriman yang terlambat | baris CPDEL lewat tanggal janji dan belum terkirim | 2 | ada |
| 7 | Kiriman hari ini | Delivery Order / Surat Jalan bertanggal hari ini | 1 | ada |
| 8 | Nilai deadstock | stok masih ada, pergerakan terakhir lebih dari 6 bulan, × harga rata-rata; per Raw Material / Semi Finished / Finished Goods | 3 + akses Inventory | ada |
| 9 | Nilai stok sekarang | saldo stok × harga rata-rata, per kategori | 3 | ada |
| 10 | Pembelian hari ini / bulan ini | nilai Purchase Order | 3 | ada |
| 11 | PO yang belum diterima | qty PO − qty diterima | 2 | ada |
| 12 | Yang menunggu approval saya | dokumen di Approval Center yang gilirannya di user itu | semua | ada |
| 13 | Work Order terbuka / terlambat | status WO dan tanggal rencana | 2 | perlu dicek |
| 14 | Hasil produksi hari ini dan scrap | catatan output WO | 2 | perlu dicek |
| 15 | Mesin yang sedang rusak / WO maintenance terbuka | Maintenance Request + WO | 2 | ada (dummy) |
| 16 | Piutang belum dibayar dan yang lewat jatuh tempo | invoice penjualan terbuka | 4 | perlu dicek |
| 17 | Hutang yang jatuh tempo minggu ini | tagihan supplier terbuka | 4 | perlu dicek |
| 18 | Saldo kas dan bank | saldo akun kas / bank di buku besar | 5 | perlu dicek |
| 19 | Kehadiran hari ini | presensi: hadir, tidak hadir, terlambat | 3 + akses HRM | perlu dicek |
| 20 | Jumlah karyawan aktif per bagian | Master Employee | 3 + akses HRM | ada |

## 6. Help (SUDAH DIBANGUN 2026-10-02, ddt + tm2)

- Tabel `sys_help_topic` (satu layar / laporan / panduan: untuk apa + kata cari) dan `sys_help_line` (potongan pendek: langkah
  cara pakai **atau** penjelasan satu isian / kolom). Tiga hal yang diminta user tersimpan terpisah: Purpose, STEP, FIELD.
- Tiap potongan paling panjang 255 karakter, English di tabel; **bahasa Indonesia lewat kamus** (konteks `help`), sesuai aturan B.3.
  Potongan sependek itu juga yang paling mudah ditemukan model lokal.
- Isi awal ditulis dari kode tiap layar: `resources/data/help/*.json` → `php artisan help:import`. Sesudah itu dirawat di layar.
- Layar: **Setup > Settings > Help Topics**, **Help Lines** (kolom English dan Indonesia berdampingan), dan **General Report >
  Help > Help Center** (dibaca semua user; baris dibuka = langkah + penjelasan isian).
- `help:import` melaporkan layar di menu yang belum punya Help, supaya layar baru tidak lolos.
- Pintu untuk semua user: halaman **F1 (Bantuan & dukungan) → Help Center**. Di `tm2sys` modul menu *General Report* belum ada,
  jadi di tmerp.test Help Center dibuka lewat F1 saja (baris menunya ikut muncul begitu modul itu dipasang).

## 7. Pemasangan Ollama

Windows (pengembangan):

```bash
winget install Ollama.Ollama
```

Ubuntu 24.04 (server):

```bash
curl -fsSL https://ollama.com/install.sh | sh
```

Lalu unduh model (sekali, butuh internet), nama model final ditentukan setelah uji kecepatan:

```bash
ollama pull qwen2.5:3b
```

## 8. Tahap

| Tahap | Isi | Butuh model | Status |
|---|---|---|---|
| 0 | Help semua layar + layar inputnya + Help Center | tidak | dibangun (ddt + tm2) |
| 1 | Paket asisten + drawer chat + cari layar + cari Help (kata kunci) + log | tidak | dibangun (ddt), belum dicoba di browser |
| 2 | Katalog angka: no. 1 dan 8 + penjaga gaji; daftar bagian 5 lainnya bertahap | tidak | no. 1, 8, gaji dibangun (ddt) |
| 3 | Sambungan Ollama: model memilih dari calon terdekat saat kata kunci tidak yakin | ya | kode ada, **belum diuji** (Ollama belum terpasang; `ASSISTANT_MODEL_ENABLED` = 0) |
| 4 | Ukur kecepatan di server Ubuntu, pilih model | ya | nanti |

## 9. Yang masih terbuka

1. **Nilai stok kosong**: sebagian besar baris kartu stok di data sekarang tidak punya nilai (harga rata-rata kosong), jadi rupiah
   deadstock terlalu rendah. Asisten menyebutnya terang-terangan ("n dari m item tidak punya nilai stok"), tetapi angkanya baru
   benar setelah nilai stok terisi.
2. **Gaji sendiri di profil user**: halaman profil belum menampilkan gaji. Jawaban asisten sudah menunjuk ke sana (keputusan 15),
   jadi halamannya perlu dibangun.
3. **Angka gaji untuk GM / Direksi**: belum ada (definisinya belum ditetapkan); untuk sekarang asisten menunjuk ke layar payroll.
4. **Sales today di data dummy = 0** bila belum ada Customer Order bertanggal hari ini.
5. **tm2**: paket asisten dipasang ke tmerp.test setelah user selesai menguji di mockup.test.
6. Daftar pertanyaan bagian 5: 17 lainnya belum dibangun; user menyusulkan tambahannya.
7. Ollama: pasang, nyalakan `ASSISTANT_MODEL_ENABLED`, lalu ukur kecepatan.

## 10. Letak kode

| Bagian | Letak |
|---|---|
| Paket (mesin, kontrak, riwayat, katalog, Ollama, API) | `packages/assistant/` (+ `README.md` cara pindah ke aplikasi lain) |
| Adaptor ERP | `app/Assistant/` (`ErpAsker`, `ErpNavigator`, `ErpKnowledge`, `Metrics/*`), `App\Providers\AssistantHostProvider`, `config/assistant.php` |
| Drawer | DevLib `x-dl::shell` / `x-dl::layer-shell` prop `assistant` (`shell/overlays.blade.php`, `shell.js`) |
| Layar setup | `setup-assistant-questions`, `setup-assistant-log` (Setup > Settings), konstanta kategori Assistant |
| Test | `tests/Feature/AssistantEngineTest.php` (mesin dengan adaptor tiruan) |
