# Alat uji multi-bahasa

Dipakai sapuan multi-bahasa 2026-10-03 (`docs/md_for_systems/multilanguage_status.md`). Semua hanya MEMBACA database,
kecuali `langprobe.php <label> record` yang mencatat teks English baru ke kamus **ddtsys** (sama seperti saat layar dibuka
user di mockup.test). Hasil ditulis ke `tools/multilanguage/probe/` (tidak ikut git).

| Alat | Guna | Cara pakai (PowerShell, dari folder tmerp) |
|---|---|---|
| `langprobe.php` | Merender semua layar v2 (menu, grid, form, cetakan) di mockup.test dalam English dan Indonesia; menyimpan teks tiap layar (`snap_<label>.jsonl`) dan daftar teks salah bahasa | `php tools/multilanguage/langprobe.php sebelum` lalu sesudah perubahan `php tools/multilanguage/langprobe.php sesudah` |
| `snapdiff.py` | Membandingkan dua snapshot: teks yang hilang / muncul per layar | `python tools/multilanguage/snapdiff.py id sebelum sesudah` (atau `en`) |
| `analyze.py` | Memilah teks salah bahasa: ditulis di kode atau nilai dari database | `python tools/multilanguage/analyze.py id_in_en` |
| `static_scan.py` | Mencari baris kode v2 yang masih memuat teks Indonesia (tanpa merender) | `python tools/multilanguage/static_scan.py resources/views/v2` |
| `ratchet_lines.php` | Menampilkan apa yang dihitung `MultiLanguageRatchetTest` beserta nama filenya | `php artisan tinker --execute="require 'tools/multilanguage/ratchet_lines.php';"` |
| `db_check.php` | Koneksi semua database + isi tabel bahasa + jumlah teks tanpa terjemahan | `php artisan tinker --execute="require 'tools/multilanguage/db_check.php';"` (tambah `--env=mockup` untuk ddt) |

Urutan yang dipakai: snapshot "sebelum" -> ubah kode -> snapshot "sesudah" -> `snapdiff.py id` harus kosong dari teks
English baru (mode Indonesia tidak berubah) dan `snapdiff.py en` harus kosong dari teks Indonesia baru.
