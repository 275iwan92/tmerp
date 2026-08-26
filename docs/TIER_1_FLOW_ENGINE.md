# TIER 1 — FLOW ENGINE · BUSINESS CONCEPT & SCOPE
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

| | |
|---|---|
| **Tier** | 1 — Business Concept & Scope |
| **Pembaca** | Semua pihak (Product Owner, Sistem Analis, Developer, DBA) |
| **Basis** | Diskusi arsitektur approval PR → digeneralisasi jadi engine lintas modul |
| **Deliverable** | Dokumen ini + `TIER_2_FLOW_ENGINE.md`, `TIER_3_FLOW_ENGINE.md`, `TIER_4_FLOW_ENGINE.md` |
| **Cakupan** | §1 Latar Belakang · §2 Prinsip Desain · §3 Perbedaan dari Approval-Only · §4 Cakupan Modul · §5 Istilah Baku |

> ⚠️ **CATATAN PENGGANTIAN TABEL LAMA — WAJIB DIBACA SEBELUM IMPLEMENTASI**
> Sistem `flow_*` ini **menggantikan sepenuhnya** dua infrastruktur lama:
> 1. `m_authorize` + `m_authorize_detail` (skema lama di `m_authorize.sql`, approval-only, approver hardcode by username) → **DIHAPUS**, digantikan `flow_config` + `flow_config_step`.
> 2. `doc_state_log` (infra lama disebut di `TIER_3A_PREFIX_LIST.md` bagian C, state-log generik terpisah dari approval) → **DIHAPUS**, digantikan `flow_log`.
>
> Modul manapun (termasuk Inquiry `iqp_*` yang sudah TERKUNCI) yang sebelumnya merujuk `doc_state_log` untuk pencatatan state transition **harus diarahkan ulang ke `flow_log`** saat modul tsb diimplementasi/direvisi. Ini perubahan arsitektur lintas-tier, bukan sekadar rename — catat di `GLOBAL_RULES.yaml`.

---

## 1. LATAR BELAKANG

Kebutuhan awal adalah mekanisme **approval** untuk Purchase Requisition (PR): dokumen dengan nilai di atas threshold tertentu perlu disetujui berjenjang oleh jabatan tertentu. Skema lama (`m_authorize`) sudah berjalan di sistem eksisting tapi punya keterbatasan:

- Approver disimpan sebagai **username hardcode**, bukan jabatan — ganti orang = edit config manual satu-satu.
- Tidak ada **threshold nilai** (Rupiah) untuk approval berjenjang.
- Tidak ada mekanisme **pejabat sementara (PLT)** saat pemegang jabatan berhalangan.
- Approval dan **state transition dokumen** (Draft→Submitted→Approved→dst) dikelola sebagai dua hal terpisah (`m_authorize` vs `doc_state_log`), padahal secara konsep approval **adalah** salah satu bentuk transisi status.

Karena kebutuhan ini berlaku untuk **semua modul dokumen** (PR, PO, CO, DO, DN, PV, HR, OP, AD — sesuai daftar `auth_module` di skema lama), solusinya digeneralisasi jadi satu **document flow engine**, bukan fitur approval yang ditempel per modul.

---

## 2. PRINSIP DESAIN

