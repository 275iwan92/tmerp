# TIER 4 — PRODUCTION SCHEDULING (PPIC) · UI/UX & MOCKUP
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

Basis: Tier 2 (logic) + Tier 3 (DDL). Ikuti `griduistandard.md` dan `FORM_UI_STANDARD.md` untuk
grid/form standar (tidak diulang di sini) — dokumen ini fokus ke layar yang **beda pola** dari
grid/form biasa: Scheduling Board (Gantt) dan Interruption Dialog.

---

## 1. DAFTAR LAYAR

| Layar | Jenis | Sumber lahir | Mockup |
|---|---|---|---|
| **Worklist WO** | Grid standar (griduistandard.md) | Otomatis dari CO confirmed (MTO) / cycle MTS | — (ikut pola grid biasa) |
| **Scheduling Board** | Custom (Gantt per mesin) | Dari Worklist WO → Simulasi | `mockup_scheduling_board.html` |
| **Interruption Dialog** | Modal custom (multi-opsi) | Dipicu dari Board saat insert hot job | `mockup_interruption_dialog.html` |
| **Exception Dashboard** | Grid standar + badge | Bagian dari Board (panel/tab), bukan layar terpisah | (di dalam mockup Board) |
| **Rule Library** | Grid standar + form | Master-like, tapi butuh approval (§Tier2 §9) | ikut pola grid+form standar |
| **MTS Forecast Entry** | Form periodik | Direksi input manual | ikut pola form standar |
| **Breakdown & Subcontract Form** | Form standar + state | Dari Task RUNNING → Lapor Breakdown | ikut pola form standar |

---

## 2. WORKLIST WO — pola grid standar, catatan khusus

Ikuti `griduistandard.md` §non-Master penuh: kotak statistik pastel (Total WO / Sedang Berjalan /
Berisiko Telat / Menunggu Simulasi), kolom identitas (avatar produk + kode WO + customer/GENERAL),
chevron expand (§9 griduistandard) untuk intip Task di dalamnya tanpa pindah halaman.

**Kolom Aksi khusus** (beda dari pola umum): tidak ada ✓ Confirm generik — sebagai gantinya
**↗ Buka** langsung ke Scheduling Board dengan WO itu ter-highlight (karena "confirm" WO sebenarnya
adalah alur Simulasi→Submit yang butuh visual Gantt, bukan sekadar flip status).

**Badge status** (palet pastel §2 griduistandard, dipetakan manual):
`DRAFT` abu · `SIMULATED` biru · `SCHEDULED` kuning · `IN_PROGRESS` hijau · `ON_HOLD` oranye ·
`COMPLETED` hijau tua · `CANCELLED` merah pudar. `is_hot_job=1` → badge tambahan 🔥 merah solid,
selalu di kiri (paling menonjol), konsisten prinsip Tier1 "tampak jelas, tak diam-diam".

---

## 3. SCHEDULING BOARD — spesifikasi layar utama

### 3.1 Layout

```
┌──────────────────────────────────────────────────────────────────────────┐
│ Scheduling Board          Mode: [● Simulasi ○ Live]    [+ Sisip Hot Job]  │
│ Rentang: [27 Agu ▾] – [29 Agu ▾]        Mesin: [Semua ▾]                 │
├──────────────────────────────────────────────────────────────────────────┤
│ ⚠ EXCEPTION: 3 belum terjadwal · 2 berisiko telat · 1 mesin kosong >2jam  │  ← klik = filter board
├───────────────┬────────────────────────────────────────────────────────┤
│ Mesin          │  08:00   10:00   12:00   14:00   16:00   18:00   20:00 │
├───────────────┼────────────────────────────────────────────────────────┤
│ Lithrone G40   │ ▓▓▓▓ WO-0231 Cetak ▓▓▓▓   ░░ idle ░░  ▓▓▓ WO-0245 🔥 ▓▓▓│
│ (Operator:     │                                                        │
│  Budi + Joko)  │                                                        │
├───────────────┼────────────────────────────────────────────────────────┤
│ Laminasi L1    │        ▓▓▓ WO-0228 ▓▓▓                                 │
├───────────────┼────────────────────────────────────────────────────────┤
│ Plong P2       │ ▓▓ WO-0230 ▓▓  [BREAKDOWN] ▒▒ menunggu reroute ▒▒       │
└───────────────┴────────────────────────────────────────────────────────┘
│ Manual Job                                                                │
├───────────────┬────────────────────────────────────────────────────────┤
│ Packing        │ Siti: ▓▓ WO-0225 ▓▓   Rina: ▓▓▓ WO-0227 ▓▓▓             │
└───────────────┴────────────────────────────────────────────────────────┘
```

