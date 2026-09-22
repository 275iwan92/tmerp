# usersecuritystandard.md — Standar Identitas & Keamanan Tiap User

> Ditulis 2026-09-21 atas instruksi user: aturan "setiap user wajib punya
> nomor WhatsApp" ternyata sudah berlaku di kode tapi tidak pernah ditulis
> sebagai aturan — cuma tersirat di `security_defaultpassword_spec.md` §10
> yang topiknya password, bukan identitas user secara umum.
>
> **Baca file ini sebelum**: menambah/mengubah field identitas user atau
> karyawan, membangun layar yang mengirim data pribadi ke user (WA/email/SMS),
> atau menyentuh alur login/lupa-password/setup keamanan.
>
> Dokumen ini **melengkapi**, tidak menggantikan:
> - `security_brief.md` — alur Login → Main Menu.
> - `security_defaultpassword_spec.md` — mekanik `default_password` &
>   `must_change_password` + addendum §10 (lupa password 4 pertanyaan).

---

## 1. Prinsip

Setiap akun (`tm2sys.sec_user`) harus bisa **dibuktikan miliknya sendiri**
tanpa bergantung pada gateway eksternal. Email dan WhatsApp gateway belum
tentu jalan di server produksi (lihat alasan di
`security_defaultpassword_spec.md` §0), jadi pemulihan akun bersandar pada
**data yang user tahu**, bukan pada pesan yang dikirim ke user.

Konsekuensinya: **kelengkapan data identitas bukan urusan HR administratif
saja — itu prasyarat keamanan.** User dengan data identitas kosong tidak bisa
memulihkan akunnya sendiri, dan tidak bisa menerima dokumen pribadi.

---

## 2. Data identitas WAJIB per user (LOCKED)

Empat data di bawah ini dipakai `App\Services\Security\SecurityRecoveryService`
sebagai kunci pemulihan akun. Semuanya-atau-tidak-sama-sekali: `verify()`
tidak memberi tahu field mana yang salah.

| # | Data | Kolom | Cara simpan |
|---|---|---|---|
| 1 | Tanggal lahir | `sec_user.birth_date` (disalin dari `emp_employee.birth_date` bila akun tertaut karyawan) | plaintext |
| 2 | Nama ibu kandung | `sec_user.mother_name_hash` | bcrypt (tak pernah perlu ditampilkan balik) |
| 3 | **Nomor HP terdaftar** | `sec_user.phone` ← `emp_employee.phone` | plaintext (harus bisa dicocokkan & dipakai mengirim) |
| 4 | Pertanyaan keamanan + jawaban | `sec_user.security_question_id` (FK → `sec_recovery_question`) + `security_answer_hash` | bcrypt |

Setup dipaksa lewat middleware `ForcePasswordChange` (diperluas, bukan
middleware baru) — independen dari `must_change_password`, supaya akun lama
yang sudah pernah ganti password tetap kebagian setup. Urutan bila user butuh
dua-duanya: `password.change` dulu, baru `security.setup`.

### 2a. SATU nomor HP = nomor WhatsApp (LOCKED, keputusan user 2026-09-21)

**Tidak ada kolom `whatsapp_no` terpisah.** `emp_employee.phone` (dan
turunannya `sec_user.phone`) adalah **satu-satunya** nomor, dan nomor itu
**wajib nomor WhatsApp aktif**. Ini sudah tercermin di UI: correction request
yang dibuat `SecurityRecoveryService::syncIdentityToEmployee()` memberi label
field ini **"Telepon / WA"**, bukan "Telepon" saja.