1. **Satu engine untuk semua modul.** Tidak ada logika approval/state khusus per modul di level tabel — semua modul mendefinisikan alurnya sendiri lewat config (`flow_config` + `flow_config_step`), engine dan tabel-nya generik/reusable.
2. **Approval adalah subset dari flow, bukan sistem terpisah.** Sebuah step dalam alur dokumen bisa berupa `APPROVAL` (butuh persetujuan manusia), `AUTO` (sistem yang trigger, mis. status berubah karena event modul lain), atau `NOTIFY_ONLY` (informasi, otomatis lanjut).
3. **Jabatan (`org_position`), bukan orang, yang jadi penanggung jawab step.** Resolusi ke user aktual terjadi saat runtime — termasuk resolusi ke Pejabat Sementara (PLT) bila pemegang jabatan tetap sedang berhalangan.
4. **Threshold nilai melekat di step**, bukan lapisan terpisah — step hanya aktif jika nilai dokumen berada dalam rentang `min_amount`–`max_amount` step tsb.
5. **Satu log untuk seluruh riwayat pergerakan dokumen** (`flow_log`) — state transition biasa dan approval dicatat di tabel yang sama, dibedakan lewat kolom `action`. Tidak ada dua sumber kebenaran riwayat dokumen.
6. **Snapshot wajib.** `flow_log` menyimpan siapa (`actual_user_id`), jabatan (`actor_position_id`), dan nilai dokumen (`amount_snapshot`) **pada saat kejadian** — supaya riwayat lama tidak berubah tampilannya kalau config atau pemegang jabatan berganti di kemudian hari.
7. **Config per modul, opsional per divisi (SBU).** Default company-wide (`division_id IS NULL`) dengan fallback — bisa dioverride per SBU (Offset/Digital/Indostry) jika strukturnya berbeda.
8. **Polymorphic, bukan FK fisik ke tiap modul.** `flow_log.doc_id` + `module_code` menunjuk ke dokumen sumber tanpa FK database langsung — karena dokumen tersebar di beberapa database (`tmsdi`, `tmpur`, dst) sesuai arsitektur multi-koneksi TMERP.

---

## 3. PERBEDAAN DARI "APPROVAL-ONLY"

| Aspek | Skema lama (`m_authorize`) | Flow Engine (`flow_*`) |
|---|---|---|
| Cakupan | Hanya approval | Approval + seluruh state transition dokumen |
| Approver | Username hardcode | Jabatan (`org_position`), resolve runtime |
| Threshold nilai | Tidak ada | `min_amount`/`max_amount` per step |
| Pejabat berhalangan | Tidak ada mekanisme | PLT (`flow_position_acting`), berlaku lintas modul |
| Log | Tidak ada tabel transaksional (hanya config) | `flow_log` — riwayat lengkap tiap dokumen |
| Step non-approval | Tidak dimodelkan | `step_type = AUTO` / `NOTIFY_ONLY` |
| Percabangan status | Tidak ada | `status_to_reject` per step (mis. ditolak → balik ke Draft/Revisi) |

---

## 4. CAKUPAN MODUL (berdasarkan `auth_module` skema lama + proyeksi scope aktif)

| module_code | Modul | Status scope 10 minggu |
|---|---|---|
| `PR` | Purchase Requisition | Aktif |
| `PO` | Purchase Order | Aktif |
| `PV` | Purchase Receive / Goods Receipt | Aktif |
| `CO` | Customer Order | Aktif |
| `DO` | Delivery Order | Aktif |
| `DN` | Delivery Note | Aktif |
| `OP` | Stock Opname | Aktif (Inventory) |
| `AD` | Stock Adjustment | Aktif (Inventory) |
| `HR` | Human Resource | 🟡 DIBUKA 2026-08-26 (DEC-SCOPE-OPEN) — config boleh mulai dipakai |

> Inquiry (`iqp_*`) **tidak** memakai `flow_*` untuk state machine-nya karena sudah punya desain state backbone sendiri di Tier 2 Inquiry (`is_confirmed`/`is_cancelled` + custom lifecycle). Perlu keputusan lanjutan: apakah Inquiry dimigrasi ke `flow_*` juga demi konsistensi, atau tetap terpisah karena kompleksitas state-nya spesifik (lihat §4.2 Tier 2 Flow Engine).

---

## 5. ISTILAH BAKU

| Istilah | Definisi |
|---|---|
| **Flow** | Rangkaian step yang dilalui sebuah dokumen dari status awal sampai status akhir |
| **Step** | Satu titik transisi status dalam flow; bisa APPROVAL, AUTO, atau NOTIFY_ONLY |
| **Actor** | Jabatan (`position_id`) yang bertanggung jawab atas suatu step APPROVAL |
| **PLT (Acting)** | Pejabat sementara yang menggantikan actor tetap dalam rentang tanggal tertentu |
| **Snapshot** | Data yang dibekukan pada saat kejadian (siapa, jabatan, nilai dokumen) agar riwayat tidak berubah retroaktif |
| **module_code** | Kode modul dokumen (PR, PO, CO, dst) — konsisten dipakai di `flow_config`, `flow_log`, dan penamaan modul lain di TMERP |
