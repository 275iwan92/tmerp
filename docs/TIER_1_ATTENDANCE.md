# TIER 1 — TIME ATTENDANCE (Modul ATT) · BUSINESS REQUIREMENTS
**ERP Cetak Offset & Packaging — CV Telasih Makmur Group**

| Item | Nilai |
|---|---|
| Kode modul | `ATT` |
| Database | `tm2hrm` (baru; `emp_*`/`org_*` tetap di `tm2mst`) |
| Prefix tabel | `att_` |
| Versi dokumen | **v1.2 — DRAFT UNTUK REVIEW TELMA** |
| Tanggal | 2026-08-24 |
| Status modul | Diaktifkan sebagai modul kerja (keluar dari status "ditunda") |
| Dokumen sumber | Lihat **§0 Hierarki Otoritas Sumber** — keempat artefak tidak setara |

> **Catatan revisi scope.** Sebelumnya seluruh domain Attendance tercatat sebagai *ditunda*
> (`GLOBAL_START_HERE.md → modules_excluded.hr`) dan sebagian isinya menumpang di
> `TIER_1..4_PAYROLL.md`. Dokumen ini **memisahkan Attendance menjadi modul mandiri** yang
> aktif dikerjakan. **Payroll tetap ditunda** dan hanya menjadi *konsumen* keluaran modul ini.
> Lihat §12 (Pemisahan Attendance vs Payroll) dan §14 (Instruksi split file lama).

---

## 0. Hierarki Otoritas Sumber — BACA INI SEBELUM APA PUN

Empat artefak menjadi rujukan modul ini, dan keempatnya **tidak setara**. Banyak "konflik aturan"
yang tampak di permukaan sebenarnya hanyalah dokumen berbeda derajat yang dibaca seolah sederajat.
Urutan berikut mengikat dan menyelesaikan sebagian besar pertentangan tanpa perlu keputusan baru.

| Tingkat | Artefak | Sifat | Kekuatan |
|:--:|---|---|---|
| **1** | **SOP No. 002/RP/TM/VII/2026 halaman 1–5** | Kebijakan resmi perusahaan, eff. 6 Juli 2026, **bertanda tangan HR, Operational Manager, General Manager, dan Direktur Utama** pada halaman 5 | **Otoritatif.** Menang atas semua |
| **2** | **Kertas kerja yang benar-benar berjalan** — sheet `TA` pada file gaji mingguan `.xlsb`, dan `1 ABSEN JANUARI 2025.xlsx` | Fakta operasional: begini perusahaan menghitung hari ini | Mengikat sebagai **deskripsi keadaan**, bukan sebagai aturan. Dipakai bila SOP diam |
| **3** | **PPTX `PRESENSI & PAYROLL - RULES & POLICY` (21 slide)** dan **halaman 6–8 PDF SOP** | **Olahan seorang system analyst atas SOP** — rancangan, bukan kebijakan | **Referensi.** Kalah dari tingkat 1 dan 2 |
| **4** | Spec TMERP lama (`TIER_1..4_PAYROLL.md`) | Turunan dari tingkat 3 | Diganti oleh dokumen ini |

### 0.1 Mengapa halaman 6–8 PDF bukan bagian dari kebijakan

Halaman 5 PDF memuat **blok tanda tangan empat pejabat** — itulah penutup dokumen resmi
(bab L "PENUTUP" juga berada di halaman itu). Matriks komponen gaji pada halaman 6, 7, dan 8 berada
**sesudah halaman tanda tangan**, dan isinya identik dengan slide 7–8 PPTX yang hanya diformat ulang.
Jadi matriks itu **lampiran kerja analis yang dijilid menyusul**, bukan ketentuan yang ditandatangani
Direksi.

**Keputusan T1-19.** Bila matriks halaman 6–8 (atau PPTX) bertentangan dengan badan SOP halaman 1–5,
**badan SOP yang berlaku**, dan TMERP mengikutinya.

### 0.2 Pertentangan yang langsung selesai oleh hierarki ini

| Hal | PPTX / matriks (tingkat 3) | SOP (tingkat 1) | Yang dipakai TMERP |
|---|---|---|---|
| Ambang "Izin Disetujui" | pengajuan minimal **H-3** (slide 6) | pengajuan minimal **H-7** (butir D.1) | **H-7** |
| Ambang durasi kerja minimum untuk berstatus Hadir | **> 3 jam** (slide 6) | **4,5 jam** (butir I.4 dan K.2) | **4,5 jam** |
| Kode `PAT-5` / `PAT+5` | ada (slide 3, 4, 8) | tidak dikenal | **Dihapus** |
| Kode `LAw` / `LAk` | ada (slide 5) | tidak dikenal | **Dihapus** (lihat T1-16) |
| Denda Rp 25.000 (terlambat) & Rp 10.000 (tidak finger) | ada (slide 2, 6, 8) | **butir B.2: perusahaan tidak menerapkan sistem denda**; hanya Alpha Rp 100.000 yang disebut eksplisit (butir F.5) | **Tidak ada denda** selain Alpha. Lihat KONFLIK-01 yang direvisi |
| Jam masuk/pulang | dipatok **07:30 / 17:00** (slide 2–5) | tidak menyebut jam; jam kerja diatur per SBU dan per shift | **Relatif terhadap shift** (T1-17) |

Slide 2 sampai 5 PPTX secara eksplisit membuka dengan kalimat *"Kita asumsikan Jam Masuk pada 07:30
dan Jam Pulang pada 17:00"* — jadi angka itu memang **ilustrasi**, bukan ketentuan. Menyalinnya
sebagai konstanta ke dalam sistem adalah kesalahan pembacaan, dan itulah yang terjadi pada kertas
kerja Excel yang berjalan sekarang (§2.8c).

### 0.3 Peringatan: PPTX menjelaskan workbook yang berbeda

Slide 19 ("Manual Presensi & Payroll — General Operating") memberi instruksi terhadap sebuah workbook
dengan sheet bernama `DB Employee`, `Data Rekap Presensi`, `Shift Schedule`, `Payroll Produksi`,
lengkap dengan rentang kolom `GS:HX` dan `KN:LS`, ditambah tautan ke sebuah Google Spreadsheet pada
slide 5. **Itu bukan file `.xlsb` yang berjalan sekarang** — struktur kolomnya sama sekali berbeda
dari sheet `TA`.

Artinya ada **rancangan sistem Excel ketiga** yang pernah dibuat analis dan kemungkinan tidak pernah
dipakai. Claude Code **tidak boleh** memperlakukan slide 19 sebagai deskripsi sistem berjalan, dan
tidak boleh mencari sheet-sheet tersebut di file yang ada.

Slide 19 juga menyebut komponen **"Potongan Kerugian"** (penggantian kerugian akibat pelanggaran)
yang tidak muncul di sumber lain manapun. Itu ranah Payroll, dicatat di sini hanya agar tidak hilang.

### 0.4 Yang tetap diambil dari PPTX

Meski derajatnya paling rendah, PPTX menyumbang dua hal yang tidak ada di sumber lain dan tidak
bertentangan dengan SOP:

1. **Prinsip potongan proporsional**: gaji pokok dan tunjangan keahlian berkurang **sebanding dengan
   jam kerja yang hilang** (slide 10–16), sementara tunjangan loyalitas dan prestasi bersifat
   penuh-atau-hilang. Ini konsisten dengan faktor prorata `jam ÷ 8` yang ditemukan di sheet `TA`
   (§2.8d), sehingga saling menguatkan.
2. **Bonus Pagi hilang karena terlambat, Bonus Hadir hilang karena pulang awal / keluar jam kerja.**
   Pemetaan sebab-akibat ini logis dan dipakai konsisten di kedua sumber.

Keduanya adalah masukan bagi Payroll; modul ATT hanya menyediakan angka faktornya.

---

## 1. Tujuan

Menjadi **satu-satunya sumber kebenaran fakta kehadiran** karyawan CV Telasih Makmur Group, yaitu:

1. Menyerap data mentah tap sidik jari dari mesin absensi (via file export) tanpa entri ulang manual.
2. Memasangkan tap mentah tersebut dengan **jadwal kerja resmi** karyawan (`emp_shift` / `emp_shift_roster`) untuk menghasilkan **satu baris status kehadiran per karyawan per hari kerja**.
3. Menggabungkan fakta mesin dengan **dokumen administratif** (Surat Izin, Surat Datang Terlambat, Surat Izin Pulang Awal, Surat Izin Keluar, pengajuan Cuti/Sakit/Izin dari portal self-service HRM) sehingga anomali mesin yang sudah dilegalkan tidak dihitung sebagai pelanggaran.
4. Menghitung **jam lembur** terverifikasi (bukan klaim manual) berdasarkan tap sidik jari yang dipotong aturan minimum & approval per kelompok karyawan.
5. Menegakkan **counter disiplin** SOP 002/RP/TM/VII/2026 secara otomatis (terlambat berapa kali sebulan, izin berapa kali sebulan, alpha berturut-turut, dst) dan memicu usulan tindakan disiplin.
6. Menutup periode (**closing**) dan menerbitkan rekap terkunci yang siap dikonsumsi Payroll saat modul tersebut dikerjakan.

