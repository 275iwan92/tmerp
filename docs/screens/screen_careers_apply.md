---
name: screen_careers_apply
description: Form "Data Calon Karyawan" (lamaran) 1-halaman, isian lengkap kandidat yang sudah login untuk melamar 1 vacancy tertentu.
---

# Lamar Posisi (Form Data Calon Karyawan)

**File**: `resources/views/pages/careers/apply.blade.php`
**Route**: `careers.apply` (GET `/hrm/recruitment/portal/lamar/{vacancy_code}`, DI DALAM `Route::middleware('portal.candidate')` -- wajib sudah login sebagai kandidat)
**Koneksi DB utama**: Firebase/Firestore (via `App\Services\Recruitment\FirebaseSyncAdapter` + `App\Services\Portal\CandidateAuthService`)
**Modul**: Recruitment Portal (Publik)

## Tujuan
Form lamaran lengkap ("Data Calon Karyawan", turunan dokumen manual LJM/HRD/P01/F02) untuk 1 vacancy yang sudah dipilih lewat route `{vacancy_code}` (bukan step terakhir seperti wizard lama). Berbeda dari `resources/views/pages/recruitment/portal.blade.php` (simulasi internal, KTP-only tanpa akun, sudah tidak punya route aktif -- lihat `docs/flows/flow_recruitment_portal_public.md`): di sini kandidat wajib login akun real, dan identitas (nama/KTP/email) dikunci dari data akun terverifikasi -- tidak bisa diketik ulang bebas. Sejak revisi 2026-08-30, seluruh 11 seksi form ditampilkan sekaligus 1 halaman tabular (bukan wizard step-per-step), dan SEMUA field wajib diisi tanpa terkecuali (termasuk baris Nama Ayah & Ibu yang terkunci). Submit langsung ke Firestore via `FirebaseSyncAdapter` (hardcode, bukan lewat `PortalBackendAdapterInterface` yang switchable -- sengaja berbeda dari simulasi lama).

## Field & Kolom
Markup form sesungguhnya ada di partial `_apply-form-steps.blade.php` (lihat bagian di bawah); daftar berikut adalah public property Livewire yang dijadikan `wire:model` di partial itu.

- `vacancy_code` (string) -- dari parameter route, di-set di `mount()`.
- `submitted` (bool) -- toggle tampilan form vs halaman sukses.
- **Data Pribadi**: `name`, `nickname`, `national_id` (readonly, dikunci dari akun), `gender` (`LAKI_LAKI`/`PEREMPUAN`), `religion`, `email` (readonly, dikunci dari akun), `phone`, `birth_date`, `address`, `address_domisili`, `vehicle_type` (`MOTOR`/`MOBIL`/`TIDAK_ADA`), `sim_types` (array checkbox: `C`/`A`/`B`/`B1`).
- **Data Keluarga**: `marital_status` (`LAJANG`/`MENIKAH`/`DUDA`/`JANDA`), `marital_date`, `family` (array baris; index 0 = Ayah, index 1 = Ibu -- selalu ada & terkunci `relation_type`, baris lain bebas ditambah dgn `relation_type`: `SPOUSE`/`CHILD`/`SIBLING`/`FATHER_IN_LAW`/`MOTHER_IN_LAW`; kolom per baris: `name`, `gender`, `age`, `last_education`, `current_job`, `current_employer`).
- **Riwayat Pendidikan**: `education` (array; `level`, `institution`, `major`, `period_start_year`, `graduation_year`, `final_score`), `educationNonformal` (array; `course_type`, `institution`, `period_start_year`, `period_end_year`, `certificate_no`).
- **Pengalaman Organisasi**: `orgExperience` (array; `organization_name`, `position`, `period`, `task_description`).
- **Pengalaman Kerja**: `workExp` (array kartu, bukan tabel; kolom: `company`, `location`, `business_type`, `employee_count`, `company_phone`, `position`, `start_date`, `end_date`, `position_end`, `position_end_start_date`, `position_end_end_date`, `subordinate_count_start`, `subordinate_count_end`, `salary_start`, `salary_end`, `benefits`, `supervisor_name`, `supervisor_phone`, `achievement`, `description`, `org_structure_note`, `key_challenge`, `improvement_made`, `leave_reason`).
- **Sosial & Referensi**: `company_acquaintance`, `hobby`, `leisure_activity`, `reading_topics`, `went_abroad`, `professionalReferences` (array min 2; `name`, `phone`), `emergencyContacts` (array min 3; `name`, `relation`, `phone`, `address`).
- **Minat & Konsep Pribadi**: `why_join_us`, `what_you_know_about_us`, `contribution_plan`, `role_plan_if_hired`, `job_type_preference`, `job_nature_preference`, `work_environment_preference`, `hindering_environment`, `preferred_colleague_type`, `difficult_decision_topic`, `strengths_weaknesses`, `who_you_ask_when_stuck`.
- **Riwayat Kesehatan**: `height_cm` (int), `weight_kg` (int), `blood_type`, `illness_history`, `hospitalized_history`, `is_smoker` (bool, select `1`=Ya/`0`=Tidak), `physical_disability`, `family_health_ok` (bool, select serupa).
- **Bahasa, Komputer & Info Lowongan**: `languageSkills` (array min 1; `language`, `written` [`S`/`CB`/`B`], `oral` [`S`/`CB`/`B`], `notes`), `computerSkills` (array min 1; `skill`, `level` [`S`/`CB`/`B`], `notes`), `still_receiving_aid`, `other_dependents`, `source_of_info`, `expected_salary` (float), `expected_facility`, `availability_date`.
- **Kesediaan & Pernyataan**: `willingness` (array asosiatif key => bool, key dari konstanta `WILLING_ITEMS` -- lihat di bawah), `agreement_signed` (bool), `signature_name`.
- **Upload Dokumen**: `cvFile`, `ijazahFile` (keduanya `WithFileUploads`, wajib).
- `lastSubmissionUuid` (nullable string) -- diisi setelah submit sukses, ditampilkan di halaman "Lamaran Terkirim" sbg nomor referensi.

