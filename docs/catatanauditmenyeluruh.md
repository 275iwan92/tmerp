# CATATAN AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user supaya tidak
> tercampur dengan daftar item terbuka). Indeks semua backlog:
> **[`BACKLOG.md`](BACKLOG.md)**.

---

## 12. TAMBAHAN DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Hasil sisir 107 file Tier/standar yang belum saya baca sendiri. **3 item
yang kedengarannya kritis SUDAH DICEK LANGSUNG ke DB dan TERNYATA SUDAH
BERES** (dokumen sumbernya `openingapp_tier1-4.md`, jelas ditulis sebelum
banyak sesi berikutnya): `sec_group_permission` sekarang 14 baris (bukan 0
— B-OA2 resolved), `sys_login_hero` tabelnya sudah ada (B-OA5 resolved),
akun admin bisa login normal (B-OA12 kemungkinan resolved juga, sudah
diverifikasi hari ini via login `johiwan`/`admin`). **Pelajaran: dokumen
`openingapp_tier*.md` secara umum sudah usang berat** — kalau baca dokumen
itu, JANGAN percaya "blocker"-nya tanpa cek ulang ke DB dulu.

### 12.7 Checklist master lintas-modul (`checklistmockuperp.md`)

File ini sendiri berfungsi sebagai daftar centang ⬜/🟡 raksasa. Layar yang
masih ⬜ (belum dibangun) di luar yang sudah tercatat di section lain file
ini: Prove Warna (versioning v1/v2/v3), Nota Alokasi Inventory (masih belum
jelas — "posisi stok transaksional" yg dulunya dimaksud di mockup TIDAK
relevan lagi setelah Inventory ledger jadi, perlu klarifikasi ulang
maksudnya kalau mau dikerjakan), Period Manager (buka/tutup periode stok —
`PeriodService` genuinely TAK PERNAH DIBUAT, bukan cuma "belum dipanggil"
seperti catatan lama bilang — dicek 2026-09-03, 0 file ditemukan — TAPI
default konstanta periode Accounting/Stock Opname/Payroll sudah ada di
`sys_constant`, lihat `docs/LOG_JOB_DONE.md` 2026-09-03), FIFO/LIFO
valuation (`StockValuationService`, saat ini cuma AVG), Forecast Engine &
Flow Engine (state-machine) mockup produksi lama (kemungkinan besar sudah
digantikan oleh `production_scheduling_tier*.md` yang jauh lebih baru —
cek dulu sebelum kerjakan, potensi duplikat). Sisa: Setup > Audit Trail
Viewer & generic Lookup Master maintainer (sebagian sudah tercakup
Configuration hari ini, sebagian belum).

### 12.8 Catatan konsistensi — SUDAH DIPERBAIKI 2026-09-22 (bukan lagi cuma disclosure)

Ditemukan 2026-09-02, awalnya dicatat sebagai "disclosure saja, bukan
actionable" — **keputusan itu SALAH, dikoreksi 2026-09-22 atas teguran user**:
kontradiksi antar-dokumen yang membuat pembaca (manusia maupun Claude) bisa
salah percaya status modul HARUS diperbaiki di sumbernya, bukan sekadar
dicatat sebagai disclosure yang dibiarkan.

`masteremployee_tier1.md` dan `TIER_1_ATTENDANCE.md` masih bilang
Recruitment/Payroll/Attendance "ditunda total" di berbagai tempat (Non-Goals,
scope Attendance §3.2, dst), padahal `DEC-SCOPE-OPEN` (2026-08-26) sudah
mencabut itu dan ketiganya sekarang aktif dibangun — Recruitment L1-L9
selesai, Payroll (`tm2pyr`) skema+enkripsi+lifecycle+PPh21 bulanan+GPS
clock-in+slip gaji+transfer bank live, Attendance punya layar presensi
harian & roster nyata. **Kedua dokumen sudah ditambal 2026-09-22**: header
tiap file sekarang punya blok "KOREKSI 2026-09-22" yang tegas, dan setiap
klaim "ditunda"/"tidak dibangun" yang salah di badan dokumen sudah diganti
merujuk status terkini — histori/rasionalisasi keputusan LAMA sengaja
dibiarkan apa adanya (bukan dihapus), supaya jejak keputusan tetap terbaca,
tapi tidak ada lagi klaim status yang salah.

**Pelajaran untuk sesi berikutnya**: kalau menemukan dokumen lama yang
kontradiksi dengan keputusan/kenyataan terkini, JANGAN cuma dicatat sebagai
"disclosure" — perbaiki sumbernya. Instruksi user 2026-09-22: *"ini harusnya
sudah dibuang kata ditunda karena saya sudah override di buka dan saat ini
sudah berjalan, hanya saja saya masih butuh improvement."*

---


Sub-bagian audit yang lain sudah dipindah ke file backlog masing-masing:
12.1 -> 01 · 12.2 -> 02 · 12.3 -> 07 · 12.4 -> 03 · 12.5 & 12.6 -> 06.

---
