# TIER 2 — PRODUCTION SCHEDULING (PPIC) · BUSINESS LOGIC & RULES
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

| | |
|---|---|
| **Tier** | 2 — Business Logic & Rules |
| **Basis** | `production_scheduling_tier1.md` (SEMUA 7 keputusan §9.1–9.7 sudah terjawab) |
| **Database target** | `tm2prd` |
| **Status** | DRAFT — siap dieksekusi Claude Code setelah Telma review |

> ⚠️ **PERUBAHAN ISTILAH sejak Tier 1**: **"Job Order (JO)" → "Work Order (WO)"**. Alasan:
> `inventory_rules.yaml`/`inventory_tier1-4.md` **sudah lebih dulu** mengunci kontrak tabel
> `prd_wo` dan `prd_wo_material` (Stock Out wajib menunjuk WO, tarik kebutuhan bahan dari
> `prd_wo_material`). Bukan keputusan baru — menyesuaikan ke kontrak yang sudah berjalan.
> Seluruh istilah lain dari Tier 1 (Task/Job Operation, Crew, Rate Band, dst.) **tidak berubah**.

---

## 1. STATE MACHINE — WORK ORDER (WO)

```
                    ┌──────────┐
   CO confirmed ───►│  DRAFT   │◄─── MTS forecast trigger (§5A.8 Tier1)
   (MTO)            └────┬─────┘
                         │ masuk Simulation Layer (Tier1 §5A.1)
                         ▼
                  ┌──────────────┐
                  │  SIMULATED   │  ← di sandbox, backward schedule + PDR jalan,
                  └──────┬───────┘    tidak menyentuh Master Schedule real
                         │ PPIC submit
                         ▼
                  ┌──────────────┐
                  │  SCHEDULED   │  ← committed ke Master Schedule real
                  └──────┬───────┘
                         │ task pertama mulai
                         ▼
                  ┌──────────────┐
        ┌────────►│ IN_PROGRESS  │◄────────┐
        │         └──────┬───────┘         │
        │                │                 │ breakdown pada task berjalan
        │                │ seluruh task    │ (tanpa keluar IN_PROGRESS,
        │                │ selesai + QC ok │  hanya task-nya yang ON_HOLD
        │                ▼                 │  sementara — lihat §3)
        │         ┌──────────────┐         │
        │         │  COMPLETED   │         │
        │         └──────────────┘         │
        │                                  │
        │         ┌──────────────┐         │
        └─────────┤   ON_HOLD    ├─────────┘
                  └──────┬───────┘
                         │ dibatalkan (jarang — mis. CO dibatalkan Sales)
                         ▼
                  ┌──────────────┐
                  │  CANCELLED   │
                  └──────────────┘
```

**Field pendukung state** (bukan status terpisah, atribut yang menyertai):
- `schedule_layer`: `SIMULATED` | `COMMITTED` — WO hanya "nyata" ke lantai produksi saat `COMMITTED`.
- `is_hot_job`: boolean — insersi via alur interupsi (Tier1 §5A.2), bukan lewat simulasi normal.
- `wo_type`: `MTO` | `MTS`.

**Transisi terlarang (guard wajib di service layer, bukan cuma UI):**
- `SIMULATED → IN_PROGRESS` langsung: **DILARANG**. Wajib lewat `SCHEDULED` (submit eksplisit,
  Tier1 §5A.1 — tak boleh ada jalan pintas walau lewat API).
- `COMPLETED → *`: **DILARANG** apa pun kecuali dibaca. WO selesai adalah fakta historis.
- Downgrade `SCHEDULED → SIMULATED`: **DILARANG**. Kalau perlu re-plan total, buat versi baru
  (pola sama REVISE di Sales — bukan downgrade status).

---

## 2. STATE MACHINE — JOB OPERATION (TASK)

