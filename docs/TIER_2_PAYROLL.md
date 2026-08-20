# TIER 2 — PRESENSI & PAYROLL · BUSINESS LOGIC & RULES
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 2 — Presensi & Payroll · Business Logic & Rules |
| **Versi** | 1.0 |
| **Bergantung pada** | Tier 1 (definisi status), khususnya Section 4 Open Items — **rule di dokumen ini mengasumsikan resolusi Open Item #1 & #2 (toleransi 3x/periode)** |
| **Prinsip desain kunci** | Seluruh angka (nominal denda, threshold menit, persentase potongan) **HARUS jadi data di `pyr_status_rule`, bukan hardcode di kode**. Alasan: perusahaan mengubah nominal ini dari waktu ke waktu (lihat riwayat slide yang berbeda antar section) — kalau hardcode, tiap perubahan kebijakan = minta Claude Code ubah kode. |

---

## SECTION 2 — KOMPONEN GAJI (dasar rule matrix)

| Kode | Nama Komponen | Sifat |
|---|---|---|
| POKOK | Gaji Pokok | Bisa dipotong proporsional per menit hilang |
| TJ_KEAHLIAN | Tunjangan Keahlian | Bisa dipotong proporsional per menit hilang (sama seperti pokok) |
| TJ_LOYALITAS | Tunjangan Loyalitas | Umumnya all-or-nothing per status |
| TJ_PRESTASI | Tunjangan Prestasi | Umumnya all-or-nothing per status |
| BONUS_PAGI | Bonus Pagi | Hilang total jika ada pelanggaran waktu masuk (T-5/T+5/T+15/PAT-5/PAT+5) |
| BONUS_HADIR | Bonus Hadir | Hilang total jika ada pelanggaran waktu pulang atau tidak hadir |
| DENDA | Denda (potongan tetap, bukan proporsional) | Nominal tetap per event pelanggaran |

---

## SECTION 3 — FORMULA PRORATA (dasar perhitungan "diperhitungkan")

```
menit_hilang = ABS(jam_finger_aktual − jam_shift_acuan)
durasi_shift_menit = total menit kerja shift (dari emp_shift, per hari)

potongan_pokok      = (menit_hilang / durasi_shift_menit) × gaji_pokok_harian
potongan_keahlian   = (menit_hilang / durasi_shift_menit) × tj_keahlian_harian
```

- `jam_shift_acuan` diambil dari `emp_shift` MILIK PEGAWAI ITU pada tanggal terkait (bukan jam default 07:30 — itu cuma asumsi ilustrasi di pptx, actual pakai shift masing-masing, termasuk shift malam/non-Offset yang beda).
- Untuk kasus **Keluar pada Jam Kerja** (KJK): `menit_hilang` = total durasi keluar-masuk di tengah shift (bisa lebih dari satu window per hari — perlu keputusan: dijumlah atau window terpanjang? → **[DELTA-CONFIRM] diasumsikan dijumlah**).
- Komponen `TJ_LOYALITAS` dan `TJ_PRESTASI` **tidak proporsional** — either penuh atau hilang total sesuai matrix §4.

---

## SECTION 4 — RULE MATRIX (data-driven, tabel `pyr_status_rule`)

### 4.1 Status Non-hadir / Administratif (sumber: Slide 7)