Modul ini **menggantikan** kertas kerja Excel HR yang berjalan sekarang (`1 ABSEN JANUARI 2025.xlsx`, 9 sheet, sebagian besar diketik ulang manual).

---

## 2. Kondisi Saat Ini (as-is) — hasil pembedahan file yang diunggah

Bagian ini wajib dibaca Claude Code sebelum implementasi karena menentukan bentuk data nyata, bukan asumsi.

### 2.1 Mesin fingerprint & format export (`01Ringkasan.xls`)

File export berformat **.xls (BIFF, Excel 97-2003)**, bukan .xlsx. Berisi 4 sheet ringkasan + **±165 sheet per-karyawan** (nama sheet = gabungan 3 PIN, mis. `10.101.105`).

| Sheet | Isi | Dipakai TMERP? |
|---|---|---|
| `Ringkasan` | 1 baris per karyawan: Lama bekerja (dibutuhkan/aktual), Kelalaian (jumlah/menit), Keluar lebih awal (jumlah/menit), Lembur (Regular/Spesial), Kehadiran `22/3`, Dinas Kantor, Tidak hadir, Cuti | **TIDAK** — hasil olahan mesin, memakai definisi shift internal mesin |
| `Shift` | Matriks karyawan × tanggal, nilai kode shift mesin (`1`) | **TIDAK** — jadwal otoritatif ada di `emp_shift_roster` TMERP |
| `Catatan` | Matriks karyawan × tanggal, nilai = **seluruh tap mentah** hari itu, multi-baris dipisah `\n` (contoh: `12:47\n13:03\n21:32\n`) | **YA — INI SUMBER IMPORT UTAMA** |
| `Tidak Normal` | Long format: No, Nama, Dept, Tanggal, AM Masuk/Keluar, PM Masuk/Keluar, Kelalaian(min), Keluar lebih awal(min), Total(min), Memo | **TIDAK** (hanya hari anomali; sudah diolah) |
| `<PIN>.<PIN>.<PIN>` (±165) | Kartu kehadiran per karyawan | **TIDAK** |

**Keputusan T1-01 — Import hanya dari sheet `Catatan`.**
TMERP mengambil **tap mentah** dan menghitung ulang semuanya sendiri. Kolom hasil olahan mesin
(`Kelalaian`, `Keluar lebih awal`, `Lembur`, `Tidak hadir`) **diabaikan total**, karena mesin memakai
definisi shift internal yang tidak sinkron dengan `emp_shift`/`emp_shift_roster` TMERP — mesin tidak
tahu skema 2-shift/3-shift per mesin cetak, tidak tahu tukar shift, dan tidak tahu jadwal Sabtu yang
berbeda. Mempercayai kolom olahan mesin akan menghasilkan angka yang salah dan tidak bisa diaudit.

### 2.2 Karakteristik tap mentah yang WAJIB ditangani

Bukti dari data nyata Januari 2026:

| Fenomena | Contoh nyata | Implikasi |
|---|---|---|
| Tap ganda beruntun (jari diulang) | `05:19`, `05:24`, `16:30` — dan `12:47`, `13:03`, `21:32` | Perlu **dedup window** |
| Jumlah tap ganjil / hanya 1 tap | `17:02\n` saja (GANI CAHYONO, 05-01) | Hari tidak lengkap → status khusus, bukan alpha |
| Tap lebih dari 2 (istirahat / keluar jam kerja) | `07:07`, `07:12`, `15:3x` | Perlu algoritma pairing |
| Shift malam melewati tengah malam | Skema 3-shift Shift 3 = `21.00 – 5.15` | Tanggal tap ≠ tanggal kerja |
| Satpam shift 12 jam lintas hari | S4 = `19.00 – 7.00` | Idem |
| Identitas = **PIN mesin**, bukan NIK | PIN `10`, `101`, `112233` (SEMAR), `82` | Perlu kolom mapping `finger_pin` |
| PIN sampah/uji coba | `112233` = "SEMAR", tidak pernah ada tap | Import harus punya jalur "PIN tak dikenal" |
| Karyawan sudah keluar | Kolom shift bernilai `OUT` di kertas kerja HR | Import harus menolak/menandai employee non-aktif |

### 2.3 Jam kerja resmi (sheet `Ketentuan`) — belum pernah masuk spec manapun

**OFFSET PRINTING**

| Pola | STAFF | SPV FINISHING | PRODUKSI |
|---|---|---|---|
| Reguler Sen–Jum | 07.30 – 17.00 | — | 07.30 – 16.30 |
| Reguler Sabtu | 07.30 – 16.00 | — | 07.30 – 15.30 |
| Shift 1 Sen–Jum | — | 05.00 – 14.30 | 05.00 – 13.15 |
| Shift 1 Sabtu | — | 05.00 – 13.30 | *(mengikuti Sen–Jum)* |
| Shift 2 Sen–Jum | — | 12.30 – 22.00 | 13.15 – 21.30 |
| Shift 2 Sabtu | — | 13.30 – 22.00 | *(mengikuti Sen–Jum)* |
| Skema 3-shift (opsional **per mesin**) | — | — | S1 05.00–13.15 · S2 13.00–21.15 · S3 21.00–05.15 |

**SATPAM** (berbasis **gedung**, bukan divisi)

| Kode | Jam | Gedung |
|---|---|---|
| S1 | 07.00 – 17.00 | A5 |
| S2 | 07.00 – 17.00 | A10 |
| S3 | 11.30 – 21.30 | A5 |
| S4 | 19.00 – 07.00 | A5 + A10 |
| S5 | 09.00 – 19.00 | A5 + A10 |

**DIGITAL PRINTING**

| Pola | STAFF | OPERATOR VERSAFIRE | PRODUKSI |
|---|---|---|---|
| Reguler Sen–Jum | 07.30 – 17.00 | — | 08.30 – 17.30 |
| Reguler Sabtu | 07.30 – 16.00 | — | 08.30 – 16.30 |
| Shift 1 Sen–Jum / Sabtu | — | 07.00 – 16.00 / 07.00 – 15.00 | — |
| Shift 2 Sen–Jum / Sabtu | 12.00 – 21.30 / 12.00 – 20.30 | 12.30 – 21.30 / 12.30 – 20.30 | — |
| Skema 2-shift (per mesin) | — | — | S1 05.00–13.15 · S2 13.15–21.30 |
| Skema 3-shift (per mesin) | — | — | S1 05.00–13.15 · S2 13.00–21.15 · S3 21.00–05.15 |

**Konsekuensi arsitektur:**
- Jam kerja **berbeda antara Senin–Jumat dan Sabtu** → `emp_shift` wajib punya detail per hari
  (`emp_shift_detail`, sudah ada di DDL HRM — tinggal di-seed dengan tabel di atas).
- Jam kerja **berbeda per SBU × kelompok jabatan** → satu SBU punya banyak shift; penugasan shift
  menempel di `emp_employee`/`emp_shift_roster`, bukan di divisi.
- Shift Satpam punya dimensi **gedung** yang belum ada di skema manapun. Lihat §11 DELTA-01.
- Skema 2/3-shift **dipilih per mesin**, bukan per divisi — konsisten dengan board roster PPIC
  (mesin × shift × tanggal) yang sudah dirancang di modul HRM.

### 2.4 Aturan lembur (sheet `Ketentuan`) — berbeda per kelompok, belum pernah terspesifikasi

| SBU | Kelompok | Ambang minimum lembur hari kerja | Otorisasi |
|---|---|---|---|
| Offset | STAFF | **min 2 jam** | Approval Manager |
| Offset | SPV Finishing | **min 2 jam** | Approval Leader |
| Offset | PRODUKSI | **min 30 menit** | Tanda tangan **SPKT** |
| Digital | STAFF | **min 2 jam** | Approval pemegang posisi *(sumber menyebut nama orang "B. Dewi" — WAJIB dipetakan ke `org_position`, bukan nama)* |
| Digital | OP Versafire | **min 30 menit** | Tanda tangan SPKT |
| Digital | PRODUKSI | **min 30 menit** | Tanda tangan SPKT |

Lembur hari Minggu / tanggal merah:
- STAFF & SPV: **dihitung berdasarkan tap sidik jari** (tanpa ambang minimum).
- PRODUKSI: pola tetap **05.00 – 14.00, istirahat 1 jam** (= 8 jam terhitung).

Bucket lembur yang dipakai kertas kerja payroll eksisting (`REKAP ABSEN` di file .xlsb):
`L. BIASA` (lembur hari kerja), `L. BESAR` (lembur hari besar), `L. BESAR_MINGGU`,
`B. HDR BESAR` (bonus hadir hari besar), `U. ROTI`, dan **`UANG MAKAN`** (kolom terpisah di sheet
`REKAP LEMBUR`, **berbeda** dari Uang Roti Rp5.000).

**Keputusan T1-02.** Attendance **menghasilkan jam lembur per bucket** (`OT_BIASA`, `OT_BESAR`,
`OT_BESAR_MINGGU`), **tidak menghitung rupiah**. Konversi jam → rupiah, Uang Makan, Uang Roti, dan
Bonus Hadir Besar adalah ranah Payroll (ditunda).

### 2.5 Dokumen administratif eksisting (sheet `Rekap Surat Ijin`)