Alasan satu kolom: nomor ini dipakai dua peran sekaligus — kunci pemulihan
akun (#3 di atas) **dan** alamat kirim dokumen pribadi (slip gaji, lihat §4).
Dua kolom berarti dua sumber kebenaran yang bisa berbeda diam-diam, dan
perbedaan itu baru ketahuan saat dokumen gaji terkirim ke nomor yang salah.

**Perubahan nomor tidak langsung berlaku.** `syncIdentityToEmployee()`
mengajukan **correction request** ke HR, bukan menimpa `emp_employee.phone`
begitu saja — supaya user tidak bisa memindahkan jalur pemulihan akunnya
sendiri ke nomor lain tanpa sepengetahuan HR.

---

## 3. Password default — per user, BUKAN satu password bersama

Sering disalahpahami. Tidak ada "password default" tunggal yang berlaku untuk
semua orang. `App\Services\Security\PasswordResetService::generate()`
membuat password acak **berbeda untuk tiap user** dengan format `Tmk-` + 6
karakter (pool tanpa karakter ambigu 0/O, 1/l/I), lalu menyimpannya
terenkripsi (Laravel `Crypt`, key = `APP_KEY`) di `sec_user.default_password`.

Implikasi yang harus diingat saat merancang fitur baru:

- **Jangan pernah menuliskan satu nilai password default di dokumen, kode,
  seed, atau UI.** Nilai itu tidak ada.
- Admin membaca password default user lewat **layar aplikasi** (auto-decrypt
  via accessor), bukan lewat SQL langsung.
- `default_password` adalah **kredensial login**, bukan kunci dokumen.
  Memakainya sebagai password PDF berarti menyebarkan kredensial login lewat
  WhatsApp — dilarang. Kunci dokumen diatur terpisah di §3a.

### 3a. Password dokumen pribadi = tanggal lahir (LOCKED, keputusan user 2026-09-21)

Dokumen pribadi yang dikirim ke user di luar aplikasi (mulai dari slip gaji
PDF via WhatsApp) dikunci dengan **tanggal lahir yang bersangkutan**, format
**`ddmmyyyy`** 8 digit tanpa pemisah — `emp_employee.birth_date`, sumber yang
sama dengan pertanyaan pemulihan #1 di §2.

- Dipilih karena user selalu hafal tanpa perlu disimpan/dikirim ulang, dan
  bukan rahasia yang kalau bocor memberi akses ke apa pun selain dokumen itu
  sendiri (beda dari NIK yang dipakai berbagai layanan lain).
- **`birth_date` kosong = dokumen TIDAK boleh dikirim ke luar aplikasi.**
  Jangan pernah memakai password cadangan/generik untuk menutupi data kosong
  — pegawai itu tetap di kanal cetak sampai tanggal lahirnya terisi. Aturan
  ini sejalur dengan §4 poin 2 (nomor kosong mematikan kanal, tidak dilewati).
- Password PDF **tidak pernah ikut di badan pesan WA yang sama**. Pesan boleh
  menyebut *cara* membukanya ("gunakan tanggal lahir Anda ddmmyyyy"), bukan
  nilainya.

---

## 4. Aturan untuk layar yang mengirim data pribadi ke user

Berlaku untuk slip gaji via WA, dan untuk fitur sejenis ke depan.

1. **Alamat kirim hanya boleh dari `emp_employee.phone`** — jangan menerima
   nomor yang diketik bebas di layar pengirim. Nomor yang belum lewat
   correction request HR bukan nomor terverifikasi.
2. **Nomor kosong = kanal kirim dimatikan, bukan dilewati diam-diam.** Layar
   harus menampilkan barisnya dengan penanda jelas dan kontrol kirimnya
   non-aktif. Contoh terpasang: `hrm.payroll.payslip-list` mematikan tickmark
   "Kirim WA" dan `toggleWaFlag()` menolak dengan pesan bila `phone` kosong.
3. **Hitung dan tampilkan sisa data yang belum lengkap** sebagai angka di
   layar itu sendiri (kotak statistik "No. WA Kosong"), supaya kekurangan data
   terlihat sebelum pengiriman massal dijalankan, bukan sesudahnya.
4. **Jangan taruh data pribadi di URL/query string** (aturan umum, ditegaskan
   di sini karena link WA gampang tergoda memuat nominal gaji).

---

## 5. Status data lapangan per 2026-09-21 (BUKAN aturan — kondisi nyata)

Dari **146 karyawan aktif**:

| Data | Terisi | Kosong |
|---|---|---|
| `phone` (= no WA, §2a) | 2 | **144** |
| `birth_date` (= password PDF, §3a) | 135 | 11 |
| Keduanya lengkap (siap kanal WA) | **2** | 144 |

Artinya, per hari ini:

- 144 karyawan **tidak bisa** memulihkan akun sendiri lewat 4 pertanyaan
  (pertanyaan #3 tak mungkin dijawab benar).
- Kanal WhatsApp slip gaji **tidak bisa dinyalakan** untuk mereka.
- 11 karyawan tanpa `birth_date` tetap terkunci di kanal cetak walaupun
  nomor WA-nya nanti terisi (§3a).

Ini **blocker data, bukan blocker kode** — pengisian nomor WA massal lewat HR
harus jalan lebih dulu sebelum kanal WA slip gaji berguna. Lihat juga
[[project_employee_mandatory_field_gap]]: `phone` sudah masuk daftar field
wajib di Master Employee, tapi data lamanya belum ditambal.
