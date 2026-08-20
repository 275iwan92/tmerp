# TIER 4 — PRESENSI & PAYROLL · UI/UX & INTERACTION DESIGN
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## 1. PETA LAYAR

| # | Layar | Aktor | Livewire component (usulan) |
|---|---|---|---|
| L1 | Import Data Finger | HR Admin | `AttFingerImport` |
| L2 | Grid Presensi Harian (review + manual override) | HR Admin | `AttDailyGrid` |
| L3 | Pengajuan Izin/Cuti/Sakit (self-service) | Karyawan | `LeaveRequestForm` |
| L4 | Approval Izin/Cuti/Sakit | Atasan Langsung | `LeaveApprovalQueue` |
| L5 | Pengajuan & Approval Lembur | Karyawan/SPV | `OvertimeRequestForm` |
| L6 | Status Disiplin (SP/Skorsing/DRM) | HR Admin | `DisciplinaryStatusPanel` |
| L7 | Payroll Run | Payroll Officer | `PayrollRunPanel` |
| L8 | Payslip (detail & cetak) | Payroll Officer, Karyawan (self-service view) | `PayslipDetail` |

---

## 2. L1 — Import Data Finger

- Upload single file (drag-drop + browse), preview jumlah baris terbaca sebelum commit.
- Setelah commit → trigger Job Kalkulasi Harian (async, tampilkan progress/status `PENDING → PROCESSED`).
- Jika `FAILED` → tampilkan `error_note` apa adanya (baris keberapa gagal parse), bukan generic error.
- Riwayat import (tabel `att_finger_import`) ditampilkan sebagai log, bisa re-trigger kalkulasi tanpa upload ulang jika job gagal di tengah jalan.

## 3. L2 — Grid Presensi Harian

- Filter: rentang tanggal, departemen/divisi (nullable per memori arsitektur), status code.
- Tiap baris: pegawai, tanggal, `status_code` (badge warna per kategori — hijau=H, kuning=T-5/PA, oranye=T+5/PAT-5, merah=T+15/A/PAT+5), finger_in/out, menit hilang.
- **Manual override**: HR Admin bisa ubah `status_code` satu baris dengan wajib isi `override_reason` (field ini bukan opsional — audit trail). `source` berubah jadi `MANUAL_OVERRIDE`, tersimpan siapa yang override.
- Baris tanpa data finger & tanpa leave/disciplinary → highlight khusus (butuh keputusan HR: benar-benar Alpha, atau lupa input izin).
- **Tidak ada** field "convert jam manual" di layar ini — itu sengaja dihilangkan sesuai Tier 1 §3.2.

## 4. L3 — Pengajuan Izin/Cuti/Sakit (self-service karyawan)

- Pilih jenis (I/IS/C/CL/S) → form field menyesuaikan:
  - I: rentang tanggal + alasan singkat, submit langsung (peringatan UI: "diajukan mendadak, dampak ke Bonus Pagi/Hadir hilang & denda Rp 25.000" — transparansi ke karyawan sebelum submit).
  - IS: sama, tapi validasi `date_start >= today + 3 hari` — jika kurang dari itu, tampilkan pesan otomatis alihkan ke jenis "I" (bukan block keras, karena bisa saja tetap mau diajukan sebagai I).
  - C: validasi `date_start >= today + 7 hari`, sama logic dengan IS.
  - CL: pilih skema A/B (radio dengan penjelasan konsekuensi pembayaran per skema), wajib upload lampiran.
  - S: wajib upload surat dokter + kwitansi/surat obat sebelum submit diaktifkan (tombol submit disabled sampai file terupload).
- Status pengajuan (DRAFT/SUBMITTED/APPROVED/REJECTED) terlihat di riwayat karyawan sendiri.

## 5. L4 — Approval (Atasan Langsung)