| Status | POKOK | TJ_KEAHLIAN | TJ_LOYALITAS | TJ_PRESTASI | BONUS_PAGI | BONUS_HADIR | DENDA | Catatan |
|---|:--:|:--:|:--:|:--:|:--:|:--:|---|---|
| H | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | — | |
| S (Sakit) | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ | — | Wajib surat dokter + kwitansi/surat obat; tanpa dokumen → reklasifikasi ke A |
| I (Izin) | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | Rp 25.000 | |
| IS (Izin Disetujui) | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ | — | |
| A (Alpha) | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | Rp 100.000 | |
| F (Tidak Finger 1x) | ✔ | ✔ | ✔ | ✔ | ✘ | ✔ | Rp 10.000/event | Lihat §4.3 |
| FF (Tidak Finger 2x) | ✔ | ✔ | ✔ | ✔ | ✘ | ✘ | Rp 10.000/event | Akumulasi, lihat §4.3 |
| C (Cuti) | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ | — | Wajib diajukan H-7; jika tidak → reklasifikasi ke I |
| CL (Cuti Melahirkan) | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ | — | Skema di §5 |
| DRM (Dirumahkan) | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ | — | Status kepegawaian, bukan pengajuan harian |
| SKR (Skorsing) | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | — | |
| SP1 aktif | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | **50% gaji harian × 3 hari berturut** | Lihat §6 |
| SP2 aktif | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | **50% gaji harian × 7 hari berturut** | Lihat §6 |
| SP3 aktif | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | — | Skorsing 7 hari atau PHK — keputusan HR, sistem hanya set status |

### 4.2 Status Time-variant (sumber: Slide 2–5, 8, 10–16 — sudah dikonsolidasi & konflik diselesaikan sesuai Open Item #1)

| Status | Threshold | POKOK | TJ_KEAHLIAN | TJ_LOYALITAS | TJ_PRESTASI | BONUS_PAGI | BONUS_HADIR | DENDA | Counter reset |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|---|---|
| T-5 | <5 menit telat | prorata | prorata | ✔ | ✔ | ✘ | ✔ | — | — |
| T+5 (ke-1 s.d. ke-3 dlm periode) | 6–15 menit telat | prorata | prorata | ✔ | ✔ | ✘ | ✔ | — | Counter per periode payroll |
| T+5 (ke-4 dst dlm periode) | 6–15 menit telat, akumulasi >3x | prorata | prorata | ✔ | ✔ | ✘ | ✔ | **Rp 25.000** | — |
| T+15 | >15 menit telat | prorata | prorata | ✔ | ✔ | ✘ | ✔ | **Rp 25.000** | Selalu, tanpa syarat 3x |
| PA (Pulang Awal) | pulang sebelum jam shift | prorata | prorata | ✔ | ✔ | ✘ | ✘ | — | — |
| PAT-5 | PA + T-5 hari sama | prorata (akumulasi keduanya) | prorata | ✔ | ✔ | ✘ | ✘ | — | — |
| PAT+5 | PA + T+5 hari sama | prorata (akumulasi keduanya) | prorata | ✔ | ✔ | ✘ | ✘ | **Rp 25.000** | — |
| KJK (Keluar Jam Kerja) | keluar-masuk tengah shift | prorata | prorata | ✔ | ✔ | ✔ | ✘ | — | — |

> **Catatan PAT-5/PAT+5:** kolom POKOK/TJ_KEAHLIAN di slide 8 untuk baris ini tertulis ✘ total (bukan prorata) — beda dengan narasi slide 3/4 yang bilang "potongan gaji pokok sejumlah menit hilang". **[DELTA-CONFIRM]** dokumen ini pakai versi **prorata** (konsisten dengan status PA/T-5/T+5 lain serta narasi slide 3-4), karena versi "✘ total" di tabel slide 8 kemungkinan simplifikasi visual, bukan aturan sungguhan — **wajib dikonfirmasi Telma/Jo sebelum Tier 3 dikunci**.

### 4.3 Toleransi Tidak Finger (F/FF)

- F (tidak finger 1x dalam periode): potongan tetap Rp 10.000, komponen lain tetap penuh **kecuali** BONUS_PAGI (hilang).
- FF (tidak finger 2x dalam periode, akumulasi): potongan tetap Rp 10.000 (per event, jadi total Rp 20.000 untuk FF), BONUS_PAGI dan BONUS_HADIR keduanya hilang.
- Counter di-reset tiap awal periode payroll.
- Tidak finger ke-3 dst dalam periode → **[Open Item]** tidak disebutkan aturannya di sumber, kemungkinan reklasifikasi ke Alpha atau perlu tier baru (FFF?). Perlu konfirmasi.

