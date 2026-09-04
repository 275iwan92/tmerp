---
name: db_hrm
description: Database tm2hrm secara fisik ada tapi TIDAK DIPAKAI — semua fitur HRM (org/employee/shift/leave) sebenarnya berjalan di tm2mst
---

# Database: tm2hrm (koneksi Eloquent: `hrm`)

**Sumber DDL**: `docs/ddl/tm2hrm.sql` — **0 tabel** (dump kosong, hanya header/footer mysqldump).
**Jumlah tabel**: 0

## TEMUAN PENTING
Digrep seluruh `app/Models/**/*.php` untuk `$connection = 'tm2hrm'` — **hasilnya 0 file**. Semua layar yang secara URL/folder terlihat "HRM" (`resources/views/pages/hrm/*` — org-chart, position, shift-roster, leave-requests, correction-requests) sebenarnya memakai model dengan `$connection = 'tm2mst'` (`OrgPosition`, `EmpShiftRoster`, `EmpLeaveRequest`, `EmpCorrectionRequest`, dll — prefix tabel `org_*`/`emp_*`, konsisten CLAUDE.md §3 yang memang menempatkan prefix ini di tm2mst sbg "Master netral"). **Ini BUKAN bug** — sesuai desain CLAUDE.md §2, HRM secara arsitektur dirancang punya database sendiri (tm2hrm) tapi implementasi berjalan-nya ternyata seluruhnya dari data Master (tm2mst). Database `tm2hrm` fisik ada (bisa dibuat/dikoneksikan) tapi kosong tanpa tabel, dan tidak ada 1 model pun yang menunjuk ke sana.

## Daftar Tabel
Tidak ada (0 tabel, baik di dump maupun di penggunaan kode).

## Catatan
Kalau modul HRM suatu saat perlu benar-benar dipisah ke database sendiri (motivasi awal skema database-per-modul), semua model `emp_*`/`org_*` yang saat ini di tm2mst perlu dimigrasi ke sini — pekerjaan besar, TIDAK direkomendasikan tanpa keputusan arsitektural eksplisit dari user (lihat CLAUDE.md §2, arsitektur database LOCKED).