Format nomor nyata: `027/SI.TLS/XII/2025`, `029/FDT.TDP/I/2026`, `027/FDT.TLS/I/2026`.

Terbaca dua jenis dokumen dan dua entitas:

| Segmen | Nilai teramati | Arti |
|---|---|---|
| Jenis dokumen | `SI` | Surat Izin (tidak masuk) |
| Jenis dokumen | `FDT` | Form Datang Terlambat / form anomali harian |
| Entitas | `TLS` | Telasih (Offset Printing) |
| Entitas | `TDP` | Telasih Digital Printing |

Kolom "KEPERLUAN" bercabang enam dan **tipe isinya berbeda**:
`TERLAMBAT` (jam), `KELUAR JAM KERJA` (jam), `PULANG AWAL` (jam), `SAKIT` (tanggal),
`IJIN` (tanggal), `CUTI` (tanggal).

**Keputusan T1-03.** Dokumen-dokumen ini dimodelkan sebagai **satu entitas `att_permit_letter`**
dengan `letter_category` (TERLAMBAT / KELUAR_JAM_KERJA / PULANG_AWAL / SAKIT / IZIN / CUTI), bukan
enam tabel. Surat ber-kategori jam menempel ke **satu** `att_daily` (1 hari); surat ber-kategori
tanggal menempel ke **rentang** `att_daily`. Penomoran otomatis mengikuti pola eksisting agar HR
tidak perlu belajar format baru — lihat §7.

### 2.6 Periode payroll = **MINGGUAN**, periode disiplin = **BULANAN**

Temuan paling berdampak. Sheet `PERIODE` di file gaji menunjukkan periode bernomor urut berjalan
sejak 2019 (`1 = 1 MARET 2019` … `1630 = 3–9 JAN`, `1631 = 24–30 JAN`, `1632 = 31 JAN–6 FEB`),
masing-masing dengan `PERIODE_AWAL`, `PERIODE_AKHIR`, `JUMLAH HARI` (biasanya 6, bisa 4–5 bila ada
libur). Nama file gaji secara eksplisit bertanda `WEEKLY`.

Sementara itu seluruh counter disiplin SOP berbasis **bulan kalender**: maksimal izin 3× **dalam
1 bulan**, terlambat <5 menit maksimal 5× **setiap bulan**, terlambat >5 menit maksimal 3× **setiap
bulan**, tidak finger 3× **dalam 1 bulan**, izin+alpha >5× **dalam 2 bulan berturut-turut**.

**Keputusan T1-04 — dua sumbu waktu yang independen.**

| Sumbu | Nama | Panjang | Fungsi | Objek |
|---|---|---|---|---|
| A | **Periode Attendance** | Mingguan, bernomor urut melanjutkan seri eksisting | Unit closing & serah-terima ke Payroll | `att_period` |
| B | **Periode Disiplin** | Bulan kalender | Reset seluruh counter pelanggaran SOP | `att_discipline_counter` (kunci: employee + tahun-bulan) |

Kedua sumbu **tidak boleh disatukan**. Sebuah periode mingguan bisa membelah dua bulan (contoh nyata:
periode 1632 = 31 Jan – 6 Feb); dalam kasus itu tanggal 31 Jan menambah counter bulan Januari dan
tanggal 1–6 Feb menambah counter bulan Februari, sementara keduanya masuk satu rekap payroll.

**Keputusan T1-05.** Nomor urut periode melanjutkan seri lama (1633, 1634, …) dan
`att_period` di-seed dengan riwayat dari sheet `PERIODE` agar referensi historis tidak putus.

### 2.7 Bentuk keluaran yang dibutuhkan HR (sheet `Rekap Absen` & `LAP ABSEN`)

- `Rekap Absen` (long format): `NAMA | Tanggal | Masuk | Keluar | Lembur | Kedatangan | Kehadiran | No. Surat | Ket` — **inilah bentuk kanonik `att_daily`**. Perhatikan kolom `No. Surat`: rekap harian memang sudah dirancang untuk menautkan dokumen izin.
- `LAP ABSEN`: laporan harian naratif per shift, kolom `DIRUMAHKAN | TIDAK MASUK | TERLAMBAT`. Ini **laporan harian untuk manajemen**, bukan struktur data — jadi output cetak/dasbor, bukan tabel.
- `REKAP ABSEN` di file gaji: agregat per karyawan per periode dengan kolom hitungan hari
  `SAKIT | IJIN | ALPHA | CUTI | IJIN SETUJU | SP(1/2)` plus jumlah hari yang berhak atas tiap
  komponen gaji. **Inilah bentuk kanonik `att_period_summary`** — antarmuka resmi Attendance → Payroll.

### 2.8 Mesin kalkulasi eksisting — sheet `TA` (temuan paling menentukan)

Sheet **`TA`** ("Time Attendance") di dalam file gaji mingguan adalah **kalkulator attendance yang
sesungguhnya dipakai perusahaan hari ini**. Sheet `REKAP ABSEN` hanya menampung hasilnya. Struktur
`TA`: satu baris per karyawan, 121 kolom, disusun sebagai 14 blok berisi 7 kolom tanggal + kolom
TOTAL.

| Blok | Isi | Sifat |
|---|---|---|
| *(kol 3–16)* | Jam masuk & jam pulang per hari, disimpan sebagai pecahan hari | Input |
| `JAM KERJA` | Jam kerja **aktual** per hari (desimal) | Hitungan |
| `NORMATIF KERJA` | Jam kerja **di dalam jendela shift** per hari | Hitungan |
| `TERLAMBAT (FAKTOR KALI)` | Penanda 1/0 per hari | Counter |
| `TERLAMBAT DIATAS 35 MENIT` | Penanda 1/0 per hari | Counter |
| `TERLAMBAT DIATAS 45 MENIT` | Penanda 1/0 per hari + `TOTAL > 45` + `TOTAL TERLAMBAT` | Counter |
| `JAM LEMBUR (JAM)` | Jam lembur hari kerja | Hitungan |
| `JAM LEMBUR HARI BESAR` | Jam lembur hari libur | Hitungan |
| `BONUS HADIR LEMBUR HARI BESAR` | Penanda 1/0 | Hitungan |
| `GAJI POKOK DAN TJ KEAHLIAN (FAKTOR KALI)` | Faktor 0–1 per hari | Faktor bayar |
| `BONUS HADIR` | Faktor 0–1 per hari | Faktor bayar |
| `TJ. MASA KERJA DAN TJ. PRESTASI` | Faktor 0–1 per hari | Faktor bayar |
| `BONUS PAGI` | Faktor 0–1 per hari | Faktor bayar |
| `UANG ROTI` | Penanda 1/0 per hari | Faktor bayar |

Rumus yang berhasil direkonstruksi dari nilai nyata dan dari satu formula yang tertinggal sebagai
teks di sheet:

**(a) Jam kerja aktual — istirahat dipotong rata, bukan dari tap**
```
jam_kerja_aktual = (jam_pulang − jam_masuk) − 1 jam istirahat
```
Terverifikasi: 07:30–16:30 → 8,00 · 07:30–15:30 → 7,00 (Sabtu) · 07:36–19:05 → 10,483 ·
07:30–20:15 → 11,75. Nilainya **desimal presisi menit**, tidak dibulatkan.

**Keputusan T1-15.** TMERP mengikuti pola ini: **istirahat dipotong sebagai konstanta per shift**
(diambil dari `emp_shift_detail`), bukan dihitung dari tap istirahat. Alasannya bukan sekadar
kompatibilitas — data nyata menunjukkan tap istirahat hampir tidak pernah dilakukan, sehingga
menghitungnya dari tap akan menghasilkan jam kerja yang membengkak palsu untuk hampir semua orang.
Tap istirahat tetap disimpan bila ada, tetapi tidak memengaruhi jam kerja.

**(b) Jam normatif — inilah definisi lembur yang sesungguhnya**
```
jam_normatif = jam standar shift − keterlambatan  (dibatasi maksimum jam standar)
jam_lembur   = jam_kerja_aktual − jam_normatif    (dibulatkan ke bawah, jam penuh)
```
Terverifikasi: masuk 07:36 (telat 6 menit) → normatif 7,90 dari standar 8,00 · masuk 07:33 → normatif
7,95 · jam kerja 10,00 dengan normatif 8,00 → lembur 2 jam.

**Keputusan T1-16.** Definisi lembur diambil apa adanya dari praktik ini, dan ini **mengoreksi spec
lama**. `TIER_1_PAYROLL.md` mendefinisikan lembur sebagai "kerja sebelum jam masuk shift" (LAw) dan
"kerja setelah jam pulang shift" (LAk) — dua kode terpisah. Praktik nyata tidak memakai konsep itu;
yang dipakai adalah **selisih jam aktual terhadap jam normatif**, yang secara otomatis mencakup
kedua sisi sekaligus dan sekaligus memastikan karyawan yang datang terlambat tidak bisa menutupi
keterlambatannya dengan pulang lebih malam lalu mengklaimnya sebagai lembur. Kode `LAw` dan `LAk`
karena itu **dihapus**; yang disimpan adalah angka `overtime_hours` beserta rinciannya.

