# TIER 4 — FLOW ENGINE · UI/UX & INTERACTION DESIGN
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

| | |
|---|---|
| **Tier** | 4 — UI/UX & Interaction (wireframe, tombol, badge, modal) |
| **Pembaca** | Frontend Developer · Claude Code |
| **Basis** | Tier 1 (istilah baku §5) · Tier 2 (state machine §1–§5) · Tier 3 (struktur tabel §3) |
| **Cakupan** | §1 Layar Config (Admin) · §2 Approval Inbox (per Actor) · §3 Log/Riwayat Dokumen · §4 Layar PLT · §5 Badge & Label Konsisten · §6 Embed di Modul Lain |

> **Aturan emas Tier 4 (sama seperti Inquiry):** UI tidak menciptakan aturan baru — hanya memvisualkan Tier 2. Setiap badge/tombol punya rujukan pasti.

---

## 1. LAYAR CONFIG (Admin — Setup Flow per Modul)

Dipakai Admin/Superuser untuk mendefinisikan `flow_config` + `flow_config_step` per modul. **Bukan** layar yang dipakai user modul sehari-hari.

```text
┌──────────────────────────────────────────────────────────────────┐
│ Flow Config — Purchase Requisition (PR)      Divisi: [Semua ▾]    │
├──────────────────────────────────────────────────────────────────┤
│ Total Step: 2                                    [+ Tambah Step]  │
│                                                                    │
│  STEP 1                                                   [Edit]  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Tipe: APPROVAL      Dari: SUBMITTED   Ke: PENDING_L2         │ │
│  │ Jika ditolak → DRAFT                                         │ │
│  │ Jabatan: Kepala PPIC          Rentang nilai: Rp 0 – ∞         │ │
│  │ Label cetak: "Mengetahui"                                     │ │
│  └────────────────────────────────────────────────────────────┘ │
│         │ status_to step 1 = status_from step 2 ✓ (auto-validasi) │
│  STEP 2                                                   [Edit]  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Tipe: APPROVAL      Dari: PENDING_L2   Ke: APPROVED           │ │
│  │ Jika ditolak → DRAFT                                          │ │
│  │ Jabatan: Direksi     Rentang nilai: Rp 50.000.000 – ∞          │ │
│  │ Label cetak: "Menyetujui"                                      │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                    │
│  [Simpan Config]   [Nonaktifkan Config Ini]                       │
└──────────────────────────────────────────────────────────────────┘
```

**Validasi real-time saat Admin edit** (realisasi Tier 2 §6):
- Rantai `status_to[n] = status_from[n+1]` divalidasi otomatis — tampilkan ⚠️ merah jika ada celah.
- Rentang `min_amount`–`max_amount` antar step tidak boleh overlap/gap — tampilkan visual bar rentang nilai per step untuk memudahkan cek.
- Step `APPROVAL` tanpa `position_id` terisi → blokir simpan, pesan "Jabatan wajib diisi untuk step Approval".

---

## 2. APPROVAL INBOX (per Actor — dipakai user modul sehari-hari)

Layar generik, dipakai lintas modul — actor login lalu melihat semua dokumen yang menunggu approval-nya (dari jabatan apapun yang dia pegang, termasuk bila sedang jadi PLT jabatan lain).

```text
┌──────────────────────────────────────────────────────────────────┐
│ Approval Saya (3)                     Login sbg: Budi (Kepala PPIC)│
├──────────────────────────────────────────────────────────────────┤
│ [PR] PR2600041 — Bahan Baku Offset          Rp 42.500.000          │
│      Diajukan: Budi Santoso · 18 Agu 2026        [Detail →]        │
│                                                                    │
│ [PO] PO2600055 — PT Kertas Nusantara        Rp 32.250.000          │
│      Diajukan: Wiwik (Purchasing) · 17 Agu 2026  [Detail →]        │
│      🔁 Kamu approve ini sbg PLT Direksi (s.d. 25 Agu 2026)         │
│                                                                    │
│ [AD] AD2600012 — Stock Adjustment Gudang B   —                     │
│      Diajukan: Sinta (Gudang) · 16 Agu 2026      [Detail →]        │
└──────────────────────────────────────────────────────────────────┘
```

**Badge `🔁 PLT`** wajib tampil jika `flow_log.is_acting` akan bernilai 1 untuk aksi ini — supaya actor sadar dia approve atas nama jabatan lain, bukan jabatannya sendiri.

**Layar Detail Approval:**

