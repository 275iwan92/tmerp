# Asisten AI — Tier 1 (rancangan, menunggu persetujuan user)

> Status 2026-10-02: **Help sudah dibangun dan terpasang di ddt + tm2** (bagian 6). **Asisten (drawer chat, mesin jawab, angka
> manajemen) BELUM dibangun** — user sedang me-review preview drawer dan aturan level (bagian 4.4); sisanya di bagian 9.
> Mockup: `docs/mockups/ai_assistant_drawer.jpg`.

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

### 4.4 Level (keputusan 4)

- Layar: hanya yang boleh dibuka user (aturan menu yang sudah ada: `AppMenu` + `canAccessModule`).
- Help: terbuka untuk semua.
- Angka: **tiga kelompok** (usulan, menunggu OK user), dari `sec_group.mgmt_level` tertinggi milik user:

  | Kelompok | `mgmt_level` | Grup saat ini | Angka yang dijawab | Riwayat chat |
  |---|---|---|---|---|
  | Top management | 4–5 | Direksi, Manager Operasional | semua angka semua bagian, termasuk kas / bank, piutang, hutang | 30 hari |
  | Middle management | 2–3 | Manager per bagian, Supervisor, Officer | angka rupiah **bagiannya sendiri** (modul yang boleh dibukanya) | 14 hari |
  | Level terbawah | 1 | Staf | **tanpa rupiah**: jumlah dokumen dan pekerjaannya sendiri | 7 hari |

  Tetap berlaku di atas kelompok itu: HPP / margin hanya Costing + Direksi (A.8), harga jual sesuai A.9, gaji hanya petugas
  payroll + Direksi. Satu metric boleh dikecualikan (lebih tertutup / lebih terbuka) lewat layar Setup > Assistant Questions.
- Batas kelompok (level berapa = top / middle) dan lama simpan riwayat = konstanta kategori ASSISTANT, bukan hardcode.
- Semua pertanyaan tercatat (siapa, kapan, dijawab apa) di log asisten; baris yang lewat masa simpan dihapus terjadwal.

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
| 1 | Paket asisten + drawer chat + cari layar + cari Help (kata kunci) + log | tidak | menunggu OK |
| 2 | Katalog angka: no. 1 dan 8, lalu daftar bagian 5 bertahap, dengan test | tidak | menunggu OK |
| 3 | Sambungan Ollama: memahami kalimat bebas, merangkai jawaban dari Help | ya | menunggu Ollama terpasang |
| 4 | Ukur kecepatan di server Ubuntu, pilih model | ya | nanti |

## 9. Pertanyaan terbuka

1. **Perjalanan user + drawer** (bagian 3, `docs/mockups/ai_assistant_drawer.jpg`): user sedang me-review.
2. **Tiga kelompok level** (bagian 4.4): benarkah Manager per bagian (level 3) masuk *middle*, dan Manager Operasional (level 4)
   masuk *top*? Middle hanya melihat angka bagiannya sendiri — setuju?
3. Daftar pertanyaan bagian 5: user menyusulkan tambahannya.