**(c) Tiga ambang keterlambatan — penamaannya menyesatkan**
Label blok berbunyi "DIATAS 35 MENIT" dan "DIATAS 45 MENIT". Angka itu **bukan durasi
keterlambatan**, melainkan **menit pada jam 07:xx**: 07:35 dan 07:45. Terverifikasi pada karyawan yang
masuk pukul 07:36 dan terkena penanda "diatas 35 menit" meskipun hanya telat 6 menit.

Dengan jam masuk standar 07:30, ketiga ambang itu berarti: telat > 0 menit, telat > 5 menit, telat
> 15 menit — **identik dengan ambang di SOP dan PPTX**. Jadi tidak ada konflik; hanya penamaan kolom
yang buruk.

**Keputusan T1-17 — sekaligus memperbaiki cacat sistem lama.** Ambang di Excel **dipatok mati** ke
pukul 07:30 / 07:35 / 07:45. Akibatnya seluruh karyawan shift (Shift 1 masuk 05.00, Shift 2 masuk
13.15, Shift 3 masuk 21.00) dan seluruh karyawan Digital Printing (produksi masuk 08.30) **dihitung
salah** oleh kertas kerja yang berjalan sekarang. TMERP menghitung ambang secara **relatif terhadap
jam masuk shift efektif hari itu**, bukan terhadap jam absolut. Ini bukan sekadar penyesuaian teknis
— ini memperbaiki kesalahan penggajian yang selama ini berjalan diam-diam.

**(d) Faktor bayar harian**
Satu formula tertinggal sebagai teks di sheet, dan isinya menjelaskan arti kata "Diperhitungkan"
pada matriks komponen gaji di PPTX:
```
bila hari Minggu, atau tidak ada jam masuk            → faktor 0
bila masuk ≤ 07:45 DAN pulang ≥ 16:30                 → faktor 1
selain itu                                            → faktor = jam kerja ÷ 8
```
Jadi "Diperhitungkan" = **prorata terhadap 8 jam kerja normatif**.

**Keputusan T1-18.** Modul ATT **menghasilkan faktor bayar harian** (`payable_factor`, angka 0–1 per
komponen per hari) dan meneruskannya ke Payroll. Faktor adalah besaran **waktu**, bukan uang, sehingga
tidak melanggar batas modul di §12 — sementara memindahkannya ke Payroll justru akan memaksa Payroll
menghitung ulang jam kerja dan menduplikasi seluruh logika shift. Payroll cukup mengalikan faktor
dengan tarif.

**(e) Uang Roti**
Terverifikasi: penanda bernilai 1 pada hari dengan lembur ≥ 2 jam, tanpa kelipatan. Modul ATT
menghasilkan **penanda kelayakannya**; nilai Rp 5.000 tetap di Payroll.

**(f) Hari Minggu ditandai manual**
Formula membaca sebuah sel penanda tunggal (`$W$3`) yang diisi tangan tiap periode untuk menonaktifkan
perhitungan hari Minggu. Ini rapuh — satu sel salah isi merusak seluruh periode untuk semua karyawan.
Di TMERP, status hari libur diambil dari kalender hari libur (DELTA-02), bukan dari penanda manual.

**(g) Tunjangan keahlian berbasis level jabatan**
Sheet `REFF_KEAHLIAN` memetakan posisi → tarif harian, dan posisinya **berjenjang**:
`HELPER GUDANG LV 1 / LV 2 / LV 3`, `OP POTONG LV 1`, `SPV`, `OP FORKLIFT`, `PLAT`. Struktur
`org_position` di modul HRM saat ini tidak mengenal level di dalam satu posisi. Lihat DELTA-08.

### 2.9 Ringkasan perubahan antar versi dokumen ini

| Hal | v1 (asumsi awal) | v1.1 (setelah sheet `TA`) | v1.2 (setelah hierarki sumber) |
|---|---|---|---|
| Istirahat | Dihitung dari tap | **Konstanta per shift** | tetap |
| Lembur | `LAw`/`LAk` | **Jam aktual − jam normatif** | diperkuat: `LAw`/`LAk` hanya ada di PPTX |
| Ambang telat | 5 / 15 menit | terkonfirmasi, relatif ke shift | tetap |
| Faktor bayar | Ranah Payroll | **Dihasilkan ATT** | diperkuat oleh slide 10–16 |
| Satuan jam kerja | Menit bulat | **Desimal presisi menit** | tetap |
| Level jabatan | — | Ada → DELTA-08 | tetap |
| Ambang "Izin Disetujui" | H-3 (ikut spec lama) | H-3 | **H-7** (SOP menang) |
| Durasi minimum status Hadir | > 3 jam (ikut spec lama) | > 3 jam | **4,5 jam** (SOP menang) |
| Denda Rp 25.000 / Rp 10.000 | dianggap berlaku | dianggap berlaku | **Gugur** — tidak ada di SOP bertanda tangan |

---

## 3. Ruang Lingkup Modul

### 3.1 Di dalam scope

| # | Fitur | Ringkas |
|---|---|---|
| A1 | **Master PIN & pemetaan mesin** | Mapping PIN mesin → `employee_id`; satu karyawan boleh punya >1 PIN (pindah mesin/gedung); daftar mesin absensi & lokasinya |
| A2 | **Import file export mesin** | Upload .xls/.xlsx, parsing sheet `Catatan`, staging, validasi, commit. Idempoten & dapat diulang |
| A3 | **Normalisasi tap** | Dedup, pengurutan, atribusi tanggal kerja untuk shift lintas hari |
| A4 | **Pairing & kalkulasi harian** | Tap → segmen kerja → jam masuk/pulang efektif, menit terlambat, menit pulang awal, menit keluar jam kerja, total jam kerja efektif |
| A5 | **Penentuan status harian** | Menghasilkan `att_daily.status_code` dari matriks §5 |
| A6 | **Penggabungan dokumen** | Menyerap pengajuan self-service HRM (cuti/sakit/izin/izin keluar/tukar shift) + `att_permit_letter` HR, lalu **menimpa/melegalkan** status mesin |
| A7 | **Lembur** | Pengajuan/SPKT, approval per kelompok, verifikasi silang otomatis ke tap, klasifikasi ke bucket. Rumus: jam aktual − jam normatif (§2.8b) |
| A7b | **Faktor bayar harian** | Menghasilkan `payable_factor` 0–1 per komponen per hari (§2.8d) — angka waktu, bukan rupiah |
| A8 | **Counter disiplin bulanan** | Akumulasi otomatis + usulan tindakan (teguran verbal / SP) ke `emp_disciplinary` HRM |
| A9 | **Koreksi manual HR** | Setiap perubahan atas hasil mesin wajib beralasan & terekam audit (siapa, kapan, dari apa ke apa) |
| A10 | **Closing periode** | Kunci periode mingguan, hasilkan `att_period_summary`, tidak bisa diubah kecuali reopen berotorisasi |
| A11 | **Laporan** | Kartu absensi per karyawan, rekap harian per shift (pengganti `LAP ABSEN`), rekap periode, rekap surat izin, rekap lembur. **Rancangan tampilan: §16** |
| A12 | **Portal karyawan (read-only)** | Karyawan melihat tap & status harian dirinya; bila keberatan → mengajukan koreksi (bukan mengubah). **Rancangan tampilan: §16** |

### 3.2 Di luar scope (tegas)

- **Semua konversi ke rupiah**: tarif lembur, denda, potongan, Uang Makan, Uang Roti, prorata komponen gaji, slip gaji → **modul Payroll (tetap ditunda)**.
- **Koneksi langsung / real-time ke mesin** (SDK, ADMS, pull via jaringan) → v2. v1 murni berbasis file export. Lihat §13.
- **Absensi berbasis GPS / selfie / mobile clock-in** → tidak diminta, tidak dibangun.
- **Recruitment** (`tm2hrr`) → tetap ditunda.
- **Perhitungan PPh 21 & BPJS** → ranah Payroll.
- **Master karyawan, shift, mesin produksi, roster** → sudah ada di modul HRM; modul ini **memakai**, tidak mendefinisikan ulang.

---

## 4. Aktor & Tanggung Jawab

| Aktor | Kewenangan di modul ATT |
|---|---|
| **HR Officer** | Import file mesin, kelola PIN, terbitkan/kelola `att_permit_letter`, koreksi manual beralasan, jalankan closing, cetak seluruh laporan |
| **Admin App** | Seluruh kewenangan HR Officer + reopen periode terkunci + kelola parameter aturan |
| **Atasan Langsung** | Approve lembur (sesuai kelompok), approve pengajuan izin/koreksi dari bawahannya |
| **Manager / Leader** | Approve lembur STAFF & SPV Finishing sesuai matriks §2.4 |
| **PPIC** | Pemilik roster shift (di modul HRM) — perubahan roster otomatis mengubah baseline perhitungan ATT |
| **Karyawan** | Read-only atas data dirinya + mengajukan koreksi |
| **Satpam** | Tidak punya akses tulis. Verifikasi Surat Izin Keluar dilakukan di luar sistem (SOP I.5), sistem hanya menyediakan tampilan read-only status surat |
| **Sistem (job)** | Kalkulasi ulang harian, akumulasi counter, deteksi anomali, notifikasi |

---

## 5. Katalog Status Harian (kanonik)