```
   WO SCHEDULED, routing pecah jadi Task per proses
                         │
                         ▼
                  ┌──────────────┐
                  │   PENDING    │  ← menunggu giliran resource (masuk antrean PDR)
                  └──────┬───────┘
                         │ resource (mesin+crew, atau orang untuk manual) tersedia
                         │ + Composite Priority Score menang antrean
                         ▼
                  ┌──────────────┐
                  │  ASSIGNED    │  ← sudah dapat slot mesin+crew+waktu, belum mulai fisik
                  └──────┬───────┘
                         │ waktu mulai tiba, operator konfirmasi mulai
                         ▼
                  ┌──────────────┐
        ┌────────►│   RUNNING    │
        │         └──────┬───────┘
        │                │
        │      ┌─────────┼─────────────┐
        │      │         │             │
        │      ▼         ▼             ▼
        │  ┌───────┐ ┌────────┐  ┌───────────┐
        │  │ DONE  │ │BREAKDOWN│  │  QC_HOLD  │  ← selesai proses fisik,
        │  └───┬───┘ └────┬───┘  └─────┬─────┘     menunggu QC gate (Tier1 §5A "F5")
        │      │          │            │
        │      │          ▼            │
        │      │   ┌─────────────┐     │
        │      │   │ SUBCONTRACT │     │  ← rerouted ke pihak ketiga (Tier1 §9.3 terjawab)
        │      │   └──────┬──────┘     │
        │      │          │ kembali    │
        │      │          └────────────┤
        │      │                       │
        │      │          ┌────────────┘
        │      │          ▼
        │      │   ┌─────────────┐
        │      │   │ QC_REJECTED │──► balik ke PENDING (rework) ATAU eskalasi
        │      │   └─────────────┘    ke Breakdown Event kalau bukan salah proses
        │      ▼
        │  ┌─────────┐
        └──┤QC_PASSED│──► Task berikutnya di routing jadi PENDING (kalau ada),
           └─────────┘    atau WO → COMPLETED (kalau ini Task terakhir & semua lain DONE)
```

**Catatan penting per state:**
- `BREAKDOWN` **tidak otomatis** jadi `SUBCONTRACT` — itu satu dari beberapa opsi reroute (Tier1
  §5A.2/F4); opsi lain: pindah ke mesin alternatif lain (tetap `PENDING` lagi, resource beda).
- `QC_HOLD` wajib untuk task yang ditandai `qc_gate_required = 1` di routing (bukan semua task —
  hanya titik kritis, Tier1 F5). Task non-kritis lompat `DONE → QC_PASSED` otomatis (tanpa
  gate manual) — tapi tetap tercatat lewat, bukan dihilangkan dari histori.
- `QC_REJECTED` **wajib** field `rejection_reason` + keputusan reroute (rework vs breakdown-eskalasi)
  — tidak boleh mengambang tanpa tindak lanjut.

---

## 3. MATRIKS STATUS × TOMBOL (WO)

| Status | Tombol tersedia | Siapa | Efek |
|---|---|---|---|
| `DRAFT` | Tarik ke Simulasi | PPIC | → `SIMULATED` |
| `SIMULATED` | Adjust manual, Re-run PDR, **Submit** | PPIC | Submit → `SCHEDULED` (+`schedule_layer=COMMITTED`) |
| `SCHEDULED` | Drag-reschedule (§5A.3 Tier1), Batalkan | PPIC | Reschedule tetap `SCHEDULED`; Batal → `CANCELLED` |
| `IN_PROGRESS` | (read-mostly di level WO — kontrol ada di level Task) | — | Otomatis dari task |
| `ON_HOLD` | Resume, Batalkan | PPIC/Manager Operasional+ | Resume → `IN_PROGRESS`; Batal → `CANCELLED` |
| `COMPLETED` | Baca saja | — | — |
| `CANCELLED` | Baca saja | — | — |

## 3.1 MATRIKS STATUS × TOMBOL (TASK)