**Konstanta `WILLING_ITEMS`** (8 item, key => label Indonesia): `probation`, `training`, `overtime`, `guarantee_docs`, `resign_notice`, `obey_rules`, `resign_if_married_pregnant`, `relocation` -- semua wajib dicentang, tak ada pengecualian.

**Sumber dropdown**: semua opsi (`gender`, `vehicle_type`, `marital_status`, `sim_types`, `relation_type`, `written`/`oral`/`level` bahasa-komputer, `is_smoker`, `family_health_ok`) hardcode array literal di partial `_apply-form-steps.blade.php`, bukan dari tabel master/lookup.

## Aksi / Method
- `mount(string $vacancy_code, CandidateAuthService $auth)`: set `vacancy_code`, init `willingness` (semua `false`), init `family` (2 baris terkunci Ayah/Ibu), prefill identitas (`name`/`national_id`/`email`/`phone`) dari `CandidateAuthService::findByUid(session('portal_candidate_uid'))`, lalu prefill SISA field dari submission terakhir kandidat (by `national_id`) via `FirebaseSyncAdapter::findLatestByNationalId()` -- kalau pernah apply sebelumnya, semua field lain (termasuk array berulang) di-copy dari submission lama; baris Ayah/Ibu di-`array_merge` ke index 0/1 yang sudah ada (bukan replace array), anggota keluarga lain di-append.
- `vacancy()` `#[Computed]`: `VacVacancy::where('vacancy_code', $this->vacancy_code)->where('is_published', true)->firstOrFail()`.
- `willingItems()` `#[Computed]`: return konstanta `WILLING_ITEMS`.
- `emailVerified()` `#[Computed]`: cek `CandidateAuthService::findByUid(...)['email_verified_at']` tidak kosong.
- `addRow(string $prop, array $template)`: push baris baru ke array property (dipakai semua tombol "+ Tambah ...").
- `removeRow(string $prop, int $i)`: hapus baris ke-`$i` dari array property; guard eksplisit -- kalau `$prop === 'family'` dan `$i` di `[0, 1]` (baris Ayah/Ibu), tidak dihapus (tombol hapus juga memang tidak dirender untuk 2 baris itu di partial, guard ini jaga-jaga).
- `submitApplication()`: method utama submit.
  1. Guard: kalau `emailVerified` false, `addError('apply', ...)` dan berhenti -- form tetap bisa diisi tapi tidak bisa dikirim sebelum email diverifikasi.
  2. Validasi lengkap 1x lewat `Validator::make($this->all(), $rules, [], $attributes)` -- SEMUA field wajib (`required`), termasuk array minimal (`education` min:1, `educationNonformal` min:1, `orgExperience` min:1, `workExp` min:1, `professionalReferences` min:2, `emergencyContacts` min:3, `languageSkills` min:1, `computerSkills` min:1), plus `family.0.name`/`family.1.name` (Nama Ayah/Ibu) wajib, `marital_date` required-if `marital_status` bukan `LAJANG`/kosong, `agreement_signed` harus `accepted`, `cvFile`/`ijazahFile` wajib.
  3. `$validator->after()` tambahan: cek semua 8 item `willingness` sudah `true` -- kalau ada yang `false` atau jumlah key kurang dari `WILLING_ITEMS`, tambah error custom `willingness`.
  4. Kalau gagal, `$validator->validate()` (re-throw `ValidationException`, ditangkap otomatis oleh Livewire ke `$errors` bag komponen -- semua pesan tampil sekaligus dalam 1 alert banner, bukan satu-satu).
  5. Kalau lolos, susun `$payload` (field kosong string `''` dinormalisasi jadi `null` untuk baris array via closure `$clean`), lalu panggil `FirebaseSyncAdapter::saveSubmission($payload, ['cv' => $cvFile, 'ijazah' => $ijazahFile])` -- return UUID submission.
  6. Reset `cvFile`/`ijazahFile` ke `null`, set `submitted = true` (tampilkan halaman sukses).

