# TIER 3 — FLOW ENGINE · DATABASE & DATA PROCESS FLOW
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

| | |
|---|---|
| **Tier** | 3 — Database & Data Process Flow (DDL, ERD, penggantian tabel lama) |
| **Pembaca** | Database Administrator (DBA) · Backend Developer · Claude Code |
| **Basis** | Tier 1 (prinsip §2) · Tier 2 (state machine §1–§6) |
| **DB** | `tmmst` (master netral, dipakai lintas modul via VIEW read-only — pola `TIER_0_GLOBAL_ARCHITECTURE.md` §3) |
| **File DDL** | `DDL_FLOW_ENGINE.sql` (executable, lampiran dokumen ini) |
| **Cakupan** | §1 Penggantian Tabel Lama · §2 ERD · §3 Struktur Tabel · §4 Prasyarat FK · §5 Migrasi Data · §6 Seed Contoh |

---

## 1. PENGHAPUSAN / PENGGANTIAN TABEL LAMA — WAJIB DIEKSEKUSI

| Tabel lama | Sumber | Status | Digantikan oleh |
|---|---|---|---|
| `m_authorize` | `m_authorize.sql` (upload, skema `tmmst` server TM188) | **DROP** | `flow_config` |
| `m_authorize_detail` | `m_authorize.sql` | **DROP** | `flow_config_step` |
| `doc_state_log` | Disebut di `TIER_3A_PREFIX_LIST.md` bagian C (Infrastruktur Lintas-Modul), belum ada DDL eksekusi | **BATALKAN RENCANA PEMBUATAN** — jangan dibuat, langsung pakai `flow_log` | `flow_log` |
| `doc_history` | Disebut di `TIER_3A_PREFIX_LIST.md` bagian C (snapshot JSON saat freeze) | **TETAP** — beda fungsi (snapshot dokumen freeze, bukan riwayat status), tidak digantikan | — |

**Perintah eksekusi:** `DDL_FLOW_ENGINE.sql` sudah termasuk `DROP TABLE IF EXISTS` untuk `flow_config`, `flow_config_step`, `flow_position_acting`, `flow_log` (guna idempotency saat re-run). Untuk `m_authorize`/`m_authorize_detail` di server TM188 (`tmmst`), **DROP manual terpisah** setelah data lama (bila ada yang perlu diselamatkan — lihat §5) sudah dipindahkan:

```sql
-- Jalankan HANYA setelah migrasi data (§5) selesai & diverifikasi
DROP TABLE IF EXISTS `m_authorize_detail`;
DROP TABLE IF EXISTS `m_authorize`;
```

> `doc_state_log` tidak perlu di-DROP karena belum pernah dieksekusi DDL-nya (baru rencana) — cukup jangan dibuat, hapus dari rencana implementasi manapun yang mereferensikannya.

---

## 2. ERD (logical)

```text
                    org_position (HRM, existing)
                          │
              ┌───────────┼──────────────────┐
              │           │                  │
              ▼           ▼                  ▼
    flow_config_step  flow_position_acting   (pemegang tetap — dari data HRM,
       .position_id      .position_id          di luar scope tabel ini)
              │
              │ N:1
              ▼
       flow_config ──── 1:N ──── flow_config_step
              │
              │ 1:N (via flow_config_id)
              ▼
          flow_log ──── N:1 (opsional) ──── flow_config_step (.flow_step_id)
              │
              │ polymorphic (module_code + doc_id)
              ▼
    [dokumen sumber lintas-DB: tmpur.PR, tmpur.PO, tmsdi.CO, dst]
```

---

## 3. STRUKTUR TABEL

Lihat `DDL_FLOW_ENGINE.sql` untuk DDL lengkap dengan komentar per kolom. Ringkasan 4 tabel:

1. **`flow_config`** — induk config per `module_code` (+ opsional `division_id`).
2. **`flow_config_step`** — anak `flow_config`, tiap baris = 1 step (transisi status, jenis step, jabatan, threshold nilai).
3. **`flow_position_acting`** — PLT per jabatan, lintas semua modul.
4. **`flow_log`** — log transaksional polymorphic, satu baris per aksi (submit/approve/reject/auto-advance) per dokumen.

---

## 4. PRASYARAT FK (belum bisa langsung diaktifkan)

DDL menyertakan FK yang **di-comment**, aktifkan setelah tabel referensi tersedia:

| FK di-comment | Menunggu tabel |
|---|---|
| `flow_config_step.position_id` → `org_position` | Modul HRM — `org_position` harus 1 posisi = 1 PIC jelas (lihat catatan `userMemories` HRM) |
| `flow_position_acting.position_id` → `org_position` | sama seperti di atas |
| `flow_position_acting.acting_user_id` → `sys_user` (nama tabel asumsi) | Modul Security/Auth — sesuaikan nama tabel user aktual |
| `flow_log.actor_position_id`, `.actual_user_id` | Tidak pakai FK fisik (snapshot, boleh reference ke ID yang mungkin sudah tidak aktif) — **sengaja tanpa FK constraint**, hanya index |

---

## 5. MIGRASI DATA (dari `m_authorize` lama, bila diperlukan)

Data existing di server TM188 (`m_authorize` + `m_authorize_detail`) memakai `auth_name` (username), bukan `position_id`. Migrasi **tidak otomatis 1:1** karena butuh mapping manual username → jabatan (`org_position`). Langkah:

1. Export data `m_authorize` + `m_authorize_detail` existing (referensi: 9 modul, 10 baris detail, lihat `m_authorize.sql`).
2. Untuk tiap baris `m_authorize_detail`, tentukan `position_id` yang sesuai dengan `auth_name` (mis. `wiwik` → posisi "Staff Purchasing", `nisa` → posisi "Operational Manager").
3. Insert ke `flow_config` (1 baris per `auth_module` aktif) dan `flow_config_step` (1 baris per level, dengan `min_amount=0, max_amount=NULL` sebagai default — threshold nilai baru ditambahkan manual sesuai kebutuhan tiap modul, karena skema lama tidak punya konsep ini).
4. **Modul yang `is_active=0` di skema lama** (PR, DN, PV — lihat data seed lama) berarti approval belum aktif dipakai; boleh langsung dirancang baru dengan `flow_config` tanpa perlu migrasi data lama (tidak ada histori approval yang hilang).
5. **Modul yang `is_active=1`** (PO, CO, HR, OP, AD) — migrasi `flow_config`/`flow_config_step` HARUS selesai dan diverifikasi sebelum `m_authorize`/`m_authorize_detail` lama di-DROP, agar approval tidak terhenti untuk modul yang sedang berjalan.

---

## 6. SEED CONTOH

Lihat bagian bawah `DDL_FLOW_ENGINE.sql` (di-comment, contoh ilustrasi):
- **PR company-wide, 2 step**: Kepala PPIC (selalu) → Direksi (jika ≥ Rp 50 juta).
- **DO, 1 step tanpa approval**: `step_type=AUTO`, auto-confirm.

Sesuaikan `position_id`/`division_id` dengan data riil `org_position`/`org_division` sebelum dieksekusi.