### 4.4 Lembur (LAw/LAk) — bukan potongan, tapi tambahan

- Dihitung dari **Durasi Lembur** hasil approval `emp_overtime_request`, bukan dari selisih jam finger murni (karena bisa ada Lembur Awal & Akhir di hari yang sama → total durasi lembur diambil dari rekap, bukan dijumlah dua window terpisah secara naif).
- **Uang Roti**: Rp 5.000 flat jika total durasi lembur harian **>2 jam**, khusus role **Staff & SPV Produksi**, **tidak berlaku kelipatan** (max Rp 5.000/hari berapapun jam lemburnya di atas 2 jam).
- Nominal lembur per jam **tidak disebutkan di sumber** (kemungkinan mengikuti aturan lembur pemerintah/UU Ketenagakerjaan yang berlaku umum) — **Open Item**, harus diklarifikasi karena ini komponen penambah gaji terbesar yang belum ada formulanya.

---

## SECTION 5 — CUTI MELAHIRKAN (CL) — skema pilihan

Dua skema, dipilih karyawan saat pengajuan (field `cl_scheme` di `emp_leave_request`):

| Skema | Durasi | Pembayaran |
|---|---|---|
| A | Maks 2 bulan | 2 bulan **dibayar penuh** |
| B | Maks 3 bulan | 2 bulan dibayar penuh + 1 bulan **tanpa dibayar** |

Sistem harus generate `att_daily_status = CL` otomatis untuk seluruh rentang tanggal cuti begitu approved, dan payroll run harus tahu bulan ke berapa dari cuti tsb untuk menentukan dibayar/tidak (field `cl_month_sequence` di hasil kalkulasi, bukan re-hitung manual tiap bulan).

---

## SECTION 6 — SURAT PERINGATAN (SP1/SP2/SP3) — mekanisme "berturut-turut"

- SP1/SP2 memotong 50% **gaji harian** (bukan gaji pokok bulanan) selama **N hari kerja berturut-turut** sejak SP diterbitkan (N=3 untuk SP1, N=7 untuk SP2).
- "Berturut-turut" = hari kerja aktual (skip hari libur/off shift), bukan kalender.
- Jika dalam masa berjalan SP karyawan juga Izin/Cuti/Alpha di hari yang sama → **[Open Item]** urutan prioritas potongan tidak disebutkan. Diasumsikan: potongan SP tetap jalan berdasar hari kerja terjadwal, ditambah (bukan digantikan) potongan status hari itu — perlu konfirmasi karena berisiko dobel-potong.
- SP3 = keputusan administratif murni (skorsing 7 hari atau PHK), sistem hanya mencatat status, tidak menghitung ulang formula gaji otomatis untuk kasus PHK (final settlement di luar scope payroll rutin).

---

## SECTION 7 — URUTAN EVALUASI RULE (saat job kalkulasi harian jalan)

1. Ambil `emp_shift` aktif pegawai pada tanggal tsb.
2. Jika ada `emp_leave_request`/`emp_overtime_request` approved untuk tanggal tsb → status non-hadir/lembur menang, **skip** kalkulasi time-variant dari finger.
3. Jika tidak ada leave request tapi juga tidak ada data finger sama sekali → default **A (Alpha)**, kecuali ada SKR/DRM aktif yang menaungi tanggal tsb.
4. Jika ada data finger → hitung status time-variant sesuai §4.2, termasuk cek counter T+5 (3x) dan counter F/FF (§4.3) terhadap akumulasi periode berjalan.
5. Terapkan status administratif periode (SP1/SP2/SP3 aktif) sebagai **layer tambahan**, bukan pengganti — lihat catatan Tier 1 §2.4.
6. Simpan hasil ke `att_daily_status` dengan breakdown komponen (bukan cuma kode status), supaya payroll run tinggal SUM, tidak re-derive logic.