## Tabel & Model / Firestore Collection yang Disentuh
- **Firestore collection `candidates`** -- baca saja (`getDocument` via `CandidateAuthService::findByUid()`), untuk prefill identitas & cek status verifikasi email. Tidak ditulis dari layar ini.
- **Firestore collection `submissions`** -- baca (`findLatestByNationalId()`, filter semua dokumen by `national_id` di PHP lalu ambil terbaru) untuk prefill, dan tulis (`setDocument`, dokumen baru ber-UUID) saat submit via `saveSubmission()`. Dokumen yang ditulis membawa seluruh `$payload` + `documents` (array metadata file upload: `doc_type`, `file_path`, `uploaded_at`) + `submitted_at`, `sync_status: 'pending'`, `synced_at: null`, `candidate_uid`.
- **MySQL `tm2hrr` (connection `tm2hrr`), tabel `vac_vacancy`** -- `App\Models\Hrr\VacVacancy` (PK `id_vacancy`) dibaca (`VacVacancy::where('vacancy_code', ...)->where('is_published', true)->firstOrFail()`) untuk judul vacancy & validasi vacancy masih published. Ini satu-satunya akses MySQL langsung dari layar ini (pengecualian terverifikasi dari template "Koneksi DB utama: Firebase" di atas).
- File upload (`cvFile`, `ijazahFile`) disimpan ke disk lokal `public`, path `recruitment/portal-documents/...` (via `$file->store(...)` di `FirebaseSyncAdapter::saveSubmission()`) -- path-nya (bukan isi file) yang dicatat ke dokumen Firestore.
- Tidak ada tulis langsung ke MySQL `tm2hrr` dari layar ini -- data baru masuk ke `tm2hrr` lewat worker `PortalSyncService` terpisah (lihat `docs/flows/flow_recruitment_portal_public.md`).

## Alur / Business Logic Penting
- **Prasyarat login + email verified**: middleware `portal.candidate` menjamin sudah login; guard tambahan di `submitApplication()` khusus cek email terverifikasi (bisa isi form tanpa verifikasi, tapi tidak bisa kirim).
- **Identitas terkunci**: `national_id` dan `email` dirender `readonly` di partial (kelas `bg-[#f8fafc]`), diisi dari data akun -- bukan dari input bebas kandidat, mencegah mismatch dengan data registrasi.
- **Prefill dari histori**: kalau kandidat pernah submit lamaran lain sebelumnya (`national_id` sama), semua isian non-identitas otomatis terisi ulang dari submission terakhir -- mengurangi isi ulang untuk kandidat yang melamar posisi berbeda.
- **Baris Ayah/Ibu terkunci**: struktur `family` dijamin index 0=FATHER, index 1=MOTHER lewat kode (bukan validasi array bebas dicari) -- tombol hapus tidak dirender & guard `removeRow()` menahan penghapusan kalau tetap dipanggil.
- **1 alert validasi gabungan**: pendekatan `Validator::make()->after()` sengaja dipilih supaya kandidat lihat SEMUA field kurang lengkap sekaligus, bukan ketemu satu error per klik ulang (revisi 2026-08-30 sesuai instruksi user).
- **Submit langsung ke Firestore**, TIDAK langsung menulis ke `tm2hrr` -- worker terpisah `recruitment:sync-portal` (jadwal `everyTwoMinutes()`, `routes/console.php`) yang menariknya secara idempoten (dedupe via `submission_uuid` + `RcrSyncLog`).
- Setelah sukses: `submitted = true` menyembunyikan form dan menampilkan halaman konfirmasi dengan nomor referensi (`lastSubmissionUuid`), tombol ke Dashboard (`careers.dashboard`) atau kembali ke daftar lowongan (`careers.landing`).

## Catatan
- Tidak ada TODO/FIXME eksplisit tertulis di kode `apply.blade.php`. Komentar kode di `mount()` dan `submitApplication()` menegaskan filosofi desain (semua wajib, 1 alert gabungan) tapi tidak menandai gap fungsional yang belum selesai.