- **Baris = resource** (mesin, atau nama orang untuk kelompok "Manual Job" — sesuai §5A.9 Tier1:
  task manual dijadwalkan per orang langsung, bukan per "mesin manual" fiktif).
- **Blok = Task**, warna dasar per status (`PENDING`=abu-abu bergaris, `ASSIGNED`=biru muda,
  `RUNNING`=hijau, `BREAKDOWN`=merah solid+ikon ⚠, `QC_HOLD`=kuning+ikon 🔍). `is_hot_job` → outline
  merah tebal + ikon 🔥 di pojok blok, terlepas dari warna status.
  Blok task yang **hasil Batching Rule** (§5A.7 Tier1, mis. dieline sama digabung) ditampilkan
  sebagai **satu blok dengan garis putus-putus di dalamnya** memisah tiap WO anggota — supaya
  PPIC tetap tahu itu gabungan, bukan 1 WO tunggal.
- **Baris resource label**: untuk mesin, baris kedua kecil di bawah nama mesin menampilkan Crew
  yang sedang bertugas (Operator Utama + Co-Operator) — bukan kolom terpisah, supaya Gantt tetap
  ringkas.
- **Toggle Mode Simulasi/Live** di pojok kanan atas — **wajib terlihat jelas warnanya beda**
  (mis. biru = Simulasi, hijau = Live) supaya PPIC tak pernah salah kira sedang mengubah jadwal
  real padahal masih sandbox (Tier1 §5A.1, prinsip krusial).
- **Baris peringatan Exception** (di atas board, sebelum grid) — ringkasan klik-untuk-filter, bukan
  layar terpisah (Tier1 F17: PPIC tak sanggup scan manual, harus proaktif tampil).
- **Drag-drop**: geser blok task ke slot lain (waktu/mesin) → validasi real-time (crew conflict
  §Tier2 4.3, machine calendar, urutan wajib routing) → gagal = blok "memantul" balik + tooltip
  alasan; berhasil = blok pindah, exception bar refresh.

### 3.2 Tombol utama
- **[+ Sisip Hot Job]** → buka Interruption Dialog (§4).
- **[Submit ke Master Schedule]** — hanya muncul saat Mode=Simulasi & ada perubahan belum
  di-commit; klik → validasi ulang freshness (Tier1 §5A.1) → sukses pindah Mode=Live otomatis.
- Filter Mesin (dropdown) — bukan checkbox multi (konsisten larangan filter berlebih di
  griduistandard §6), cukup satu dropdown "Semua/[nama mesin]".

---

## 4. INTERRUPTION DIALOG — modal multi-opsi

Dipicu dari tombol [+ Sisip Hot Job] atau otomatis saat ada permintaan urgent masuk dari luar
(nanti terhubung Tier lanjutan). Modal, bukan halaman penuh — karena keputusan harus cepat.

