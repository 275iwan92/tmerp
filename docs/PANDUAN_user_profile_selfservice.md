# Panduan — Portal User Profile (Self-Service Karyawan)

**Untuk dibaca Telma.** Dokumen ini menjelaskan apa yang bisa dilakukan karyawan
di portal profil pribadinya, dan bagaimana alur persetujuannya ke HR. Ini bukan
spec teknis untuk Claude Code — hanya penjelasan fungsi.

Mockup referensi: `mockup_personal_data_selfservice.html`

---

## Prinsip utama (WAJIB dipegang)

1. **Hanya bisa dibuka oleh pemilik akun sendiri.** Karyawan yang login hanya
   melihat & mengelola datanya sendiri. Tidak ada karyawan yang bisa melihat atau
   mengubah profil karyawan lain lewat portal ini. (HR tetap bisa lihat semua,
   tapi lewat panel HR Admin yang terpisah — bukan portal ini.)

2. **Data pribadi bersifat read-only.** Karyawan tidak bisa mengedit langsung.
   Perubahan apa pun harus lewat **pengajuan** yang disetujui HR dulu.

3. **Semua pengajuan masuk ke HR untuk di-approve, dan HR bisa dinotifikasi.**
   Tidak ada perubahan yang otomatis berlaku tanpa persetujuan HR.

4. **Prinsip upah: "no present no pay".** Hari di mana karyawan tidak hadir, upah
   hari itu tidak dibayar — KECUALI dua hal: (a) **Sakit dengan surat dokter**,
   dan (b) **Cuti** (karena cuti adalah hak berbayar). Izin biasa = tidak dibayar.

---

# BAGIAN 1 — KATALOG PENGAJUAN

Dibagi tiga kelompok: (A) Waktu/Kehadiran, (B) Perubahan Data, (C) Dokumen &
Portofolio.

> **Catatan parameter:** angka-angka aturan (jatah cuti 12 hari, jarak H+7,
> maksimal 3 hari/pengajuan, dst) adalah **parameter yang bisa dikonfigurasi**,
> bukan angka mati. Nilai di bawah adalah yang berlaku SAAT INI dan bisa diubah
> HR/manajemen tanpa mengubah kode.

---

## A. Pengajuan Waktu / Kehadiran

### A.1 Cuti (berbayar)

- **Jatah:** 12 hari kerja per tahun (parameter, saat ini 12).
- **Saldo ditampilkan** di form: sisa hari cuti yang masih bisa diambil.
- **Aturan jarak H+7:** kalender pemilih tanggal **hanya membuka tanggal mulai
  H+7** dihitung dari hari form dibuka. Contoh: hari ini 18 Agu 2026 → tanggal
  paling awal yang bisa dipilih adalah 7 hari kerja setelahnya. Ini otomatis
  mencegah pengajuan cuti mendadak (< 7 hari). Bukan sekadar peringatan — tanggal
  di bawah H+7 memang tidak muncul/tidak bisa dipilih.
- **Hari yang bisa dipilih:** hitungan **hari kerja Senin–Sabtu**. Yang **tidak
  bisa** dipilih: **hari Minggu** dan **hari besar/libur nasional** (otomatis
  di-disable di kalender).
- **Maksimal per pengajuan:** 3 hari kerja dalam satu pengajuan. Kalau butuh lebih,
  karyawan bisa membuat pengajuan terpisah lagi (selama saldo masih ada).
- **Efek:** memotong saldo cuti. Upah tetap dibayar.

### A.2 Sakit (berbayar, bila ada surat dokter)

- **Lampiran surat dokter/RS wajib**, TAPI dilampirkan **setelah karyawan masuk
  kerja kembali** (bukan saat hari sakit). Jadi alurnya: karyawan sakit → masuk
  lagi → ajukan pengajuan sakit + upload surat dokter untuk tanggal tsb.
- **Efek:** bila surat dokter valid → hari itu tetap dibayar (pengecualian dari
  "no present no pay"). Tidak memotong saldo cuti.
- Tanpa surat dokter, hari sakit diperlakukan seperti tidak hadir (tidak dibayar).