| Status | Tombol | Siapa | Guard |
|---|---|---|---|
| `PENDING` | (otomatis oleh PDR, tak ada tombol manual "assign") | Sistem | Composite Priority Score menentukan urutan |
| `ASSIGNED` | Mulai (konfirmasi fisik), Batal-assign (kembali ke `PENDING`, resource beda) | Operator/PPIC | Mulai wajib crew lengkap hadir (§5A.9 Tier1) |
| `RUNNING` | Selesai, Lapor Breakdown | Operator | Lapor Breakdown → `BREAKDOWN`, catat `breakdown_event` |
| `BREAKDOWN` | Pilih reroute: Mesin lain / Subkontrak | PPIC | Subkontrak **wajib** generate PO `SUBCONTRACT` (Tier1 §9.3) sebelum lanjut |
| `SUBCONTRACT` | Terima kembali → `QC_HOLD` | PPIC/Gudang | QC ketat wajib (Tier1 prinsip #5) |
| `QC_HOLD` | Lulus / Tolak | QA | Lulus → `QC_PASSED`; Tolak → `QC_REJECTED` |
| `QC_REJECTED` | Rework (→`PENDING`) / Eskalasi Breakdown | QA + PPIC | Wajib isi alasan |
| `QC_PASSED`/`DONE` | Baca saja | — | Trigger Task berikutnya `PENDING` |

---

## 4. ALGORITMA PDR — SPESIFIKASI IMPLEMENTASI

Menerjemahkan Tier1 §5A.3–§5A.4 jadi langkah konkret yang bisa dikodekan.

### 4.1 Backward Scheduling (per WO, dipicu saat submit simulasi & tiap re-run)

```
FUNCTION backwardSchedule(wo):
    tasks = wo.routing_tasks ORDER BY seq_no DESC   // mundur dari task terakhir
    cursor_time = wo.due_date

    FOR task IN tasks:
        rate = RateBand.lookup(task.resource_candidate, task.process_type)
                  // §5A.6 Tier1: histori dulu, fallback master kalau data < ambang
        duration = task.qty / rate.value
        duration = MachineCalendar.inflate(duration, task.resource_candidate)
                  // lompati jam non-kerja/shift kosong

        task.target_finish = cursor_time
        task.target_start  = cursor_time - duration
        cursor_time = task.target_start   // task sebelumnya harus selesai di titik ini

    wo.material_needed_by = tasks.first().target_start
        // dipakai Time-Phased Procurement (Tier1 prinsip #9)
    RETURN tasks
```

### 4.2 Composite Priority Score (dipanggil tiap resource free / trigger re-evaluasi)

```
FUNCTION priorityScore(task, now):
    IF task.is_hot_job:
        RETURN (LAYER=0, tiebreak=criticalRatio(task, now))

    cr = criticalRatio(task, now)
    IF cr < 1:
        RETURN (LAYER=1, tiebreak=cr)          // makin kecil cr, makin darurat

    RETURN (LAYER=2, tiebreak=task.target_finish, // EDD
            fallback1=resourceFitPenalty(task),   // Lapis 3
            fallback2=task.wo.confirmed_at)        // Lapis 4 FIFO

FUNCTION criticalRatio(task, now):
    remaining_work = SUM(duration of task..last task in this WO's routing)
    RETURN (task.target_finish - now) / remaining_work

FUNCTION resourceFitPenalty(task):
    IF task.candidate_resource.slow_machine_flag AND faster_alternative_available_soon():
        RETURN PENALTY_HIGH
    IF NOT crewAvailable(task):        // §5A.9 Tier1 — cek operator, bukan cuma mesin
        RETURN PENALTY_BLOCK           // task ini TIDAK BOLEH dipilih sampai crew free
    RETURN 0
```

**Urutan eksekusi saat resource free:**
```
candidates = Tasks WHERE status='PENDING' AND eligible_for(resource)
candidates = applyBatchingRules(candidates)     // §5A.7 Tier1 — gabung dulu
sorted = candidates SORTED BY priorityScore()
sorted = applySequencingRules(sorted)           // §5A.7 Tier1 — penghalus lokal,
                                                 // TIDAK BOLEH ubah urutan Lapis 0-2
next = sorted.first()
next.status = 'ASSIGNED'
```

### 4.3 Deteksi Konflik Crew (§5A.9 Tier1)

```
FUNCTION crewAvailable(task):
    FOR person IN task.proposed_crew:   // operator utama + co-operator
        IF person HAS another_task WHERE status IN ('ASSIGNED','RUNNING')
                                    AND time_overlaps(task.window):
            RETURN FALSE
    RETURN TRUE
```
Guard ini **wajib** dicek server-side saat commit assignment — bukan cuma validasi UI, karena
drag-drop manual PPIC juga harus lewat pengecekan yang sama (tidak ada jalur bypass).

---

## 5. ALUR INTERUPSI/HOT JOB — DETAIL EKSEKUSI

Melengkapi Tier1 §5A.2 dengan langkah implementasi:

```
1. Interupsi masuk (form ringkas: due_date/qty, sumber: DIREKSI|CUSTOMER|INTERNAL_REWORK,
   customer_id jika relevan) → sistem tandai is_hot_job=1, wo_type tetap MTO/MTS asalnya

2. Engine cari slot:
   FOR each mesin yang cocok untuk task pertama WO ini:
       simulasikan penyisipan di titik terdekat due_date
       hitung task existing yang harus mundur (right-shift lokal, BUKAN reschedule total)
       IF hasil valid (tak langgar constraint wajib routing/rate band):
           tambahkan sebagai kandidat opsi

3. Ranking opsi (bukan random urutan):
   - Opsi yang TIDAK membuat job lain telat → tampil duluan
   - Opsi yang membuat job lain telat → tampil dengan dampak eksplisit (+N jam dari due date)
   - Opsi reroute-subkontrak → selalu tampil terakhir (biaya tambahan eksplisit)
   - Urutan tampil bisa disesuaikan bobot histori PPIC (§5A.5 Tier1) — TAPI urutan default
     di atas tetap basis awal sebelum bobot diterapkan

4. PPIC pilih 1 opsi → sistem commit:
   - Task yang tergeser: target_start/target_finish di-update, backward schedule TIDAK
     dihitung ulang total (cuma task yang kena dampak langsung)
   - Task hot job: masuk ASSIGNED di slot terpilih

5. Tulis ke prd_decision_feedback_log (event_type=INTERRUPTION, context=..., option_chosen=...)
```

---

## 6. ALUR MTS FORECAST & REPLENISHMENT — DETAIL

Melengkapi Tier1 §5A.8:

```
1. Direksi input forecast (form periodik, per item MTS, per periode — kadensi Tier2:
   MINGGUAN, selaras replenishment 2-3x/minggu)

2. Tiap siklus replenishment:
   actual = SUM(qty delivered) periode sebelumnya
   accuracy = 1 - ABS(forecast - actual) / forecast
   simpan ke prd_mts_forecast.accuracy_pct

   IF stock_on_hand(item) < safety_level(item):     // safety_level: Tier2 lanjutan,
       generate WO baru (wo_type=MTS, source_type=MTS_FORECAST)  // per-item, Tier 2/3 nanti
       backwardSchedule(wo)   // sama persis engine MTO

3. accuracy_pct < 85% → flag notifikasi ke Direksi (bukan auto-koreksi forecast periode
   berikutnya — forecast tetap input manusia, Tier1 prinsip §5A.8)
```

---

## 7. ALUR BREAKDOWN → SUBKONTRAK → QC — DETAIL

```
1. Operator lapor Breakdown pada Task RUNNING → status BREAKDOWN, catat prd_breakdown_event
   (machine_id, reported_at, reported_by, downtime_estimate)

2. PPIC pilih reroute:
   a. MESIN LAIN → cari resource alternatif sejenis yang available → Task kembali PENDING
      dengan resource_candidate baru, backward schedule task ini SAJA dihitung ulang
   b. SUBKONTRAK →
      - Wajib isi: vendor, due_date kirim, due_date kembali
      - Sistem generate PR/PO tipe SUBCONTRACT (Tier1 §9.3, terhubung tm2pur) —
        entry simplified (skip approval berjenjang PR normal, langsung ke approval
        minimal sesuai role subkontrak — bedakan dari approval substitusi bahan §9.4)
      - Task status → SUBCONTRACT

3. Barang kembali dari subkontrak → Task status → QC_HOLD (WAJIB, tak bisa dilewati)

4. QA putuskan:
   - QC_PASSED → Task berikutnya di routing jadi PENDING
   - QC_REJECTED → catat rejection_reason → PPIC putuskan: rework (balik ke PENDING,
     resource internal) atau eskalasi (breakdown_event baru, kemungkinan ganti vendor)
```

---

## 8. ALUR SUBSTITUSI BAHAN / CUTTING YIELD — DETAIL

```
1. Trigger: MRP netting (backwardSchedule menghasilkan material_needed_by) menemukan
   stok exact tak cukup untuk prd_wo_material tertentu

2. Query kandidat (Tier1 §5A.10):
   candidates = rm_stock WHERE gramatur = required.gramatur
                           AND kualitas = required.kualitas
                           AND ukuran >= required.ukuran
                ORDER BY
                   CASE WHEN days_since_last_out > DEAD_STOCK_THRESHOLD THEN 0   -- Dead Stock dulu
                        WHEN turnover_ratio < SLOW_MOVING_THRESHOLD THEN 1       -- lalu Slow Moving
                        ELSE 2 END ASC,
                   days_since_last_out DESC

3. Usulan tampil ke pengaju (PPIC/Purchasing/Product Development/Product Costing —
   Tier1 §9.4) → approval WAJIB minimal Manager Operasional (atau GM/Direksi)

4. Approved →
   - prd_wo_material.is_substituted = 1, substitute_material_id, cutting_yield_waste_qty
   - Stock Out nanti (Inventory) menarik dari kombinasi hasil ini, BUKAN item asal
   - Waste tercatat ke rm_stockcard (bukan hilang dari pembukuan — kontrak sudah ada
     di Inventory, modul ini hanya mengisi angkanya)
```

---

## 9. PERMISSION MATRIX (ringkas, detail role_id di Tier 3)

| Aksi | PPIC | Manager Operasional+ | QA | Purchasing/Prod.Dev/Costing |
|---|:--:|:--:|:--:|:--:|
| Submit Simulasi → Scheduled | **C** | – | – | – |
| Reschedule manual (drag-drop) | **C** | – | – | – |
| Approve interupsi (pilih opsi) | **C** | – | – | – |
| Approve substitusi/cutting-yield | R (bisa ajukan) | **A** (minimal) | – | R (bisa ajukan) |
| ACTIVE-kan Rule Library | R (bisa draft) | **A** | – | – |
| QC gate lulus/tolak | – | – | **A** | – |
| Approve subkontrak → PO | **C** | **A** (sesuai threshold Procurement) | – | – |
| Input forecast MTS | – | – | – | Direksi (di luar 4 kolom ini) |
| Kalibrasi bobot feedback loop | R (usul) | **A** | – | – |

C=Create/Execute · A=Approve · R=Review/Propose

---

## 10. LANGKAH BERIKUTNYA

1. Review Telma atas rename JO→WO dan detail algoritma di atas.
2. Lanjut **Tier 3** (DDL `tm2prd`) — lihat `production_scheduling_tier3.md`.
3. Lanjut **Tier 4** (UI/UX + mockup) — lihat `production_scheduling_tier4.md`.