Diambil dari `TIER_1_PAYROLL.md` §2.4, **direvisi** berdasarkan temuan file. Perubahan ditandai.

### 5.1 Sumbu 1 — Status Kehadiran Harian (`att_daily.status_code`)

Satu hari kerja = **tepat satu** nilai.

| Kode | Nama | Sumber penentuan |
|---|---|---|
| `H` | Hadir normal | Mesin |
| `T1` | Terlambat < 5 menit | Mesin *(sebelumnya `T-5`)* |
| `T2` | Terlambat 5–15 menit | Mesin *(sebelumnya `T+5`)* |
| `T3` | Terlambat > 15 menit | Mesin *(sebelumnya `T+15`)* |
| `PA` | Pulang awal | Mesin |
| `KJK` | Keluar pada jam kerja | Mesin + Surat Izin Keluar |
| `S` | Sakit | Dokumen |
| `I` | Izin | Dokumen |
| `IS` | Izin disetujui (berbayar) | Dokumen — pengajuan **minimal H-7** (SOP D.1) |
| `C` | Cuti tahunan | Dokumen |
| `CK` | Cuti khusus / penting | Dokumen |
| `CL` | Cuti melahirkan | Dokumen |
| `A` | Alpha | Turunan (tidak ada tap & tidak ada dokumen) |
| `DL` | **Dinas luar / dinas kantor** | Dokumen — **BARU**, belum ada di spec lama; mesin merekam kolom "Dinas Kantor" dan HR memakainya |
| `DRM` | Dirumahkan | Status kepegawaian (`emp_contract.work_status`) |
| `SKR` | Skorsing | Tindakan disiplin |
| `LIB` | Libur terjadwal | Turunan dari roster/kalender — **BARU**, dibutuhkan agar hari tanpa jadwal tidak salah dibaca sebagai alpha |
| `HL` | Hari libur nasional / tanggal merah | Kalender hari libur |
| `NOSCH` | **Tidak terjadwal** | Turunan — karyawan aktif tanpa baris roster. **BARU**, ini kondisi data yang harus terlihat, bukan disembunyikan |

**Penghapusan (1) — kode lembur.** Kode `LAw` (lembur awal) dan `LAk` (lembur akhir) dari spec lama
**dihapus** sesuai Keputusan T1-16. Lembur bukan status kehadiran melainkan besaran jam; ia hidup di
kolom `overtime_hours` beserta bucket-nya, bukan di `status_code`.

**Penghapusan (2) — kode gabungan.** Kode `PAT-5` dan `PAT+5` dari spec lama **dihapus**. Alasannya: terlambat
dan pulang awal adalah dua pengukuran independen yang dua-duanya sudah tersimpan sebagai angka
(`late_minutes`, `early_leave_minutes`); membuat kode gabungan menyebabkan ledakan kombinatorial
(terlambat × pulang awal × keluar jam kerja = 12+ kode) dan tetap tidak mampu menampung kombinasi
tiga arah. Kode status cukup mengambil **pelanggaran paling berat** hari itu, sedangkan pelanggaran
lain tetap terbaca dari kolom menit dan dari flag di §5.2.

### 5.2 Sumbu 2 — Penanda Harian (boolean, bisa lebih dari satu)

| Flag | Arti |
|---|---|
| `is_late` | Ada keterlambatan |
| `is_early_leave` | Pulang sebelum jam shift berakhir |
| `has_midshift_exit` | Ada keluar-masuk di tengah shift |
| `is_missing_punch` | Tap tidak lengkap (ganjil / hanya 1 / tidak ada tap pulang) |
| `is_under_min_hours` | Jam kerja efektif < 4,5 jam (SOP I.4 & K.2) |
| `has_overtime` | Ada jam melewati shift yang lolos verifikasi |
| `is_document_backed` | Anomali sudah dilegalkan dokumen |
| `is_manually_adjusted` | Pernah dikoreksi HR |
| `is_holiday_work` | Bekerja di hari Minggu / tanggal merah |

### 5.3 Sumbu 3 — Status Administratif Berjalan (periode, bukan harian)

`SP1`, `SP2`, `SP3` — hidup di `emp_disciplinary` (modul HRM), **bukan** di `att_daily`. Modul ATT
hanya membaca dan mengusulkan penerbitannya.

**Keputusan T1-06.** Tiga sumbu di atas ortogonal. Seorang karyawan bisa berstatus `H` (sumbu 1),
berflag `is_late` + `has_overtime` (sumbu 2), sekaligus sedang menjalani `SP1` (sumbu 3). Spec lama
mencampur ketiganya dalam satu daftar status, dan itu penyebab utama ambiguitas.

---

## 6. Aturan Bisnis Utama (ringkas — logika rinci di Tier 2)

### 6.1 Ambang keterlambatan

`T1` < 5 menit · `T2` 5–15 menit · `T3` > 15 menit. Diukur dari **jam masuk shift efektif hari itu**
(bukan jam shift generik), sesudah roster & tukar shift disetujui diperhitungkan.

### 6.2 Batas jam kerja minimum

Total jam kerja efektif < **4,5 jam** dalam satu hari → hari itu **dihitung sebagai `I` (Izin)**,
kecuali ada penugasan resmi atau persetujuan khusus Manajemen (SOP I.4 dan K.2). Ini berlaku apa pun
penyebabnya — pulang awal, datang terlambat parah, atau keluar jam kerja.

**Keputusan T1-07 — penyelesaian pertanyaan lama "KJK multi-window".**
Spec lama menyisakan `[DELTA-CONFIRM]`: bila karyawan keluar-masuk beberapa kali dalam sehari,
apakah menit hilang dijumlahkan atau diambil window terpanjang. **Pertanyaan itu gugur.** Aturan SOP
yang benar bukan tentang lamanya keluar, melainkan tentang **total jam kerja efektif yang tersisa**.
Karena itu perhitungannya adalah:
```
jam_kerja_efektif = Σ (durasi seluruh segmen berada di dalam area) − istirahat terjadwal
```
Semua window keluar otomatis terpotong karena tidak masuk hitungan segmen — tanpa perlu memilih
antara "dijumlah" atau "terpanjang". Ini juga memperbaiki pembacaan keliru atas slide PPTX yang
menulis *"KELUAR PADA JAM KERJA — MAKSIMAL 4,5 JAM, > 4,5 JAM DIKATEGORIKAN IZIN"*: yang dimaksud
bukan durasi keluar maksimal 4,5 jam, melainkan **jam kerja tersisa minimal 4,5 jam**, sesuai bunyi
SOP butir I.4 dan K.2 yang lebih baru dan lebih jelas.

### 6.3 Alpha

- Tidak ada tap **dan** tidak ada dokumen dalam 1×24 jam → `A` (SOP F.1).
- Izin lebih dari 3 hari berturut-turut → hari ke-4 dan seterusnya → `A` (SOP D.2 & F.3).
- Izin lebih dari 3 kali **dalam satu bulan kalender** → kejadian ke-4 dan seterusnya → `A` (SOP K.3).
- Izin tanpa persetujuan atasan dan/atau HRD → `A` (SOP F.4).

**Keputusan T1-08.** Butir 2 dan 3 di atas adalah **dua aturan berbeda yang berlaku bersamaan**,
bukan duplikasi. Yang pertama berbasis rentetan hari, yang kedua berbasis hitungan kejadian per bulan.
Keduanya diimplementasikan terpisah.

### 6.4 Eskalasi disiplin (SOP K)

| Pemicu | Tindakan |
|---|---|
| Alpha 3 hari kerja berturut-turut | Surat Peringatan |
| Izin + Alpha > 5 kali dalam 2 bulan berturut-turut | Surat Peringatan |
| Tidak hadir tanpa keterangan 5 hari kerja berturut-turut | Proses PHK sesuai PP |
| Terlambat < 5 menit > 5× dalam sebulan | Teguran verbal |
| Terlambat > 5 menit > 3× dalam sebulan | Teguran verbal |
| Tidak finger 3× dalam sebulan | Teguran verbal |
| Tukar shift tanpa form H-1 | Teguran verbal |
| Tidak masuk berurutan dengan Minggu/tanggal merah tanpa SKD | Teguran verbal |
| Dokumen izin palsu | Teguran verbal hingga SP |

**Keputusan T1-09.** Sistem **mengusulkan**, tidak menerbitkan otomatis. Setiap pemicu menghasilkan
baris usulan di antrean tindakan disiplin; HR meninjau lalu memutuskan. Penerbitan SP tanpa manusia
di tengah adalah risiko hukum ketenagakerjaan yang tidak sepadan dengan penghematan waktunya.

### 6.5 Sakit

Wajib dilaporkan **paling lambat 07.30 WIB** hari itu (SOP C.1). Wajib dua dokumen: SKD + kwitansi/
resep/bukti pembelian obat — atau alternatif tunggal berupa tangkapan layar riwayat aplikasi JKN bagi
peserta BPJS. Dokumen diserahkan paling lambat hari H saat masuk kerja (SOP C.3). Bila tidak lengkap
atau lewat batas → **direklasifikasi menjadi `I`** (SOP C.4).

### 6.6 Izin memotong cuti

