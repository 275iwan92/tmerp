# TIER 2 — FLOW ENGINE · BUSINESS LOGIC & RULES
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

| | |
|---|---|
| **Tier** | 2 — Business Logic & Rules |
| **Basis** | Tier 1 Flow Engine (prinsip §2, cakupan modul §4) |
| **Pembaca** | Sistem Analis · Developer (Backend) · DBA |
| **Cakupan** | §1 Alur Eksekusi Step · §2 Resolusi Actor & PLT · §3 Threshold Nilai · §4 Percabangan (Reject) · §5 Step Non-Approval · §6 Aturan Config · §7 Konsekuensi Migrasi |

---

## 1. ALUR EKSEKUSI STEP (happy path)

```text
Dokumen dibuat (status awal, mis. DRAFT)
  → User submit → sistem cari flow_config aktif untuk module_code (+ division_id, fallback NULL)
  → Ambil flow_config_step dengan status_from = status dokumen saat ini
  → Filter step yang match berdasarkan amount_snapshot dokumen (BETWEEN min_amount AND max_amount)
       - Bila step_type = AUTO      → eksekusi langsung, status_to diterapkan, catat flow_log (action=AUTO_ADVANCED)
       - Bila step_type = NOTIFY_ONLY → status_to diterapkan langsung, kirim notifikasi ke position_id, catat flow_log (action=SUBMITTED)
       - Bila step_type = APPROVAL   → resolusi actor (§2), dokumen menunggu (status tetap status_from atau status antara, sesuai desain modul), tampil di antrian approval actor
  → Actor approve → status_to diterapkan, flow_log dicatat (action=APPROVED)
  → Actor reject  → status_to_reject diterapkan, flow_log dicatat (action=REJECTED, notes wajib diisi)
  → Bila masih ada step berikutnya (step_no + 1) dengan status_from = status_to step ini → lanjut otomatis ke step berikutnya
  → Bila tidak ada step berikutnya → flow selesai, dokumen di status_to final
```

**Catatan penting:** `status_from` step berikutnya harus persis sama dengan `status_to` step sebelumnya — ini yang membuat step-step terangkai jadi satu flow. Validasi ini wajib dicek saat config disimpan (§6).

---

## 2. RESOLUSI ACTOR & PLT

Saat step `APPROVAL` dieksekusi, sistem menentukan siapa user yang berhak approve dengan urutan:

```text
1. Ambil position_id dari flow_config_step
2. Cek flow_position_acting:
     WHERE position_id = step.position_id
       AND is_active = 1
       AND CURRENT_DATE BETWEEN start_date AND end_date
   → Bila ADA baris → actor = acting_user_id (PLT). Set is_acting = 1 di flow_log.
   → Bila TIDAK ADA → actor = pemegang tetap position_id (dari data HRM/org_position).
                       Set is_acting = 0 di flow_log.
3. Bila position_id tidak punya pemegang tetap DAN tidak ada PLT aktif
   → flow BLOCKED, munculkan alert ke Admin/HR (tidak ada approver valid).
```

**Aturan PLT:**
- PLT ditetapkan oleh Admin/HR (bukan self-service oleh pemegang jabatan sendiri) — kontrol internal.
- PLT berlaku **otomatis ke semua modul** yang memakai `position_id` tsb (tidak granular per-modul) — karena PLT menggantikan jabatan orangnya, bukan hanya satu jenis dokumen.
- Rentang tanggal PLT tidak boleh overlap untuk `position_id` yang sama (1 jabatan hanya 1 PLT aktif dalam satu waktu) — validasi di level aplikasi saat `flow_position_acting` disimpan.
- PLT yang berakhir lebih awal dari `end_date` (pejabat tetap kembali lebih cepat) → set `is_active = 0`, bukan hapus baris (jejak audit tetap ada).

---

## 3. THRESHOLD NILAI

- Setiap step `APPROVAL` bisa punya `min_amount`/`max_amount`. Step hanya **muncul dalam alur** jika `amount_snapshot` dokumen berada dalam rentang tsb.
- `min_amount = NULL` diperlakukan sebagai 0. `max_amount = NULL` diperlakukan sebagai unlimited (step tertinggi).
- **Rentang antar-level dalam satu `flow_config` tidak boleh overlap dan tidak boleh ada celah** (gap) — validasi saat config disimpan: `step[n].max_amount` harus persis sama dengan `step[n+1].min_amount` (atau `step[n+1].min_amount` = `step[n].max_amount` bila step[n+1] mulai setelah batas step[n]).
- Contoh valid (PR company-wide):
  - Step 1: `min=0, max=NULL` (Kepala PPIC — selalu jalan, semua nilai)
  - Step 2: `min=50000000, max=NULL` (Direksi — hanya jalan jika nilai ≥ 50 juta, step 1 tetap jalan duluan)