### A.3 Izin (tidak dibayar)

- Untuk keperluan pribadi di luar sakit/cuti.
- **Efek:** mengikuti "no present no pay" — upah hari itu tidak dibayar.
- Tidak memotong saldo cuti (karena bukan cuti).

### A.4 Cuti Khusus Berbayar (sesuai ketentuan)

Terpisah dari cuti tahunan — **tidak memotong saldo 12 hari**. Contoh peristiwa:
menikah, menikahkan/khitan anak, istri melahirkan, keluarga inti meninggal,
ibadah (mis. haji). Jumlah hari per peristiwa mengikuti ketentuan (parameter).
Perlu lampiran pendukung sesuai jenisnya (mis. surat nikah, akta).

### A.5 Tukar Shift (Shift Swap)

Karyawan mengajukan tukar jadwal shift dengan rekan. Perlu **persetujuan rekan
yang diajak tukar + PPIC** (karena menyangkut penempatan mesin). Setelah disetujui,
jadwal roster diperbarui.

---

## B. Pengajuan Perubahan Data

### B.1 Koreksi Ejaan / Salah Ketik

Nama, dan field teks lain yang salah tulis. Karyawan klik "Ajukan Koreksi" →
isi nilai baru + alasan → approval HR. Field sensitif (**NIK KTP, tanggal lahir**)
selalu wajib approval, tidak boleh auto.

### B.2 Perubahan Alamat (dipisah dua)

- **Alamat KTP** — sesuai kartu identitas.
- **Alamat domisili/tinggal** — tempat tinggal sebenarnya.

Keduanya **disimpan terpisah** supaya kasus "alamat tinggal ≠ alamat KTP" tertangani
dengan benar (umum untuk perantau). Karyawan bisa mengajukan perubahan salah satu
atau keduanya.

### B.3 Perubahan Anggota Keluarga

- **Kelahiran baru** → tambah anggota keluarga (anak).
- **Anggota meninggal** → **ditandai meninggal + tanggal**, TIDAK dihapus (histori
  tetap tersimpan untuk keperluan tanggungan/administrasi).
- **Tambah pasangan** (setelah menikah).

### B.4 Perubahan Status Pernikahan

Lajang → menikah (atau perubahan lain). Berpengaruh ke tanggungan pajak (PTKP) &
BPJS, jadi perlu approval + lampiran (mis. surat nikah).

### B.5 Perubahan Rekening Bank

Kalau ganti bank/nomor rekening — penting untuk penggajian. Perlu approval HR.

### B.6 Kontak Darurat

Nama + nomor telepon kerabat yang bisa dihubungi saat darurat. Bisa
ditambah/diperbarui.

---

## C. Dokumen & Portofolio

### C.1 Dokumen Identitas (upload/perbarui)

KTP, SIM, SKCK (surat berkelakuan baik), Kartu Keluarga, Passport, NPWP.

- Dokumen yang **diupload sendiri** → bisa diganti sendiri.
- Dokumen yang **diupload HR** → terkunci read-only di sisi karyawan.
- Untuk dokumen bermasa berlaku (SIM/SKCK/Passport): simpan tanggal berlaku →
  sistem bisa mengingatkan bila hampir/sudah kadaluarsa → karyawan upload versi
  baru (**perpanjangan dokumen**).

### C.2 Pendidikan & Pengembangan (untuk nilai investasi employee)

Mencakup: **sertifikasi, seminar, pelatihan/training, pendidikan formal**. Tiap
entri menyimpan:

| Field | Guna |
|---|---|
| Jenis | Sertifikasi / Seminar / Pelatihan / Pendidikan formal |
| Nama & penyelenggara | Identitas kegiatan |
| Tanggal & durasi | Kapan & berapa lama |
| **Biaya** | Nilai rupiah kegiatan |
| **Dibiayai oleh** | Perusahaan / Pribadi / Sharing |
| File sertifikat | Bukti/pendukung |
| Masa berlaku | Bila sertifikasi ada expiry |
| **Ikatan dinas** | Flag bila ada bonding |