Bila saldo cuti tahunan masih ada, izin **memotong saldo cuti** (SOP B.7), dan karyawan **hanya boleh**
mengajukan izin bila jatah cuti tahun berjalan sudah habis (SOP D.5). Aturan ini sudah tercatat sebagai
revisi tertunda di `HRM_STATE.yaml`; modul ATT adalah tempat penegakannya saat menentukan status harian.

### 6.7 Tidak finger

Momen wajib tap: datang, pulang, istirahat terlambat, keluar pada jam kerja, pulang awal. Tap yang
hilang → flag `is_missing_punch` + menambah counter bulanan. Modul ATT **tidak** menghitung nilai
denda; ia hanya mencatat kejadian dan jumlahnya.

### 6.8 Hari Minggu & tanggal merah

Seluruh jam kerja pada hari tersebut masuk bucket lembur (`OT_BESAR_MINGGU` / `OT_BESAR`), tidak
diukur terhadap ambang keterlambatan. Untuk PRODUKSI berlaku pola tetap 05.00–14.00 dengan istirahat
1 jam. Sumber kebenaran tanggal merah — lihat §11 DELTA-02.

---

## 7. Dokumen Administratif (`att_permit_letter`)

Satu entitas, enam kategori, meneruskan pola penomoran yang sudah dipakai HR:
```
<nomor urut>/<jenis>.<entitas>/<bulan romawi>/<tahun>
contoh: 041/FDT.TLS/VIII/2026
```

| Segmen | Nilai |
|---|---|
| jenis | `SI` (tidak masuk: SAKIT, IZIN, CUTI) · `FDT` (anomali harian: TERLAMBAT, KELUAR_JAM_KERJA, PULANG_AWAL) |
| entitas | `TLS` (Offset) · `TDP` (Digital) · *(Indostry menyusul — lihat DELTA-03)* |

**Keputusan T1-10.** Nomor urut berjalan **per jenis × entitas × tahun**, dibangkitkan sistem, dan
bersifat *gapless* — nomor hanya terbit saat surat disimpan final, bukan saat form dibuka, agar tidak
ada lubang nomor pada dokumen yang dibatalkan.

**Keputusan T1-11 — hubungan dokumen dengan fakta mesin.** Dokumen **tidak pernah menghapus** tap
mesin. Ia menambah lapisan pembenaran di atasnya: `att_daily` menyimpan `status_code_machine`
(hasil murni mesin) **dan** `status_code_final` (setelah dokumen & koreksi). Keduanya selalu dapat
dibandingkan. Inilah yang membuat modul ini dapat diaudit, dan inilah yang tidak dimiliki kertas kerja
Excel sekarang.

---

## 8. Alur Utama

### 8.1 Alur harian
```
Mesin fingerprint (fisik)
   └─> HR export file .xls  ──> [A2] Import & staging
                                    │
                              validasi PIN, tanggal, duplikat
                                    │
                              [A3] Normalisasi tap (dedup, urut, atribusi tanggal kerja)
                                    │
                    ┌───────────────┴───────────────┐
              emp_shift_roster                 att_punch (bersih)
              (jadwal otoritatif)                    │
                    └───────────────┬───────────────┘
                                    │
                              [A4][A5] Kalkulasi → att_daily.status_code_machine
                                    │
                     dokumen (portal self-service + att_permit_letter)
                                    │
                              [A6] Overlay → att_daily.status_code_final
                                    │
                              [A8] Counter disiplin bulanan → usulan tindakan
```

### 8.2 Alur periode
```
[A10] Closing periode mingguan
   ├─ prasyarat: nol anomali belum terselesaikan (missing punch, PIN tak dikenal, NOSCH)
   ├─ kunci att_daily pada rentang periode
   ├─ hasilkan att_period_summary (bentuk mengikuti §2.7)
   └─ status periode: OPEN → LOCKED  (reopen hanya oleh Admin App, terekam audit)
```

**Keputusan T1-12 — closing tidak boleh dipaksakan.** Periode tidak dapat ditutup selama masih ada
baris anomali berstatus terbuka. Alasannya: begitu periode terkunci dan diserahkan ke Payroll,
kesalahan menjadi mahal untuk dikoreksi. Menahan closing adalah cara paling murah untuk memaksa
kualitas data. HR boleh menyelesaikan anomali dengan memberi keputusan (mis. "tetap alpha"), tetapi
tidak boleh mengabaikannya.

---

## 9. Antarmuka dengan Modul Lain

| Modul | Arah | Isi |
|---|---|---|
| **HRM / Master Employee** | ATT ← HRM | `emp_employee`, `emp_contract` (work_status), `emp_shift`, `emp_shift_detail`, `emp_shift_roster`, `emp_shift_swap_request` (disetujui), `emp_leave_request`, `emp_out_permit_request`, `emp_leave_balance`, `org_position` |
| **HRM / Kedisiplinan** | ATT → HRM | Usulan `emp_disciplinary` (teguran verbal / SP) beserta bukti hitungannya |
| **HRM / Portal karyawan** | ATT → HRM | Tab baru "Absensi Saya": tap harian, status, rekap bulanan |
| **Payroll (ditunda)** | ATT → PYR | `att_period_summary` — **satu-satunya** antarmuka resmi. Payroll tidak boleh membaca `att_daily`/`att_punch` langsung |
| **Produksi / PPIC** | ATT ← PPIC | Roster shift per mesin; ATT tidak mengubah roster |
| **Flow Engine** | ATT ↔ FLOW | Approval lembur, approval koreksi, otorisasi reopen periode memakai `flow_*` yang sudah dirancang |

**Keputusan T1-13.** Payroll dilarang mengakses tabel level tap atau harian. Batas ini menjaga agar
Payroll dapat dibangun belakangan tanpa membekukan struktur internal Attendance.

---

## 10. Prinsip Desain yang Mengikat

1. **Mesin adalah fakta, manusia adalah tafsir.** Tap mentah tidak pernah diubah atau dihapus. Semua penyesuaian hidup di lapisan terpisah dan dapat ditelusuri.
2. **Hitung ulang, jangan percaya.** Semua angka olahan dari mesin diabaikan; TMERP menghitung sendiri dari tap mentah terhadap jadwal TMERP.
3. **Idempoten.** Mengimpor file yang sama dua kali tidak boleh menggandakan apa pun. Menjalankan ulang kalkulasi harus menghasilkan angka identik selama input tidak berubah.
4. **Attendance menghitung waktu, Payroll menghitung uang.** Tidak ada satu pun nilai rupiah di modul ini.
5. **Aturan adalah data, bukan kode.** Seluruh ambang (5 menit, 15 menit, 4,5 jam, 3×/bulan, 2 jam lembur, 30 menit lembur) tersimpan di tabel parameter berversi dengan tanggal berlaku — SOP sudah pernah berubah dan akan berubah lagi.
6. **Anomali terlihat, bukan tersembunyi.** Tap tidak lengkap, PIN tak dikenal, karyawan tanpa jadwal — semuanya muncul di antrean kerja, bukan diam-diam dianggap alpha atau hadir.
7. **Sistem mengusulkan tindakan disiplin, manusia memutuskan.**

---

## 11. DELTA — Perubahan yang Dibutuhkan pada Modul Lain

| ID | Modul terdampak | Perubahan |
|---|---|---|
| **DELTA-01** | HRM · `emp_shift` | Tambah dimensi **lokasi/gedung** (A5, A10, A5+A10) untuk pola shift Satpam S1–S5. Saat ini skema shift tidak punya konsep gedung sama sekali |
| **DELTA-02** | Global | **Tabel hari libur nasional** (`sys_holiday`) belum ada di modul manapun, padahal dibutuhkan oleh ATT (lembur hari besar, aturan "berurutan dengan Minggu/tanggal merah") dan sudah tercatat sebagai *delta_pending* di `HRM_STATE.yaml` untuk perhitungan H-7/H-1. Ini menjadi **blocker** bagi ATT |
| **DELTA-03** | Global | Kode entitas dokumen baru mengenal `TLS` dan `TDP`. SBU ketiga (**Indostry Packaging**) belum punya kode surat — perlu ditetapkan |
| **DELTA-04** | HRM · `emp_employee` | Tambah `finger_pin` (dan tabel `att_employee_pin` bila satu karyawan bisa punya beberapa PIN lintas mesin) |
| **DELTA-05** | HRM · `emp_shift_detail` | Seed lengkap seluruh pola jam kerja pada §2.3 — saat ini tabelnya ada tetapi isinya belum pernah didefinisikan |
| **DELTA-06** | HRM · aturan izin | Aturan "izin memotong cuti" & "izin hanya boleh bila saldo cuti habis" sudah tercatat sebagai revisi tertunda; ATT mengasumsikan ini sudah berlaku |
| **DELTA-07** | HRM · `emp_out_permit_request` | Kolom `fingerprint_out_at` / `fingerprint_in_at` yang selama ini placeholder kini **terisi otomatis** dari `att_punch` |
| **DELTA-08** | HRM · `org_position` | Butuh **level di dalam posisi** (`HELPER GUDANG LV 1/2/3`, `OP POTONG LV 1`). Sheet `REFF_KEAHLIAN` memakainya sebagai kunci tarif tunjangan keahlian. Pilihan: kolom `position_level` pada `org_position`, atau memecah tiap level menjadi baris posisi tersendiri — **perlu keputusan** |
| **DELTA-09** | HRM · `emp_shift_detail` | Butuh kolom **durasi istirahat** per shift per hari, karena istirahat dipotong sebagai konstanta (§2.8a) dan besarnya berbeda antar pola (produksi hari Minggu memakai 1 jam pada pola 05.00–14.00) |
| **DELTA-10** | HRM · `emp_shift_detail` | Butuh flag **apakah shift ini mewajibkan tap istirahat**, untuk menentukan tampil/tidaknya kolom istirahat di layar absensi (Keputusan T1-21) |