- Queue per atasan (hanya bawahan langsung), sorted by tanggal pengajuan terlama dulu.
- Klik → detail pengajuan + lampiran (preview inline untuk PDF/gambar) → Approve/Reject (reject wajib isi alasan).
- Approve otomatis trigger status ke `att_daily_status` untuk rentang tanggal terkait (tidak menunggu job harian berikutnya).

## 6. L5 — Lembur

- Form pengajuan: tanggal, tipe (Awal/Akhir/Keduanya), durasi (input manual jam:menit, BUKAN dihitung otomatis dari finger — sesuai Tier 2 §4.4 durasi dari rekap/pengajuan, bukan re-derive).
- Approval oleh atasan sama pola dengan L4.
- Badge "Uang Roti" muncul otomatis di preview jika durasi >2 jam DAN role pegawai = Staff/SPV Produksi (read-only info, bukan input manual).

## 7. L6 — Status Disiplin

- List SP/Skorsing/DRM aktif & histori per pegawai.
- Tambah baru: pilih jenis, `effective_start`, catatan. `working_days_target` terisi otomatis (3/7) berdasar jenis, read-only.
- Counter `working_days_counted` read-only, terupdate otomatis oleh job harian — tampilkan progress bar "3 dari 3 hari" biar HR tahu kapan efek SP berakhir.

## 8. L7 — Payroll Run

- Pilih periode (bulan/tahun) → tombol "Hitung" → trigger job async, progress bar per-pegawai selesai diproses.
- Setelah `CALCULATED`: tabel ringkas semua pegawai (nama, total komponen, total bersih), bisa expand ke breakdown per pegawai (link ke L8) sebelum finalize.
- Tombol "Finalize" hanya aktif dari status `REVIEWED`, dengan modal konfirmasi tegas ("Setelah finalize, payroll TIDAK bisa dihitung ulang untuk periode ini") — pola sama dengan Save→Confirm di modul Sales/Shift Roster.
- Filter warning: highlight pegawai dengan status `A` (Alpha) yang belum direview HR di L2, supaya Payroll Officer tidak asal finalize data mentah.

## 9. L8 — Payslip

- Header: nama pegawai, periode, total gaji bersih.
- Breakdown per komponen (POKOK, TJ_KEAHLIAN, dst) dengan angka +/- jelas (potongan berwarna merah/negatif).
- Expand ke breakdown harian (`pyr_payslip_detail`) — transparansi penuh kenapa nominal segini, terutama untuk kasus prorata & denda.
- Karyawan (self-service, akses read-only) hanya lihat payslip milik sendiri, periode yang sudah `FINALIZED`.
- Cetak/PDF — pakai skill `docx`/`pdf` saat implementasi generate file-nya (bukan scope UI Livewire ini).

---

## 10. PRINSIP DESAIN LINTAS LAYAR

- **Tidak ada layar "convert jam manual"** atau field serupa di manapun — dihapus dari desain sesuai keputusan Tier 1.
- Semua badge status pakai warna konsisten (hijau/kuning/oranye/merah) di L2 dan L8, supaya HR dan karyawan baca pola yang sama.
- Manual override (L2) SELALU minta alasan — tidak ada override diam-diam, karena ini titik paling rawan disalahgunakan dari proses lama.
- Approval (L4/L5) push notifikasi ke atasan (pakai modul Notification `tm2sys` yang sudah ada), bukan mengandalkan atasan cek manual.

---

## 11. HANDOFF KE DEVELOPER

- Prasyarat: HRM/Master Employee (`emp_employee`, `emp_shift`) sudah live dengan DDL per-hari (per catatan arsitektur, bukan versi lama satu start/end).
- Jangan implementasi Tier 3 (`pyr_status_rule` seed) sebelum Open Items Tier 1 §4 & Tier 2 dikonfirmasi Telma — kalau dikerjakan sekarang nominalnya berisiko salah dan harus diulang.
- Modul ini **di luar scope aktif 10 minggu** — dokumen ini disimpan sebagai referensi siap-pakai untuk saat giliran HR/Payroll dibuka, bukan untuk dieksekusi Claude Code sekarang kecuali diminta eksplisit.
