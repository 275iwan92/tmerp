# Tier 4: Master Employee + Self-Service HRM (ringkas, spec UI v1)

> Diringkas 2026-10-14 dari 257 baris (keputusan Jo 13-Okt: ringkas). Salinan penuh dengan wireframe: `tmerp_unused/docs/md_for_systems/masteremployee_tier4.md`. Yang dibuang: wireframe ASCII dan tata letak v1. Yang dipertahankan: aturan perilaku. Layar v2: profil pegawai = objek DevLib; aturan bisnis lengkap di `masteremployee_tier1.md` dan `attendancepayroll_tier1-3.md`.

## HR Admin
- **Detail pegawai** 8 subtab: Profil, Kontrak, Keluarga, BPJS, Dokumen, Keterampilan, Rekening, Akun. **Tab Akun hanya untuk Admin App** (tidak dirender untuk HR Officer, bukan disabled).
- Baris status kepegawaian dinamis: TETAP; KONTRAK / HARIAN / BORONGAN = tipe + tanggal berakhir; PROBATION = `s/d probation_end_date (dlm kontrak s/d end_date)`.
- **Gaji termasking** untuk HR Officer bila tipe TETAP atau `org_position.salary_hidden_from_hr = 1` (tampil "Rahasia"); Admin App melihat apa adanya. (Pembatasan payroll terkini: rulebook A.25.)
- HR boleh mengubah langsung profil, keluarga, dokumen (tanpa approval); sisi karyawan harus lewat pengajuan.
- Layar Dokumen Kedaluwarsa: sudah lewat, ≤30 hari, menunggu review perpanjangan; KTP WNI seumur hidup (Tier 1 §9).
- Approval Sakit / Izin / Cuti dan Koreksi Data: Setujui / Tolak per baris PENDING. Sakit: indikator dokumen lengkap + tombol "Konversi ke Izin" bila dokumen tidak lengkap / telat (SOP C.4). Izin: tampilkan `izin_category` + dokumen pendukung.
- **Izin Keluar = dual approval:** `[Setujui sbg Atasan]` dan `[Setujui sbg HRD]` terpisah; APPROVED hanya bila keduanya (`emp_out_permit_request`).
- Kedisiplinan (`emp_disciplinary`): Teguran Verbal / SP1-3 / Alpha; baris hasil sistem (`related_leave_request_id` terisi) bertanda tautan dan tidak bisa dihapus.

## Master Produksi dan Roster
- Master Mesin dan Master Shift: edit lewat modal (identitas, aset, spesifikasi; shift = tabel 7 hari × Masuk / Ist.Mulai / Ist.Selesai / Pulang).
- Shift Roster board PPIC (Offset): baris shift dari `emp_shift` × `emp_shift_detail` (nama + jam, bukan "Shift 1/2/3"); drag karyawan ke sel; konflik HARD = merah + blok, SOFT = oranye boleh; lembur "(OT)"; Save / Confirm / Unlock / Print. Grid HR sederhana untuk Digital Printing / Indostry: klik sel siklus 0 → S1 → S2 → S3.

## Portal karyawan (self-service)
- **Data Saya**: tiap field `[Ajukan Koreksi]` (tgl lahir wajib approval HR); alamat KTP dan domisili terpisah; BPJS hanya nomor; pengunduran diri.
- **Keluarga & Status**: ajukan kelahiran, tandai meninggal, status nikah, rekening, kontak darurat.
- **Dokumen & Pendidikan**: unggah dokumen (🔒 bila diunggah HR), ajukan perpanjangan; riwayat pendidikan dengan badge Perusahaan / Pribadi + Ikatan Dinas.
- **Cuti / Sakit / Izin (SOP 002/RP/TM/VII/2026):** Cuti H+7, maks 3 hari, Surat Cuti wajib, kalender menonaktifkan sebelum H+7 dan hari libur. **Izin dinonaktifkan selama saldo cuti > 0**; Izin: terjadwal (H-7) atau mendadak (Musibah / Instansi Resmi / Kepengurusan Musibah), maks 3 hari berturut dan 3×/bulan, dokumen H+1. Sakit: 2 dokumen (SKD + kwitansi / resep) atau 1 screenshot JKN; jam lapor > 07:30 diberi peringatan; wajib SKD bila sakit > 1 hari atau menyambung hari libur. Cuti Khusus: jenis peristiwa + lampiran.
- **Tukar Shift**: deadline **H-1** (bukan H+7), peringatan non-blocking bila telat; status Menunggu Rekan → Menunggu PPIC.
- **Izin Keluar**: tanggal, jam keluar / kembali, keperluan; status Atasan dan HRD terpisah; fingerprint dicatat mesin, bukan aplikasi.
- **Jadwal Saya**: hanya batch CONFIRMED. **Kartu Pengenal**: foto 4×6 cm / 600×900 px / rasio 2:3, validasi dimensi di sisi klien.

## Binding utama (field → tabel)
`emp_employee` + `org_division` / `org_position` (list, profil); `emp_contract` (status, gaji, probation); `emp_bpjs_kesehatan` / `emp_bpjs_ketenagakerjaan`; `emp_document` (kedaluwarsa); `emp_correction_request`, `emp_leave_request` (`izin_category`, `attachment_*`, `sakit_lapor_jam`, `converted_to_izin`), `emp_out_permit_request`, `emp_shift_swap_request` (`submitted_on_time`), `emp_family_request`, `emp_badge_photo_request`, `emp_resign_request`; `emp_leave_balance` (saldo per tahun); `emp_shift_roster` + `emp_shift_roster_batch`; `emp_disciplinary`; akun = `tm2sys.sec_user` (relasi logis, bukan FK).