---

## 12. Pemisahan Attendance vs Payroll

| Aspek | Attendance (aktif) | Payroll (tetap ditunda) |
|---|---|---|
| Pertanyaan yang dijawab | Karyawan ini hadir seperti apa? | Karyawan ini dibayar berapa? |
| Satuan | Menit, jam, hari | Rupiah |
| Periode | Mingguan (closing) + bulanan (disiplin) | Mingguan (mengikuti periode ATT) |
| Keluaran | `att_period_summary` | Slip gaji |
| Ketergantungan | Tidak butuh Payroll | Butuh Attendance |

Isi lama `TIER_1..4_PAYROLL.md` terbelah: bagian F1 (import finger), F2 (kalkulasi status), F5
(crosscheck lembur), F6 (toleransi tidak finger) dan katalog status **pindah ke modul ATT**;
bagian F3 (matriks status → komponen gaji), F7 (dampak SP terhadap gaji), F9 (payroll run), F10
(uang roti) **tetap di Payroll**.

---

## 13. Rencana Bertahap

| Tahap | Isi | Status |
|---|---|---|
| **v1** | Import file export mesin; seluruh fitur A1–A12 | Dokumen ini |
| v2 | Tarik data langsung dari mesin (SDK/ADMS/jaringan), menghapus langkah export manual | Belum dijadwalkan |
| v3 | Dasbor kehadiran real-time untuk PPIC (siapa sudah tap di mesin apa) | Belum dijadwalkan |

**Keputusan T1-14.** v1 sengaja berbasis file. Alasannya: HR sudah terbiasa melakukan export, jalur
ini tidak menambah risiko jaringan ke server Malang, dan seluruh logika normalisasi yang dibangun di
v1 akan tetap terpakai bulat-bulat saat v2 mengganti sumbernya. Membangun integrasi langsung lebih
dahulu berarti menunda seluruh nilai modul demi kenyamanan satu langkah operasional.

---

## 14. Tindak Lanjut yang Harus Dilakukan Telma

1. **Review dokumen ini**, khususnya sepuluh keputusan bertanda T1-01 s.d. T1-14 dan tiga konflik di §15.
2. Setelah disetujui: `TIER_2_ATTENDANCE.md` (logika & algoritma), `TIER_3_ATTENDANCE.sql` (DDL), `TIER_4_ATTENDANCE.md` (wireframe & mockup).
3. **Buat `ATTENDANCE_STATE.yaml`** dan daftarkan modul di `GLOBAL_START_HERE.md` — status HR yang semula "ditunda seluruhnya" kini punya dua pengecualian aktif: HRM Master Employee dan Attendance.
4. **Perbarui `TIER_0_GLOBAL_ARCHITECTURE.md`** dengan database `tm2hrm` dan domain `att_*`.
5. **Split `TIER_1..4_PAYROLL.md`** sesuai §12 — jangan biarkan dua dokumen mengklaim aturan yang sama.
6. Putuskan DELTA-01 s.d. DELTA-10, terutama **DELTA-02 (tabel hari libur)** yang menjadi blocker.
7. Jawab dua pertanyaan konsep baru di **§16.5** (izin dalam satuan jam, jam rapat).

---

## 15. Konflik Sumber yang Menunggu Keputusan

Tiga hal berikut **bertentangan di dalam dokumen sumbernya sendiri**. Saya cantumkan usulan
penyelesaian, tetapi keputusan akhir ada pada Telma karena ini kebijakan perusahaan, bukan teknis.

### KONFLIK-01 — Denda: ada atau tidak? *(direvisi di v1.2 — sebagian besar sudah selesai)*

SOP butir **B.2** menyatakan perusahaan **tidak menerapkan sistem denda** atas perizinan, pelanggaran
hanya dikenakan teguran verbal. Namun SOP butir **F.5** dalam badan dokumen yang sama menyatakan
**"Alpha mendapatkan sanksi senilai Rp 100.000"**. Sementara denda Rp 25.000 (terlambat) dan
Rp 10.000 (tidak finger) **hanya muncul di PPTX dan di matriks halaman 6–8** — keduanya artefak
tingkat 3.

*Penyelesaian (mengikuti §0):* denda Rp 25.000 dan Rp 10.000 **gugur** karena tidak ada di badan SOP
yang ditandatangani, dan justru bertentangan langsung dengan B.2. Kemungkinan besar keduanya adalah
warisan praktik sebelum SOP terbit yang terbawa oleh analis. Yang tersisa hanya satu pengecualian
yang disebut eksplisit oleh Direksi: **Alpha Rp 100.000** (F.5), yang dibaca sebagai pengecualian
tegas atas kebijakan payung B.2.

Bagaimanapun, **modul ATT tidak menyimpan satu pun angka rupiah**. Yang dicatat hanyalah jumlah
kejadian. Jadi konflik ini tidak memblokir modul ini sama sekali — dampaknya baru terasa di Payroll.
**Perlu konfirmasi** hanya untuk memastikan pembacaan ini benar sebelum Payroll dikerjakan.

### KONFLIK-02 — Batas cuti berturut-turut

SOP butir **G.2** menyatakan cuti maksimal **3 hari berturut-turut**. Ini bertentangan dengan hak
cuti tahunan 12 hari yang lazim dan dengan skema cuti melahirkan 2–3 bulan yang tercatat di spec
Payroll lama.

*Usulan:* G.2 dibaca berlaku untuk **cuti tahunan reguler** saja, tidak berlaku bagi cuti melahirkan
dan cuti khusus/penting. **Perlu konfirmasi eksplisit** karena berdampak langsung pada validasi form.

### KONFLIK-03 — Batas waktu penyerahan dokumen sakit

SOP butir **C.3** menyebut dokumen diserahkan **paling lambat hari H** saat masuk kerja. SOP butir
**E.4** menyebut **paling lambat H+1**. Matriks lampiran menyebut **H+1** untuk surat izin.

*Usulan:* C.3 (hari H) berlaku khusus untuk **sakit**; E.4 (H+1) berlaku untuk **izin mendadak**.
Pembacaan ini konsisten karena keduanya berada di bab yang berbeda dan mengatur objek yang berbeda.
Nilai keduanya tetap disimpan sebagai parameter agar mudah diubah.

### KONFLIK-04 — Syarat jam pulang untuk Bonus Hadir: 16:30 atau 17:00?

Formula pada sheet `TA` mensyaratkan jam pulang **≥ 16:30**. Namun sebuah catatan yang ditinggalkan
penyusunnya di sheet yang sama berbunyi bahwa untuk kolom Bonus Hadir ditambahkan aturan: bila jam
pulang **< 17:00** maka hasilnya 0. Kedua ketentuan itu hidup berdampingan di file yang sama dan
saling bertentangan.

*Usulan:* nilai ambang **tidak dipatok** melainkan disimpan sebagai parameter per shift
(`bonus_hadir_min_checkout`), karena 16:30 adalah jam pulang PRODUKSI Offset sementara 17:00 adalah
jam pulang STAFF — besar kemungkinan keduanya benar untuk kelompok yang berbeda, dan konfliknya
muncul justru karena Excel memaksa satu angka untuk semua orang. **Perlu konfirmasi.**

---

## 16. Keluaran yang Diminta Pengguna

### 16.1 Sumber acuan

Telma memberikan tangkapan layar sebuah aplikasi absensi lain (footer bertanda versi 1.0.0) sebagai
**acuan bentuk keluaran** yang diharapkan muncul di profil masing-masing karyawan, disertai arahan
bahwa **laporan harian ke HR dibuat dalam bentuk yang lebih sederhana**.

Layar acuan tersebut berisi empat blok: Data Profile, Filter rentang tanggal, dua kartu Summary, dan
Detail Tabel Absensi.

Acuan ini diperlakukan sebagai **inspirasi tata letak**, bukan spesifikasi yang disalin bulat-bulat.
Beberapa bagiannya justru mengandung cacat desain yang harus dihindari — lihat §16.4.

### 16.2 Layar A — Absensi Saya (portal karyawan)

Menjadi **tab ke-9** pada portal self-service karyawan yang sudah dirancang di modul HRM
(sebelumnya 8 tab). Mobile-first, read-only atas data diri sendiri.

**Blok 1 — Ringkas Profil**
Foto, nama, departemen, posisi, nomor badge, tanggal masuk, masa kerja. Ditarik dari `emp_employee`,
**tidak diketik ulang**.

Acuan juga menampilkan *masa pensiun* beserta hitung mundur usia 60 tahun. Itu informasi kepegawaian,
**bukan attendance** — tempatnya di tab Data Saya, bukan di sini. Dicatat sebagai usulan untuk modul
HRM, bukan bagian modul ini.

