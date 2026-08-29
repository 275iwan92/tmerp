# attachments_index.md — Daftar Isi `docs/attachments/`

> Folder tempat menaruh **form manual (kertas/scan) & file Excel yang SAAT INI
> benar-benar dipakai** di lapangan oleh PT. Telasih Makmur — dijadikan acuan
> sebelum membangun/mengubah layar terkait, jangan menebak field. Update
> daftar ini tiap kali ada file baru ditambahkan/dipakai.
>
> **Cara pakai:** minta Claude "baca [nama file] dari attachments" kapan pun
> mau mulai kerjakan modul terkait — tidak semua file perlu dibaca detail
> sekarang, cukup tahu isinya lewat daftar ini dulu.

| File | Isi | Status pakai | Relevan untuk |
|---|---|---|---|
| `STRUKTUR.pdf` | Struktur organisasi riil — 49 posisi + hierarki lengkap (Presiden Direktur s.d. staff/SPV per departemen) | ✅ SUDAH dipakai isi data Struktur Organisasi | HR > Organization > Struktur Organisasi (`hrm.org-chart`) |
| `form portal.pdf` | Formulir resmi "Data Calon Karyawan" (No. Dok LJM/HRD/P01/F02) — data pribadi, keluarga, pendidikan, pengalaman kerja detail, minat & konsep pribadi, kesehatan, kesediaan, contoh terisi (Sufyan Anas) | ✅ SUDAH dipakai bangun form Apply Portal Candidate (12 langkah) | Recruitment > Portal Candidate |
| `SOP PERIZINAN KARYAWAN TELASIH MAKMUR.pdf` | SOP lengkap 5 halaman: definisi Sakit/Izin/Cuti/Alpha/Tukar Shift/Keluar Jam Kerja, ketentuan pengajuan (H-7, H-1, dst), tabel potongan komponen gaji per kondisi kehadiran, sanksi (denda Rp25rb/Rp100rb, SP1-3, PHK) | ⬜ Belum dipakai bangun layar | Penyempurnaan Approval Cuti/Sakit/Izin (`hrm.leave-requests`) & modul Presensi |
| `PRESENSI & PAYROLL - RULES & POLICY.pptx` | 21 slide: rules perhitungan gaji dari status presensi — tiap kasus (T-5/T+5/PA/PAT/Lembur Awal-Akhir/dst) dengan komponen gaji mana yang kena potong, tabel Payroll x Status Presensi lengkap, alur kerja manual Excel existing (Sheet DB Employee → Data Rekap Presensi → Shift Schedule → Payroll Produksi) | ⬜ Belum dipakai bangun layar | **Wajib dibaca sebelum mulai modul Presensi/Payroll** (HR > Time Attendance/Payroll, masih placeholder NOT_BUILT) |
| `1112 GAJI KARYAWAN PERIODE 3 - 9 JAN 2025 WEEKLY 1630.xlsb` | Contoh data payroll mingguan riil (periode 3-9 Jan 2025) | ⬜ Belum dibongkar isinya | Referensi struktur data Payroll saat digarap |
| `1112 GAJI KARYAWAN PERIODE 17 - 23 JAN 2025 WEEKLY 1630.xlsb` | Contoh data payroll mingguan riil (periode 17-23 Jan 2025) | ⬜ Belum dibongkar isinya | Referensi struktur data Payroll saat digarap |
| `01Ringkasan.xls` | Ringkasan (kemungkinan rekap presensi/payroll lintas periode) | ⬜ Belum dibongkar isinya | Referensi struktur data Payroll saat digarap |
| `HR  penerimaan karyawan.jpeg` | Form manual "Form Penerimaan Kandidat" 1 lembar — Identitas Permintaan, Identitas Kandidat, Ringkasan Hasil Seleksi (Screening/Interview HR/Interview User/Psikotest/Interview GM/Offering/PKWT), Summary Psikotest (IQ/DISC/dst), Keputusan User+Direksi, tanda tangan HR/User/BOD | ⬜ Belum disinkronkan | Beda/lebih ringkas dari alur Recruitment L1-L9 yang sudah dibangun — dipakai riil utk rekrutmen SPV/manajerial, perlu dicek apakah mau diselaraskan |
| `TM SPK.jpeg` | "Surat Perintah Kerja" produksi — No CO/SPK/WO, due date, spesifikasi material/ukuran plano, tabel proses (Persiapan Bahan→Potong→Cetak→QC→Laminating→Plong→Kupas→Lem/Stitching→Pack) dengan kolom Jumlah Baik/Waste + TTD operator per proses | ⬜ Belum dipakai bangun layar | Referensi kalau ada penyempurnaan tracking proses WO/Production |
| `form surat ijin TM Offset.jpeg` | "Surat Izin Karyawan" manual (No. SI.TLS) — Sakit dengan surat dokter / Izin + alasan, approval HRD/Kepala Divisi/Yang Bersangkutan | ⬜ Belum disinkronkan penuh | Approval Cuti/Sakit/Izin — cek kesesuaian field dgn layar existing |
| `form ijin terlambat.jpeg` | "Form Datang Terlambat / Keluar Jam Kerja / Pulang Awal" manual (No. FDT.TDP) — checkbox jenis + jam + alasan, approval HRD/Kepala Divisi/Pemohon | ⬜ Belum disinkronkan | Modul Presensi (kasus Terlambat/Keluar Jam Kerja/Pulang Awal, lihat juga pptx Presensi) |
| `Sufyan Anas - SPV Produksi Finishing Percetakan - Glints TapLoker.pdf` | CV/resume asli kandidat, hasil download dari portal Glints/TapLoker (profil, pendidikan, 2 pengalaman kerja: Ryo Digital Printing Desain Grafis→Branch Manager, JJ Photo Studio Fotografer) | ✅ Sudah jadi basis data uji e2e modul Recruitment (nama sama persis dipakai di test) | Referensi sumber lamaran riil (`source_of_info` portal) |
| `Psikotest - Sufyan Anas - SPV Finishing.pdf` | Hasil psikotes MENTAH lengkap (16 hlm): IST (9 subtes, IQ 102), PAPI Kostik, 16PF, Kraeplin (kecepatan/ketelitian/keajegan/ketahanan kerja), DISC (profil "Designer"/"Contemplator"), + dokumentasi foto tiap subtes | ⬜ Belum disinkronkan ke layar | Referensi struktur data utk L6 Input Hasil Psikotes/Interview (`hrm.recruitment.assessment-result`) kalau mau simpan detail per-alat-tes, bukan cuma skor+rekomendasi ringkas spt sekarang |
| `Interpretasi - Sufyan Anas - SPV Finishing.pdf` | Psikogram ringkas + narasi interpretasi hasil psikotes di atas (skala 1-5 per aspek Kecerdasan/Cara Kerja/Potensi Kerja/Human Relation/Kepribadian, skor individu 301, rekomendasi "Dapat Disarankan", kelebihan/perlu ditingkatkan/saran pengembangan) | ⬜ Belum disinkronkan ke layar | Sama seperti di atas — versi "hasil olahan" utk assessor, vs raw score di file Psikotest |
| `Form Penerimaan Kandidat_Sufyan Anas_SPV Finishing.xlsx` | Versi Excel dari form yang sama dgn `HR  penerimaan karyawan.jpeg`, sudah terisi lengkap kasus nyata Sufyan Anas (Screening 100/Interview HR 90/Psikotest 301/Interview GM, catatan wawancara detail, keputusan User "OK" + Direksi "oke") | ⬜ Belum dibongkar isinya (baru terdaftar) | Sama seperti `HR  penerimaan karyawan.jpeg` — kandidat contoh sama yang dipakai isi test e2e Recruitment |
| `DAFTAR KARYAWAN.xlsx` | Daftar karyawan (kemungkinan master/rekap) | ⬜ Belum dibongkar isinya | Referensi struktur data HRM/Payroll |
| `DAFTAR REKENING KARYAWAN (BCA).xlsx` | Daftar rekening bank BCA karyawan utk transfer gaji | ⬜ Belum dibongkar isinya | Referensi field rekening bank kalau modul Payroll butuh data transfer |
| `8. GAJI KARYAWAN PERIODE 22 - 28 AGUSTUS 2026 WEEKLY 1657.xlsb` | Contoh data payroll mingguan riil (periode 22-28 Agu 2026, paling baru) | ⬜ Belum dibongkar isinya | Referensi struktur data Payroll saat digarap |
| `JD & KPI - STAFF FINANCE.pdf` | Job Description (Daily/Weekly/Monthly/Seasonal) + tabel KPI berbobot (Kedisiplinan 20%, Ketepatan Penagihan 30%, Akurasi Transaksi 25%, Ketepatan Laporan 25%) utk posisi Staff Finance (No. Dok 010/XI/TM-HRD/2025) | ⬜ Belum dipakai bangun layar | Kalau modul HR nanti punya fitur JD/KPI per posisi — belum ada layarnya sama sekali saat ini |

## Catatan tambahan

**Sufyan Anas = 1 contoh kandidat riil dgn paper trail lengkap**, tersebar di 5 file: CV (Glints TapLoker) → Form Penerimaan Kandidat (jpeg & xlsx) → Psikotest mentah → Interpretasi. Nama & sebagian data yang sama (posisi SPV Finishing/Produksi, riwayat kerja Ryo Digital Printing) dipakai sebagai data uji end-to-end modul Recruitment sesi 2026-08-29 — kebetulan cocok karena memang ini kandidat riil yang jadi rujukan `form portal.pdf`.

- Isi tabel di atas hasil pembacaan Claude Code 2026-08-29 (PDF/PPTX/JPEG penuh; file Excel `.xlsx`/`.xlsb`/`.xls` baru terdaftar namanya, belum dibongkar datanya — sesuai instruksi user, tidak perlu dibongkar sampai benar-benar mau dipakai).
- Kalau nambah file baru ke `docs/attachments/`, update baris di tabel ini juga (minta "reindex attachment") — jangan biarkan folder ini "diam-diam" beda dari daftar.