```text
┌──────────────────────────────────────────────────────────────────┐
│ PR2600041                                    Status: PENDING_L2   │
├──────────────────────────────────────────────────────────────────┤
│ [ringkasan dokumen — render sesuai modul asal, out of scope Tier4 │
│  Flow Engine, di-embed dari layar modul PR]                       │
│                                                                    │
│ Step saat ini: 2 dari 2 — Menyetujui (Direksi)                    │
│ Nilai: Rp 42.500.000  (≥ Rp 50 jt? Tidak → step ini seharusnya     │
│                          tidak muncul — lihat catatan validasi)    │
│                                                                    │
│ Catatan (wajib bila tolak): [________________________]            │
│                                                                    │
│              [Tolak]              [Setujui →]                     │
└──────────────────────────────────────────────────────────────────┘
```

> Contoh nilai di atas sengaja janggal (Rp 42,5jt tapi step Direksi muncul) — ini ilustrasi kasus yang **seharusnya tidak terjadi** bila validasi Tier 2 §3 benar; Frontend tidak perlu menangani, cukup percaya data dari backend.

---

## 3. LOG / RIWAYAT DOKUMEN (embed di layar detail tiap modul)

Realisasi `flow_log` — dipasang sebagai komponen reusable di layar detail PR/PO/CO/dst, bukan layar berdiri sendiri.

```text
┌─ Riwayat Dokumen ──────────────────────────────────────────────┐
│ ● SUBMITTED    Budi Santoso (PPIC)         18 Agu 2026, 09:12   │
│ ● APPROVED     Rudi (PLT Kepala PPIC) 🔁    18 Agu 2026, 14:30   │
│                └ "OK, sesuai kebutuhan lot CO2600011"           │
│ ○ Menunggu     Direksi                     —                    │
└──────────────────────────────────────────────────────────────────┘
```

- Ikon `●` = sudah terjadi (hijau), `○` = belum (abu-abu).
- Badge `🔁` tampil di baris riwayat mana pun dengan `is_acting=1` — histori permanen menunjukkan itu PLT, bukan pejabat tetap (sesuai Tier 1 prinsip #6, snapshot tidak boleh berubah retroaktif).
- Baris `REJECTED` tampil merah dengan alasan (`notes`) selalu terbuka (tidak collapsible) — transparansi kenapa dokumen ditolak.

---

## 4. LAYAR PLT (Admin/HR — Penunjukan Pejabat Sementara)

```text
┌──────────────────────────────────────────────────────────────────┐
│ Pejabat Sementara (PLT)                        [+ Tambah PLT]     │
├──────────────────────────────────────────────────────────────────┤
│ Jabatan: Kepala PPIC (Offset)                                     │
│ PLT: Rudi Hartono         20 Agu – 25 Agu 2026    Alasan: Sakit    │
│                                             [Batalkan lebih awal]  │
├──────────────────────────────────────────────────────────────────┤
│ Jabatan: Direksi                                                   │
│ PLT: Wiwik (Purchasing)   17 Agu – 25 Agu 2026    Alasan: Dinas    │
│                                             [Batalkan lebih awal]  │
└──────────────────────────────────────────────────────────────────┘
```

Validasi form tambah PLT: tanggal tidak boleh overlap dengan PLT aktif lain untuk jabatan yang sama (Tier 2 §2).

---

## 5. BADGE & LABEL KONSISTEN (lintas semua layar)

| Kondisi | Badge | Warna |
|---|---|---|
| `step_type=APPROVAL`, menunggu | `Menunggu Approval` | Amber |
| `action=APPROVED` (step terakhir) | `Disetujui` | Hijau |
| `action=REJECTED` | `Ditolak` | Merah |
| `action=AUTO_ADVANCED` | *(tidak perlu badge khusus, tampil sbg riwayat biasa)* | Abu-abu |
| `is_acting=1` | `🔁 PLT` | Biru muda, selalu berdampingan dengan nama actor |

---

## 6. EMBED DI MODUL LAIN

Modul PR/PO/CO/dst **tidak membangun ulang** UI approval — cukup panggil 2 komponen reusable dari Flow Engine:
1. **`<FlowActionBar doc="PR" docId="41">`** — render tombol Setujui/Tolak sesuai step aktif dokumen tsb (dipakai di layar detail modul).
2. **`<FlowHistoryLog doc="PR" docId="41">`** — render riwayat seperti §3 (dipakai di layar detail modul, biasanya di bagian bawah/sidebar).

Ini konsisten dengan prinsip Tier 1 §2.1 (satu engine untuk semua modul) — mencegah tiap modul reimplement UI approval sendiri-sendiri.