**Blok 2 — Filter**
Rentang tanggal (dari–sampai) + tombol Terapkan dan Reset. Default: **periode attendance berjalan**,
bukan bulan kalender, agar sejalan dengan siklus mingguan (§2.6).

**Blok 3 — Kartu Ringkasan** (dua kartu berdampingan)

| Kartu 1 — Kehadiran & Perizinan | Kartu 2 — Lembur |
|---|---|
| Hadir · Terlambat (jumlah kejadian **dan** total menit) · Pulang awal · Keluar jam kerja | Lembur hari kerja (jam) |
| Sakit · Izin · Izin disetujui · Alpha · Cuti terpakai · **sisa saldo cuti** | Lembur hari libur / Minggu (jam) |
| Tidak finger (jumlah) | Jumlah hari masuk pada hari libur |
| Sisa kuota izin bulan berjalan | Hari yang layak Uang Roti |

Tombol **[Ajukan Surat Izin]** di kartu 1, memicu pembuatan `att_permit_letter` (§7) dengan tanggal
sudah terisi dari baris yang dipilih.

**Blok 4 — Detail Tabel Absensi**

Kolom yang **diadopsi** dari acuan:

| Kolom | Isi |
|---|---|
| Tanggal | Tanggal + nama hari |
| Masuk | Tap masuk |
| Izin Keluar / Izin Masuk | Tap keluar-masuk karena Surat Izin Keluar (KJK) |
| Istirahat Keluar / Istirahat Masuk | Tap istirahat, **bila ada** (lihat §16.4 butir 1) |
| Pulang | Tap pulang |
| Keterangan | Nama shift + kondisi, atau nama hari libur, atau sebab ketidakhadiran |

Kolom yang **ditambahkan** karena acuan tidak memilikinya padahal justru paling dibutuhkan:

| Kolom tambahan | Alasan |
|---|---|
| **Jadwal** (jam masuk–pulang shift hari itu) | Tanpa ini karyawan tidak bisa menilai sendiri apakah 07:00 itu terlambat. Untuk karyawan shift, jadwal berubah tiap hari |
| **Terlambat (menit)** | Angka, bukan sekadar kata "Terlambat" |
| **Jam kerja efektif** | Dasar aturan 4,5 jam (§6.2); karyawan berhak melihatnya |
| **Lembur (jam)** | Acuan sama sekali tidak menampilkan lembur per hari, padahal ini yang paling sering dipersoalkan |
| **Penanda dokumen** | Ikon bila hari itu sudah dilegalkan surat, dengan nomor suratnya. Tanpa ini, tombol "Ajukan Surat Izin" tidak punya umpan balik |

**Keputusan T1-20 — menampilkan dua status.** Setiap baris menampilkan status akhir, dan bila berbeda
dari status mesin, keduanya ditampilkan berdampingan (mis. `Alpha → Izin · surat 041/SI.TLS/VIII/2026`).
Ini penerapan langsung dari T1-11: karyawan berhak melihat apa yang dicatat mesin dan apa yang
akhirnya diputuskan, beserta dasarnya.

### 16.3 Layar B — Laporan Harian HR (bentuk sederhana)

Menggantikan sheet `LAP ABSEN` yang sekarang diketik manual. **Satu halaman, satu tanggal**, dibuka
tiap pagi.

Isinya hanya **daftar penyimpangan**, bukan daftar seluruh karyawan — karyawan yang hadir normal
tidak perlu disebut sama sekali. Dikelompokkan per shift, sesuai kebiasaan yang sudah berjalan:

```
LAPORAN ABSENSI HARIAN — Senin, 24 Agustus 2026
Terjadwal 128 · Hadir 119 · Menyimpang 9

▸ SHIFT 1 & REGULER
  Tidak masuk      3   [nama · status · nomor surat bila ada]
  Terlambat        2   [nama · jadwal · aktual · selisih menit]
  Pulang awal      1   [nama · jadwal · aktual]
  Tap tidak lengkap 1  [nama · tap yang hilang]

▸ SHIFT 2
  ...

▸ PERLU TINDAKAN HR
  Dirumahkan       1
  PIN tak dikenal  1
  Tidak terjadwal  2
```

Tiga prinsip yang membedakannya dari acuan:

1. **Hanya penyimpangan.** Laporan harian yang memuat seluruh karyawan tidak akan dibaca.
2. **Setiap baris dapat ditindaklanjuti** — mengkliknya membuka baris `att_daily` untuk dikoreksi
   atau diterbitkan suratnya.
3. **Blok "Perlu Tindakan HR" terpisah** dari penyimpangan biasa, karena isinya adalah masalah
   **kualitas data** (PIN tak dikenal, karyawan tanpa jadwal) yang menghambat closing (§8.2), bukan
   masalah kedisiplinan karyawan.

### 16.4 Cacat pada layar acuan yang TIDAK boleh ditiru

**1. Kolom istirahat akan kosong pada data Telasih.**
Acuan menyediakan kolom Istirahat Keluar/Masuk dan mengisinya. Namun data mesin Telasih yang nyata
(sheet `Catatan`, §2.2) menunjukkan **mayoritas hari hanya berisi dua tap**. Bila kolom ini ditiru
apa adanya, tabel akan penuh tanda strip dan terasa rusak.

**Keputusan T1-21.** Kolom istirahat ditampilkan **hanya bila shift bersangkutan mewajibkan tap
istirahat** (atribut pada `emp_shift_detail`). Untuk shift yang tidak mewajibkan, kolomnya
disembunyikan seluruhnya, bukan ditampilkan kosong. Ini juga konsisten dengan T1-15: tap istirahat
bersifat informatif dan tidak memengaruhi perhitungan jam kerja.

**2. Menyamakan "tidak ada tap" dengan "Alpha" — kesalahan yang paling serius.**
Pada layar acuan, tanggal 5, 6, 10, 11, 14, dan 21 Agustus semuanya bertanda
**"Alpha — Tidak ada keterangan"**, sementara hari kerja lain di antaranya terisi normal. Polanya
tidak beraturan dan tidak menyerupai perilaku orang yang benar-benar membolos.

Ada dua kemungkinan, dan keduanya sama-sama menunjukkan cacat: entah karyawan itu memang tidak
dijadwalkan pada hari-hari tersebut, atau tap-nya gagal terekam. Sistem acuan tidak membedakan
keduanya dan langsung menyimpulkan Alpha.

Konsekuensinya bukan sepele. Menurut SOP butir K.6, tidak hadir tanpa keterangan lima hari kerja
berturut-turut diproses sebagai pemutusan hubungan kerja. Sistem yang menuduh Alpha secara serampangan
bukan hanya menghasilkan angka yang salah — ia menghasilkan **dasar tindakan hukum yang salah**.

**Keputusan T1-22.** Alpha **hanya** boleh disimpulkan bila ketiganya terpenuhi: karyawan berstatus
aktif, **ada baris roster** untuk hari itu, dan tidak ada dokumen apa pun. Bila tidak ada roster →
`NOSCH`. Bila hari libur → `LIB` atau `HL`. Bila ada sebagian tap tetapi tidak lengkap →
`is_missing_punch`, bukan Alpha. Inilah alasan ketiga kode tersebut ada di katalog §5.1, dan
tangkapan layar ini adalah bukti nyata mengapa ketiganya diperlukan.

**3. Keterangan hanya berupa teks bebas.**
Nilai seperti "Shift Pagi Terlambat" atau "Alpha - Tidak ada keterangan" adalah kalimat, bukan kode.
Akibatnya tidak bisa diagregasi, disaring, atau dihitung. TMERP menyimpan **kode status** (§5.1) dan
merangkai kalimatnya saat ditampilkan — bukan sebaliknya.

**4. Nama hari libur nasional muncul sebagai keterangan.**
Baris 17 Agustus berbunyi "Hari Kemerdekaan Republik…". Ini justru **fitur yang benar** dan sekaligus
menegaskan bahwa tabel hari libur (**DELTA-02**) adalah prasyarat, bukan pelengkap: tanpa itu,
tanggal merah akan jatuh sebagai Alpha bagi seluruh karyawan sekaligus.

### 16.5 Konsep baru dari acuan yang perlu keputusan Telma

Dua hal muncul di layar acuan tetapi **tidak ada di sumber Telasih manapun** — tidak di SOP, tidak di
kertas kerja, tidak di PPTX:

| Konsep | Terlihat sebagai | Pertanyaan |
|---|---|---|
| **Izin dalam satuan jam** | "Ijin Dalam Jam : 0 Jam 0 Menit" | Apakah Telasih mengenal izin sebagian hari yang dihitung berjam? Bila ya, ini berbeda dari Izin Keluar (KJK) dan butuh perlakuan tersendiri pada saldo serta status harian |
| **Jam rapat** | "Total Rapat : 0 Jam" pada kartu lembur | Apakah jam rapat di luar jam kerja diperlakukan terpisah dari lembur biasa? |

Keduanya **tidak saya masukkan** ke rancangan sampai ada konfirmasi. Menambahkan konsep yang tidak
dipakai perusahaan hanya akan menambah kolom kosong dan kebingungan.

---

*Akhir TIER 1 — ATTENDANCE v1.2*