**Nilai investasi employee:** HR bisa menjumlahkan total biaya yang **dibiayai
perusahaan** per karyawan — berguna untuk keputusan retensi & ikatan dinas. Entri
yang dibiayai pribadi tetap masuk portofolio karyawan, tapi **tidak dihitung**
sebagai investasi perusahaan.

### C.3 Keterampilan / Sertifikasi Keahlian

Keterampilan teknis (mis. "Operator Cetak Offset", "K3 Dasar", "Forklift") +
file sertifikat. Entri dari HR terkunci; entri sendiri bisa diedit.

---

## D. Pengunduran Diri (Resign)

Pengajuan formal berhenti kerja dengan **tanggal efektif**. Alur khusus:
karyawan ajukan → HR/atasan proses (biasanya ada tahap serah-terima). Ini
pengajuan bermuatan tinggi — perlu jejak audit yang jelas. Setelah efektif,
status karyawan berubah menjadi nonaktif (resign_date + resign_reason terisi).

---


# BAGIAN 2 — YANG HANYA BISA DILIHAT (tidak bisa diubah)

- **Jadwal Saya**: menampilkan jadwal shift, tapi **hanya yang sudah CONFIRMED**
  oleh PPIC/HR. Versi draft tidak muncul. Karyawan tidak bisa mengubah jadwalnya.
- **Kartu Pengenal**: karyawan hanya melihat versi digital badge-nya. Pencetakan
  kartu fisik tetap wewenang HR.
- **Saldo Cuti**: sisa hari cuti ditampilkan (read-only), dihitung oleh engine cuti.

---

# BAGIAN 3 — ENGINE CUTI (aturan yang berlaku saat ini)

Semua nilai di bawah adalah **parameter** — berlaku sekarang, bisa diubah nanti.

| Aturan | Nilai saat ini |
|---|---|
| Jatah cuti tahunan | 12 hari kerja / tahun |
| Jarak minimum pengajuan | H+7 (tanggal mulai paling awal = 7 hari kerja dari hari form dibuka) |
| Maksimum per pengajuan | 3 hari kerja |
| Hari yang bisa dipilih | Senin–Sabtu |
| Hari yang di-disable | Minggu + hari besar/libur nasional |

**Cara kerja kalender cuti:**
1. Karyawan buka form cuti pada suatu tanggal (mis. 18 Agu 2026).
2. Kalender hanya membuka tanggal mulai **H+7** ke depan — tanggal lebih awal
   tidak bisa dipilih (mencegah cuti mendadak secara otomatis).
3. Hari **Minggu & hari besar** otomatis tidak bisa dipilih.
4. Karyawan bisa memilih **maksimal 3 hari kerja** dalam satu pengajuan.
5. Sistem menampilkan sisa saldo cuti dan mengurangi bila pengajuan disetujui.
6. Kalau butuh lebih dari 3 hari, buat pengajuan terpisah lagi (selama saldo cukup).

**Hubungan dengan upah (no present no pay):**
- **Cuti** disetujui → hari itu **dibayar** (potong saldo cuti).
- **Sakit** + surat dokter valid → **dibayar** (tidak potong cuti).
- **Izin** → **tidak dibayar** (no present no pay).
- **Cuti khusus** (menikah, melahirkan, dst) → **dibayar**, tidak potong saldo cuti tahunan.

---

## Ringkasan alur persetujuan (semua pengajuan)

```
Karyawan (portal pribadi)  →  Kirim pengajuan  →  Status: Menunggu
                                                        │
                                              HR dinotifikasi
                                                        │
                              HR tinjau di panel HR Admin
                                                        │
                              ┌─────────────┴─────────────┐
                          Setujui                       Tolak
                              │                             │
              Data/dokumen diperbarui             Status: Ditolak
              + log perubahan tercatat          (karyawan lihat alasan)
```

**Tiga hal yang tidak boleh dilanggar:**
1. Portal hanya untuk pemilik akun sendiri.
2. Tidak ada perubahan data yang berlaku tanpa approval HR.
3. Setiap pengajuan memicu notifikasi ke HR & tercatat jejaknya (audit).