- `amount_snapshot` dihitung sekali saat dokumen submit pertama kali dan **dibekukan** — bila dokumen diedit dan nilainya berubah setelah masuk approval, harus re-submit dari awal (bukan threshold dievaluasi ulang di tengah alur).

---

## 4. PERCABANGAN (REJECT)

- Setiap step `APPROVAL` punya `status_to_reject` sendiri — tidak harus selalu balik ke `DRAFT`, bisa diarahkan ke status lain (mis. `REVISION_NEEDED`) sesuai kebutuhan modul.
- `status_to_reject = NULL` berarti reject **tidak diperbolehkan** di step ini (jarang dipakai, hanya untuk step yang sifatnya wajib lanjut, mis. `NOTIFY_ONLY`).
- Setelah reject, `flow_log` mencatat baris `action=REJECTED` dengan `notes` wajib diisi (alasan) — divalidasi di level aplikasi.
- Dokumen yang di-reject dan diedit ulang lalu disubmit kembali dianggap **submission baru** — flow berjalan dari step 1 lagi (tidak resume dari step yang menolak).

---

## 5. STEP NON-APPROVAL

| step_type | Kapan dipakai | Behavior |
|---|---|---|
| `APPROVAL` | Butuh persetujuan manusia | Lihat §1–§4 |
| `AUTO` | Status berubah karena event dari modul lain (mis. PR ditutup otomatis saat PO terkait selesai Receiving) | `position_id = NULL`. Sistem yang trigger transisi, `flow_log.actual_user_id = NULL`, `action = AUTO_ADVANCED` |
| `NOTIFY_ONLY` | Informasi ke jabatan tertentu tanpa perlu approval untuk lanjut (mis. Direksi "diberi tahu" PO besar diterbitkan, tapi tidak wajib approve) | `position_id` diisi (penerima notifikasi), status langsung lanjut ke `status_to`, `flow_log.action = SUBMITTED` |

Modul yang **sama sekali tanpa approval** (mis. DO, per contoh seed di Tier 3) cukup punya 1 `flow_config_step` dengan `step_type = AUTO`, tetap tercatat di `flow_log` demi konsistensi riwayat lintas modul.

---

## 6. ATURAN CONFIG (validasi saat `flow_config`/`flow_config_step` disimpan)

1. `flow_config`: kombinasi `(module_code, division_id, is_active=1)` harus unik — tidak boleh 2 config aktif bersamaan untuk modul+divisi yang sama.
2. `flow_config_step`: `status_from` step pertama (`step_no=1`) = status awal dokumen modul tsb (didefinisikan per modul, di luar scope tabel ini).
3. Step-step berurutan (`step_no` naik) harus membentuk rantai `status_to[n] = status_from[n+1]` tanpa celah (kecuali cabang berdasarkan threshold nilai — lihat §3).
4. `step_type = APPROVAL` **wajib** punya `position_id` terisi. `step_type = AUTO` **wajib** `position_id = NULL`.
5. Config nonaktif (`is_active=0`) tidak dihapus — jadi riwayat versi, tetap dirujuk oleh `flow_log` lama via `flow_config_id`.

---

## 7. KONSEKUENSI MIGRASI

- **Semua modul yang sebelumnya berencana pakai `doc_state_log`** (state log generik lama) harus dialihkan untuk mencatat ke `flow_log`. Ini mempengaruhi desain state machine modul manapun yang belum final (PR, PO, CO, DO, DN, Inventory).
- **Inquiry (`iqp_*`) dikecualikan untuk saat ini** — state machine-nya sudah didesain custom di Tier 2 Inquiry v3 sebelum Flow Engine ada. Rekomendasi: **jangan migrasi paksa** Inquiry ke `flow_*` karena kompleksitas state-nya (REVISE/RECALC, prove versioning, publish gate Sisi A/B) tidak murni linear step — berisiko memaksakan model yang tidak pas. Evaluasi ulang di masa depan jika dibutuhkan konsistensi penuh, sebagai keputusan terpisah.
- Modul yang **belum mulai desain DDL-nya** (Procurement, Inventory, sebagian Production) langsung pakai `flow_*` sejak awal — tidak ada migrasi, cukup ikuti Tier 3.
