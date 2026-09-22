# Payroll — Keputusan Desain Enkripsi (`tm2pyr`)

> Ditulis 2026-09-03 atas instruksi user ("Tulis ini di md agar saya tidak
> lupa"). Modul Payroll (`tm2pyr`) **BELUM DIBANGUN** — dokumen ini murni
> catatan keputusan desain supaya sesi mendatang tidak perlu menebak ulang
> saat modul ini akhirnya digarap. Jangan mulai implementasi kode dari
> dokumen ini sendirian tanpa membaca ulang & konfirmasi ke user kalau ada
> yang berubah sejak ditulis.
>
> Konteks awal (kenapa enkripsi ini requirement): CLAUDE.md §2 "Payroll
> encryption" — data gaji & histori gaji `tm2pyr` wajib terenkripsi at-rest.
> Materi referensi arsitektur payroll (formula PPh21 TER/BPJS/lembur/e-sign,
> BUKAN soal enkripsi) ada di `docs/payroll_timeattendance/`, terutama
> `tm2hrm_laravel_php83.zip` (paket referensi Laravel lengkap) — paket itu
> TIDAK mengenkripsi apa pun (semua kolom gaji `decimal` polos), jadi tidak
> menjawab pertanyaan enkripsi ini sama sekali.

---

## 1. Scope — kolom mana yang dienkripsi

**HANYA 2 kolom** (keputusan user 2026-09-03, "saja dulu" — mulai sempit,
bisa diperluas nanti kalau perlu):
- `basic_salary` (gaji pokok)
- `net_salary` (take-home pay setelah semua potongan)

**TIDAK dienkripsi** (tetap plaintext): `fixed_allowance`, `gross_salary`,
`attendance_incentive`, `overtime_pay`, semua komponen potongan (BPJS TK/Kes,
PPh21, denda telat, potongan alpha), kontribusi perusahaan.

**Catatan transparansi (FYI, bukan blocker)**: karena `gross_salary` &
seluruh komponen potongan tetap plaintext, `net_salary` sebenarnya BISA
direkonstruksi secara matematis oleh siapa pun yang punya akses baca DB
(`net_salary ≈ gross_salary − total_deductions`, dan `gross_salary` sendiri
kemungkinan juga plaintext) TANPA perlu mendekripsi kolom `net_salary`
sama sekali. Jadi lapisan ini melindungi dari pembacaan LANGSUNG/CEPAT (mis.
`SELECT basic_salary, net_salary FROM ...` sekilas di tool DB), bukan
proteksi penuh terhadap seseorang yang niat menghitung ulang dari komponen
lain. Kalau nanti dirasa perlu, scope bisa diperluas mengikuti pola yang
sama di bawah.

---

## 2. Penyimpanan kunci

**Lokasi**: `tm2sys.sys_constant` (BUKAN `.env`/`APP_KEY`, BUKAN KMS
eksternal) — keputusan sadar user: prioritas **mudah di-recover** (kunci
tetap ada di database, bukan file config yang bisa hilang saat server
pindah/reinstall) di atas proteksi maksimal.

| Field | Nilai |
|---|---|
| `category_code` | `PAYROLL` (kategori baru di `sys_constant_category`, belum ada sebelumnya) |
| `constant_group` | `ENCRYPTION` |
| `constant_key` | `VERBA_SANCTA` (nama kode/penanda, instruksi user — bukan nama deskriptif "PAYROLL_ENCRYPTION_KEY" yang gamblang) |
| `constant_text` | `telasihmakmur` (passphrase asli, LIHAT §3 kenapa ini BUKAN langsung dipakai sbg key mentah) |
| `description` | Pointer ke dokumen ini, supaya siapa pun yang nemu baris ini di DB tahu ke mana harus cari konteks |

Akses baca lewat helper yang SUDAH ADA (`App\Models\Sys\SysConstant`, dipakai
`CostingEngine.php`): `SysConstant::text('VERBA_SANCTA')`.

**Trade-off yang disadari & diterima user**: karena kunci & data terenkripsi
sama-sama hidup di 1 server MySQL yang sama (`tm2sys` & `tm2pyr`, lihat
CLAUDE.md §2 arsitektur database-per-modul, tapi 1 server fisik), siapa pun
yang punya akses baca ke `tm2sys.sys_constant` otomatis bisa mendekripsi
`tm2pyr`. Enkripsi ini efektif melindungi dari: kebocoran PARSIAL (mis. cuma
dump/backup `tm2pyr` saja tanpa `tm2sys`), bug aplikasi yang keliru expose
tabel payroll mentah, atau orang yang browsing `tm2pyr` langsung tanpa akses
`tm2sys`. TIDAK melindungi dari admin DB penuh atau kebocoran seluruh server.
Ini SESUAI keputusan eksplisit user (mudah di-recover > proteksi maksimal),
dicatat di sini supaya jelas ini pilihan sadar, bukan kelalaian desain.

---

## 3. Algoritma & implementasi (rekomendasi, belum dikonfirmasi detail ke user)

- **Passphrase `telasihmakmur` (13 karakter ASCII) TIDAK dipakai langsung
  sebagai key AES mentah** — terlalu pendek/lemah utk AES-256 (butuh 32
  byte). Derivasi: `hash('sha256', $passphrase, true)` → 32 byte key.
  Passphrase yang disimpan di DB (§2) TETAP `telasihmakmur` persis seperti
  diminta user (gampang diingat/di-recover); derivasi ini murni transformasi
  teknis yang terjadi di kode saat runtime, bukan yang disimpan.
- **Algoritma**: AES-256-GCM (authenticated encryption — ketahuan kalau
  ciphertext dirusak/diubah, beda dari AES-256-CBC polos) via
  `openssl_encrypt()`/`openssl_decrypt()` PHP native. **BUKAN** Laravel
  `Crypt`/`encrypted` cast bawaan — itu selalu pakai `APP_KEY` dari `.env`,
  tidak cocok dengan keputusan "kunci di DB, terpisah dari `.env`".
- **Cara pasang**: custom Eloquent cast class (`App\Casts\PayrollEncrypted
  implements CastsAttributes`) ditempel ke `basic_salary`/`net_salary` di
  model gaji (`Payslip`/employee salary, model persis belum ada karena
  modul belum dibangun) — `get()` decrypt otomatis saat baca attribute,
  `set()` encrypt otomatis saat simpan. IV (nonce) di-generate random per
  nilai, disimpan menempel di ciphertext (mis. `base64(iv . tag . ciphertext)`
  dalam 1 kolom) — pola standar GCM, tidak perlu kolom IV terpisah.

---

## 4. Dampak ke laporan (Trial Balance / jurnal auto-posting)

**Dikonfirmasi user 2026-09-03**: laporan Trial Balance/jurnal payroll cuma
butuh **agregat** — total gaji mingguan, total gaji bulanan, atau total
labor **direct** vs **indirect** — BUKAN nilai per-karyawan mentah. Ini
selaras dengan arsitektur enkripsi di atas:

- SQL `SUM(net_salary)` **TIDAK BISA** jalan langsung di level DB (kolom
  berisi ciphertext, bukan angka). **Cara yang benar**: service posting
  jurnal (pola sama `JournalPostingService` yang sudah dipakai PV/Stock Out)
  ambil baris payslip lewat Eloquent (cast otomatis decrypt tiap baris saat
  attribute diakses), lalu `SUM()` di level PHP sebelum membentuk 1 baris
  jurnal agregat. Ini WAJAR & tidak masalah performa pada skala karyawan
  saat ini (~137 aktif, lihat memori `project_hrm_module_status`).
- Konsekuensi lain yang perlu diingat saat modul ini dibangun: filter/sort
  SQL langsung by `basic_salary`/`net_salary` (mis. "cari karyawan gaji >
  Rp5jt", `ORDER BY net_salary`) **tidak bisa** di level query — harus
  ambil semua baris relevan lalu filter/sort di PHP setelah decrypt. Masih
  wajar di skala saat ini, tapi kalau jumlah karyawan sudah ribuan perlu
  dipikir ulang (indeks terpisah/kolom bucket, dll — belum jadi masalah
  sekarang, jangan over-engineer di muka).

---

## 5. Yang BELUM diputuskan (kalau mulai bangun modul ini, konfirmasi dulu)

- Skema tabel Payslip/salary final (referensi `tm2hrm_laravel_php83.zip`
  cukup dekat, tapi belum tentu dipakai 1:1 — perlu diselaraskan ke
  konvensi penamaan proyek ini, prefix tabel `tm2pyr`, dst, lihat CLAUDE.md
  §3).
- Apakah histori/log perubahan gaji (`doc_state_log`-style) juga perlu ikut
  terenkripsi kalau menyimpan nilai gaji di dalamnya.
- Rotasi kunci — SENGAJA belum dipikirkan (di luar scope "mulai sempit dulu"
  user), kalau nanti perlu ganti `VERBA_SANCTA`, semua data lama harus
  didekripsi-reenkripsi ulang (migration terpisah, belum ada rencana).