## Partial: _apply-form-steps.blade.php
**File**: `resources/views/pages/careers/_apply-form-steps.blade.php`
**Bukan route sendiri** -- di-`@include('pages::careers._apply-form-steps')` langsung dari `apply.blade.php` (baris ~413), memakai scope property Livewire dari komponen induk (harus persis nama property yang sama: `name`, `nickname`, `family`, `education`, dst -- lihat daftar Field & Kolom di atas).

**Tujuan**: markup murni ("file ini murni markup", komentar kode) -- berisi seluruh 11 seksi form Data Calon Karyawan (turunan dokumen manual LJM/HRD/P01/F02), tanpa logic Livewire sendiri (tidak ada `<?php class ... @>` block, murni Blade view). Validasi sepenuhnya ada di `submitApplication()` milik komponen induk.

**11 seksi (masing-masing `<section id="sec-...">`)**:
1. **Data Pribadi** (`sec-pribadi`) -- No. KTP (readonly), Nama Lengkap, Nama Panggilan, Jenis Kelamin, Agama, Tanggal Lahir, Email (readonly), No. HP, Alamat KTP, Alamat Domisili, Kendaraan, SIM (checkbox multi).
2. **Data Keluarga** (`sec-keluarga`) -- Status Pernikahan, Tanggal Menikah/Cerai, tabel Susunan Keluarga (baris Ayah/Ibu highlight kuning `bg-[#fffaf0]` & terkunci, baris lain bisa ditambah/dihapus via `addRow`/`removeRow`).
3. **Riwayat Pendidikan** (`sec-pendidikan`) -- 2 tabel terpisah: Pendidikan Formal (min 1) dan Pendidikan Non-Formal/Kursus (min 1, boleh isi "Tidak ada").
4. **Pengalaman Organisasi** (`sec-organisasi`) -- 1 tabel, min 1 baris (boleh isi "Tidak ada").
5. **Pengalaman Kerja** (`sec-kerja`) -- SATU-SATUNYA seksi berulang berbentuk kartu (bukan tabel) "karena kolom terlalu banyak per baris untuk tabel wajar" (komentar kode) -- tiap kartu punya sub-bagian Jabatan Awal & Jabatan Terakhir dengan tanggal masing-masing, plus gaji, atasan, prestasi, uraian pekerjaan, tantangan, perubahan yang dilakukan, alasan keluar.
6. **Aktivitas Sosial, Kegiatan & Referensi** (`sec-sosial`) -- pertanyaan bebas + tabel Referensi (min 2 orang, bukan keluarga) + tabel Kontak Darurat (min 3 orang).
7. **Minat & Konsep Pribadi** (`sec-minat`) -- 12 pertanyaan esai bebas (textarea) tentang motivasi, kelebihan/kekurangan, cara mengambil keputusan, dll.
8. **Riwayat Kesehatan** (`sec-kesehatan`) -- tinggi/berat/gol. darah, riwayat penyakit & rawat inap, status merokok, kesehatan keluarga, gangguan jasmani.
9. **Bahasa, Komputer & Info Lowongan** (`sec-bahasa`) -- tabel Penguasaan Bahasa Asing (min 1), tabel Penguasaan Komputer (min 1), bantuan yang diterima, tanggungan lain, sumber info lowongan, gaji diinginkan, tanggal siap kerja, fasilitas diinginkan.
10. **Kesediaan & Pernyataan** (`sec-kesediaan`) -- checklist 8 item `WILLING_ITEMS` (semua wajib dicentang), teks pernyataan baku (kutipan pernyataan kesediaan berhenti tanpa pesangon bila data tidak sesuai), checkbox persetujuan + kolom "tanda tangan" berupa ketik nama lengkap (`signature_name`).
11. **Upload Dokumen** (`sec-dokumen`) -- input file CV dan Ijazah (`wire:model`, keduanya wajib), menampilkan nama file yang sudah dipilih dengan centang hijau.

**Pola UI berulang**: setiap seksi array (Keluarga/Pendidikan/Organisasi/Referensi/Kontak Darurat/Bahasa/Komputer) memakai `<table>` literal HTML ("model tabular yang jelas" -- instruksi user 2026-08-30) dengan tombol "+ Tambah ..." di bawah tabel yang memanggil `addRow(prop, template_json)`, dan tombol hapus `✕` per baris (kecuali baris Ayah/Ibu terkunci) yang memanggil `removeRow(prop, index)`. `wire:key` dipasang per baris (`fam-{i}`, `edu-{i}`, `nonformal-{i}`, `org-{i}`, `work-{i}`, `ref-{i}`, `emg-{i}`, `lang-{i}`, `comp-{i}`) untuk stabilitas render Livewire saat baris ditambah/dihapus.
