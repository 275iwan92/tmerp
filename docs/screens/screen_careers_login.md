---
name: screen_careers_login
description: Halaman login kandidat Portal Karir menggunakan akun Firestore.
---

# Masuk Kandidat (Login Karir)

**File**: `resources/views/pages/careers/login.blade.php`
**Route**: `careers.login` (GET/POST `/hrm/recruitment/portal/masuk`, di luar middleware `auth`/`portal.candidate` -- publik)
**Koneksi DB utama**: Firebase/Firestore (via `App\Services\Portal\CandidateAuthService`, collection `candidates`)
**Modul**: Recruitment Portal (Publik)

## Tujuan
Form login untuk kandidat yang sudah punya akun (didaftarkan lewat `screen_careers_register`). Berhasil login menyimpan `portal_candidate_uid` di session Laravel (guard sesi custom `portal.candidate`, BUKAN middleware `auth` internal ERP). Setelah sukses, redirect ke `intended_url` (kalau ada, mis. habis diarahkan dari halaman apply) atau ke `careers.dashboard`.

## Field & Kolom
Public property Livewire (form login):
- `email` (string, required, format email) -- `wire:model="email"`
- `password` (string, required) -- `wire:model="password"`

Tidak ada dropdown/lookup di layar ini.

## Aksi / Method
- `login(CandidateAuthService $auth)`:
  1. Validasi `email` (required, email) dan `password` (required).
  2. Panggil `$auth->attempt($email, $password)` -- cari kandidat via `findByEmail()` lalu `Hash::check()` terhadap `password_hash` di dokumen Firestore.
  3. Kalau gagal (kandidat tidak ditemukan atau password salah) -- `addError('email', 'Email atau password salah.')`.
  4. Kalau sukses -- simpan `session(['portal_candidate_uid' => $candidate['_docId']])`, `session()->regenerate()`, lalu redirect (`navigate: false`) ke `session('intended_url') ?? route('careers.dashboard')`.

## Tabel & Model / Firestore Collection yang Disentuh
- **Firestore collection `candidates`** (via `CandidateAuthService::attempt()` -> `findByEmail()` -> `FirestoreRestClient::listDocuments('candidates')`) -- baca saja, tidak menulis apa pun di layar ini.
- Tidak menyentuh MySQL.

## Alur / Business Logic Penting
- Kalau datang dari redirect verifikasi email (`careers.verify-email` route), session `verified=true` di-flash dan ditampilkan banner sukses "Email berhasil diverifikasi, silakan masuk." di atas form.
- Password di-hash pakai `Illuminate\Support\Facades\Hash` standar Laravel (bukan custom), disimpan sebagai field `password_hash` di dokumen Firestore -- BUKAN kolom `password` biasa.
- Layout: `layouts::portal`, title: "Masuk".

## Catatan
Tidak ada gap/TODO eksplisit di komentar kode untuk layar ini. Komentar di baris atas class menjelaskan alasan arsitektur: tidak pakai `Illuminate\Contracts\Auth\UserProvider` penuh karena provider itu didesain sekitar Eloquent, sedangkan Firestore diakses via REST.
