---
name: screen_hrm_job_description_and_sop
description: Deskripsi Kerja (per posisi) + register SOP -- input HR Officer, read-only di profil Self-Service tiap pegawai.
---

# Deskripsi Kerja & SOP

**Keputusan user 2026-09-13**: "tambahkan job description module dimana di
setiap user terdapat job description yang muncul di user profile dalam
bentuk readonly. Yang input adalah HR Officer. Begitu pula dengan SOP."

**File**:
- `resources/views/pages/hrm/position.blade.php` — field Deskripsi Kerja ditambahkan ke form Master Posisi yang sudah ada.
- `resources/views/pages/hrm/sop-documents.blade.php` (BARU) — register dokumen SOP.
- `resources/views/pages/self-service/index.blade.php` — 2 tab baru: "Deskripsi Kerja" & "SOP" (read-only).

**Route**: `hrm.position` (sudah ada), `hrm.sop-documents` (`/hrm/sop`, BARU).
**Koneksi DB**: mst (tm2mst) untuk data, sys (tm2sys) untuk grup `HR_OFFICER`.

## Keputusan desain kunci

1. **Deskripsi Kerja melekat ke POSISI (`org_position`), bukan per pegawai.**
   Dalam praktik HR, deskripsi kerja adalah atribut jabatan yang dipakai
   bersama oleh semua pemegangnya, bukan ditulis ulang per orang. Pegawai
   mewarisi lewat `employee.position_id` yang sudah ada — tidak ada tabel
   baru untuk ini, cukup 3 kolom baru di `org_position`
   (`job_description`, `job_description_updated_by`, `job_description_updated_at`).
2. **SOP adalah register dokumen baru** (`sop_document`, tm2mst, prefix `sop_`
   — domain baru, belum ada di CLAUDE.md §3 sebelum sesi ini): kode dokumen,
   judul, revisi, tanggal berlaku, cakupan departemen (`department_id` NULL =
   semua departemen), tujuan, isi. **Versi pertama, BELUM** memecah isi jadi
   langkah-kerja terstruktur seperti `sop_workflow_steps` di materi mentah
   `docs/sop_module_blueprint_reference.md` — itu menunggu arahan lanjutan,
   lihat catatan di file itu.
3. **Role gate baru: `HR_OFFICER`** (`sec_group`, pola sama `QA`/`PROD_MGR`/
   `OPS_MGR`) + `SecUser::isHrOfficer()`. **Belum ada satu pun akun yang
   dipasangkan ke grup ini** — posisi "Personalia" (org_position id 89) ada
   di master tapi tak ada pegawai/akun yang menjabatnya (dicek live
   2026-09-13). Assign manual lewat layar Security setelah user menentukan
   siapa HR Officer sesungguhnya. `is_admin` bypass total (pola `hasGroup()`
   yang sudah ada) — dipakai sementara utk input sebelum akun HR Officer
   sungguhan di-assign.
4. **Guard SELALU server-side, bukan cuma sembunyikan tombol/field di Blade**
   — `save()`/`toggleActive()` di kedua layar mengecek `isHrOfficer()` di awal
   method dan diam-diam tidak melakukan apa-apa kalau bukan HR Officer
   (bukan exception/error, karena field-nya memang tidak pernah dirender ke
   non-HR-Officer — tidak ada UI yang perlu diberi pesan error).

## Master Posisi (`hrm/position.blade.php`) — field baru
- `job_description` (textarea, autogrow) — **HANYA dirender di form kalau
  `Auth::user()->isHrOfficer()`** (bukan disabled, benar-benar tidak ada di
  DOM untuk user lain). 8 field lain di form yang sama tetap bisa diedit
  siapa pun yang punya akses layar HRM ini — gate-nya cuma di field ini.
- `save()`: payload `job_description*` cuma dimasukkan kalau `isHrOfficer()`.

## SOP (`hrm/sop-documents.blade.php`) — layar baru, 1 layar 2 peran
- **HR Officer**: grid biasa + tombol "+ New Record", klik 2x → form edit
  penuh (kode, judul, revisi, tanggal berlaku, cakupan departemen, tujuan,
  isi, aktif/nonaktif), action-icon Edit + toggle aktif.
- **Selain HR Officer**: grid yang SAMA tapi tanpa tombol New Record & tanpa
  kolom Aksi, banner info "Mode lihat saja". Klik 2x → `panel = 'view'`
  (bukan `'form'`) — halaman baca murni (tujuan + isi + metadata), tombol
  cuma "← Kembali". `open()` menentukan panel berdasar `isHrOfficer()`.
- `department_id` NULL pada baris = "Semua Departemen" (ditampilkan begitu
  di grid & form, bukan blank).

## Self-Service — 2 tab baru (read-only, keduanya di `self-service/index.blade.php`)
- **`jobdesc` "📋 Deskripsi Kerja"** — computed `myJobDescription()`
  (`$emp->position->job_description`), ditampilkan `whitespace-pre-line`
  (baris baru dipertahankan). Placeholder kalau kosong: "Belum ada deskripsi
  kerja untuk posisi ini. Hubungi HR Officer bila diperlukan." Catatan kecil
  di bawah: "Hanya HR Officer yang dapat mengubah deskripsi ini."
- **`sop` "📘 SOP"** — computed `mySopDocuments()`: SOP aktif dengan
  `department_id IS NULL` **ATAU** `department_id = $emp->position->department_id`
  (union, bukan salah satu) — pegawai selalu lihat SOP global + SOP
  departemennya sendiri, tidak pernah SOP departemen lain. Daftar berupa
  tombol per dokumen ("📘 judul · kode · Rev · cakupan · Baca →"), klik
  → `openSop($id)` set `$viewingSopId`, isi lengkap tampil di modal
  (tujuan + isi + tanggal berlaku), `closeSop()` menutup.

## Pengujian
23 skenario via `Livewire::test` (bukan browser — login browser dilarang
aturan sesi): field job description tersembunyi & save ditolak untuk
non-HR-Officer, muncul & tersimpan untuk HR Officer, tampil read-only di
profil pemegang posisi; grid SOP mode-lihat-saja vs mode-edit, openNew/
toggleActive dipaksa non-HR-Officer ditolak diam-diam, HR Officer bisa
CRUD penuh, non-HR-Officer buka dokumen → mode view tanpa tombol Simpan;
tab SOP Self-Service menampilkan gabungan SOP global + SOP departemen
sendiri dan TIDAK menampilkan SOP departemen lain, modal baca berfungsi.
Semua data uji dibersihkan & diverifikasi ulang langsung ke DB.

## Belum / keterbatasan
- Belum ada satu akun pun berperan HR Officer — perlu di-assign manual.
- SOP masih flat (1 blok teks isi), belum ada versi/riwayat revisi, belum
  ada lampiran file, belum dipecah jadi langkah kerja terstruktur (lihat
  `docs/sop_module_blueprint_reference.md` untuk materi mentah yang lebih
  detail — workflow steps, parameter operasional, aturan QC, dst — yang
  belum diproses ke skema ini, sengaja menunggu arahan lanjutan user).
- Verifikasi visual browser belum dilakukan.