```
┌───────────────────────────────────────────────────────────┐
│  🔥 Sisipkan Pekerjaan Mendadak                        [×] │
├───────────────────────────────────────────────────────────┤
│  Sumber: [Direksi ▾]   Customer: [– opsional –]            │
│  Produk: [cari...]     Qty: [____]   Due: [tgl] [jam]       │
│                                                              │
│  Sistem menemukan 3 opsi penyisipan:                        │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ○ Opsi 1 — RANK TERTINGGI                            │   │
│  │   Sisip di Lithrone G40, 14:00–16:00                 │   │
│  │   Dampak: WO-0245 mundur 2 jam — masih aman jauh dari│   │
│  │   due date-nya (27 Sep)                              │   │
│  └─────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ○ Opsi 2                                             │   │
│  │   Sisip di Lithrone G40, geser WO-0230                │   │
│  │   ⚠ Dampak: WO-0230 BERISIKO TELAT +3 jam dari due    │   │
│  │   date-nya                                            │   │
│  └─────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ○ Opsi 3 — Reroute Subkontrak                        │   │
│  │   Tidak ada job lain tergeser                        │   │
│  │   ⚠ Dampak: +Rp 850.000 biaya subkontrak (estimasi)   │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│                          [Batal]   [Terapkan Opsi Terpilih] │
└───────────────────────────────────────────────────────────┘
```

- Opsi **tidak selalu 3** — render dinamis sesuai jumlah kandidat valid (Tier1 §5A.2).
- Opsi yang **tidak** menggeser job lain (aman) selalu tampil dengan aksen hijau; yang berisiko
  telat = aksen kuning/oranye; reroute subkontrak = aksen biru (biaya, bukan risiko jadwal).
  **Bukan warna acak** — mengikuti palet pastel baku griduistandard.
- Radio button (pilih 1), tombol **[Terapkan Opsi Terpilih]** disabled sampai satu opsi dipilih.
- Setelah diterapkan → commit langsung ke Master Schedule (Live), dialog tertutup, Board refresh
  dengan hasilnya, **tanpa** perlu Submit terpisah (beda dari alur Simulasi biasa — interupsi
  memang jalur darurat, Tier1 §5A.2 sudah menegaskan ini pengecualian sah).

---

## 5. BINDING TABLE (kontrak field → kolom, pola sama masterproduct_tier4.md §7)

| Elemen UI | Kolom/Sumber |
|---|---|
| Blok Task warna | `prd_wo_operation.task_status` |
| Outline hot job | `prd_wo_operation.is_hot_job` (atau `prd_wo.is_hot_job` diwarisi) |
| Label Crew di bawah nama mesin | `prd_crew_assignment` JOIN `emp_employee` WHERE `operation_id` aktif |
| Badge exception bar | `v_prd_exception_dashboard` |
| Toggle Mode | `prd_wo.schedule_layer` |
| Opsi Interruption Dialog | Hasil runtime engine Tier2 §5, tidak disimpan sampai dipilih (baru ditulis `prd_decision_feedback_log` setelah Terapkan) |
| Rank opsi (RANK TERTINGGI) | Hasil kalibrasi bobot §5A.5 Tier1 — default urutan dari Tier2 §5 langkah 3 bila belum ada histori |

---

## 6. CATATAN VALIDASI SILANG (jangan dilewat saat build)

- Board **wajib** query `emp_shift_roster` sebelum menampilkan opsi crew di drag-drop manual
  (Tier3 §12 poin 2) — dropdown pemilihan operator saat assign manual **hanya** menampilkan
  orang yang ter-roster di mesin & tanggal itu, bukan seluruh karyawan.
  Bukan diperkirakan sekali dan lupa — tiap kali Board dibuka, wajib query itu.
- Blok task yang task-nya masuk `QC_HOLD` **tidak boleh digeser drag-drop** — kunci posisi
  (visual: cursor "not-allowed" saat hover) sampai QA memutuskan lulus/tolak.

---

## 7. LANGKAH BERIKUTNYA

1. Bangun `mockup_scheduling_board.html` dan `mockup_interruption_dialog.html` (HTML statis,
   data dummy) — lihat file terpisah, sudah dibuat bersamaan dokumen ini.
2. Review Telma atas seluruh Tier 1–4 sebelum diserahkan ke Claude Code.
3. Update `production_state.yaml` (buat baru, ikuti pola `sales_state.yaml`) mencatat Tier 1–4
   SPEC LENGKAP, status implementasi NOT_BUILT.
