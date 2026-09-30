# MAINTENANCE — Tier 1 (riset, desain, alur) · DISETUJUI user 2026-10-01

> Dibuat 2026-10-01 atas instruksi user: *"Kompresor & forklift tetap sebagai mesin tapi diberi FLAG
> non-produksi (just only supported), tetap jadi bagian MAINTENANCE module, karena nanti ada penjadwalan
> PREVENTIVE MAINTENANCE dan BREAKDOWN MAINTENANCE."*
> Status: **§9 K1-K10 + mockup disetujui user 2026-10-01 ("setuju dari preview"). M1 inti DIBANGUN di mockup.test (ddt*)**;
> M2/M3 + migration tm2* = sisa di `backlog03_*.md` §6. Mockup: `docs/mockups/maintenance_v2.html` / `.jpg`.
> Kode: `App\Services\Maintenance\MaintenanceService`, `routes/v2_maintenance.php`, layar `resources/views/v2/screens/maintenance-*.blade.php`.

---

## 0. Keadaan sekarang (dicek ke kode & DB 2026-10-01)

| Sudah ada | Di mana | Dipakai untuk |
|---|---|---|
| 45 mesin nyata, `is_process_machine` 1=produksi / 0=penunjang (CMP-01 kompresor, FKL-01 forklift) | `tm2mst.prod_machine` (PK `machine_id`) | aset maintenance = tabel ini, **tidak dibuat master aset baru** |
| Kolom `maintenance_interval_hours/days`, `last/next_maintenance_date`, `total_run_hours`, `total_impressions`, `mtbf_hours`, `criticality` (A/B/C), `service_vendor` | `prod_machine` | semua masih kosong (45/45) — diisi manual, tidak ada mesin yang menghitung |
| `machine_status` + `STATUSES_BLOCKING` (MAINTENANCE/BREAKDOWN/RETIRED) | `ProdMachine::isSchedulable()`, `MachineCapabilityService` | Scheduling Board menolak mesin yang sedang blocking |
| Log stop mesin (PLANNED_MAINT, SETUP, BREAKDOWN, WAITING_*, POWER, OTHER) | `tm2mst.prod_machine_downtime`, tab Perawatan di Master Mesin v1 | Availability OEE (`MachineOeeService`) |
| Lapor breakdown dari Worklist WO + keputusan reroute/subkon | `tm2prd.prd_breakdown_event` | sisi PRODUKSI (pindah mesin / subkon), bukan perbaikan mesinnya |
| Suku cadang = `mat_raw` kategori **Spare Part Machine** (H, INDIRECT) & **Jasa/Service** (J) | `tm2mst.mat_category` | kategori ada, tapi barang spare part lama banyak masih di "Others"/tanpa kategori (perlu dirapikan, lihat §9 K8) |
| Menu lama `PROD_MTN_SCH` "Schedule Maintenance", `PROD_MTN_PREV` "Preventive Maintenance" | `sys_app_menu` (NOT_BUILT) | kosong |
| V1 lama (ScriptCase) | `docs/ddl/tmprd.sql` `mnt_breakdown` (start/stop/note) | tidak ada data maintenance lain yang perlu dimigrasi |

---

## 1. Riset referensi — tabel perbandingan

| Sistem | Objek utama | Dokumen & alur | Preventive | Breakdown | KPI bawaan | Catatan untuk kita |
|---|---|---|---|---|---|---|
| **SAP PM / S/4 EAM** | Functional location → Equipment; Task List; Maintenance Plan/Strategy; Catalog (object part, damage, cause, activity) | Notification M1 malfunction / M2 request → Order PM01 preventive, PM02 corrective, PM03 breakdown | Strategy (paket waktu/counter), call horizon, shift factor | Notification M1 → order PM03 | MTBF/MTTR (PMIS) | Pisah **permintaan** (notification) vs **pekerjaan** (order); katalog kode kerusakan = sumber Pareto |
| **Oracle eAM** | Asset (asset group), Activity (maintenance BOM + route) | Work Request (approval workflow) → Work Order | Preventive schedule dari activity, time/meter | Work Request → WO | — | WO mewarisi **BOM & langkah dari Activity** = template |
| **MS Dynamics 365 Asset Mgmt** | Functional location, Asset, Counter, Job type/variant/trade | Maintenance request → Work order (lifecycle state) | Maintenance plan: **time-based** atau **counter-based**, maintenance rounds | Request → WO | Asset KPI | Job type = kosakata baku (inspection, lubrication, replacement) |
| **IBM Maximo** | Location, Asset, Job Plan, PM, Meter, Failure Hierarchy | Service Request → WO status **WAPPR → APPR → (WMATL) → INPRG → COMP → CLOSE** | PM frequency: waktu, meter, atau keduanya; job plan | SR → WO | MTBF/MTTR, backlog | status **Waiting Material** & COMP (teknisi) ≠ CLOSE (supervisor) |
| **Infor EAM** | Equipment, PM schedule, Problem/Cause/Action codes | WO type PM, BR (breakdown), CAL, CC/CA corrective | PM schedule | WO BR | — | kode Problem/Cause/Action standar |
| **Odoo Maintenance** | Equipment (+ work center), Team, Category | **1 dokumen** Maintenance Request (kanban stage), tipe Preventive / Corrective | recurrent request (hari) | Corrective request | **MTBF, MTTR, perkiraan kegagalan berikut** otomatis | sederhana, cocok UKM; stage kanban |
| **ERPNext** | Asset, Asset Maintenance (tugas + periodisitas), Asset Repair | Asset Maintenance Log, Asset Repair (biaya, spare part) | periodisitas tugas → ToDo | Asset Repair | — | tugas per aset + due date otomatis |
| **Fiix** | Asset, PM schedule | Work request portal → WO | waktu/meter, **fixed vs floating** | request → WO | — | fixed = jadwal kaku; floating = dihitung dari penyelesaian terakhir |
| **UpKeep** | Asset, meter | request → WO | waktu / pemakaian / hibrida | request | — | mobile-first |
| **eMaint** | Asset, meter | request → WO | waktu / pemakaian / **kondisi** (meter + grafik) | request | — | condition-based dari nilai ukur |
| **MaintainX / Limble** | Asset, procedure (checklist bergambar), part | **QR code di mesin → portal request** (tanpa login), WO | PM + procedure | request via QR | MTTR, PM compliance | teknisi scan QR → histori, WO aktif, SOP |
| **Accurate / Zahir** | Aset Tetap (penyusutan) | — | — | — | — | hanya akuntansi aset; tidak ada modul perawatan |

KPI standar industri (SMRP & praktik CMMS): **MTBF**, **MTTR**, **Availability** (bagian OEE), **PM Compliance** = PM selesai tepat waktu ÷ PM terjadwal (target ≥ 90 %), **Planned Maintenance %** = WO terencana ÷ semua WO (world-class 85–90 %), **Backlog (minggu)** = jam kerja tertunda ÷ jam teknisi per minggu (sehat 3–5 minggu).

Praktik mesin cetak offset (Heidelberg/Komori & checklist pressroom): **harian** bersih roller/blanket, cek ink–water, gripper, filter udara; **mingguan** tegangan belt/rantai, pelumasan, tekanan plate–blanket; **bulanan** suction tape, ink key, doctor blade, kalibrasi warna; **triwulan** servis teknisi pabrikan.

Sumber:
[SAP PM notification/order types](https://www.tutorialspoint.com/sap_pm/sap_pm_quick_guide.htm) ·
[SAP PM guide (Verdantis)](https://www.verdantis.com/sap-plant-maintenance/) ·
[SAP PM order types (2BM)](https://2bmsoftware.com/sap-maintenance-order-types/) ·
[Oracle eAM Work Management](https://docs.oracle.com/cd/E18727_01/doc.121/e13670/T259967T259972.htm) ·
[Oracle eAM Flows](https://docs.oracle.com/cd/E18727_01/doc.121/e52502/T627996T628021.htm) ·
[D365 Maintenance plans](https://learn.microsoft.com/en-us/dynamics365/supply-chain/asset-management/preventive-and-reactive-maintenance/maintenance-plans) ·
[D365 counter-based plans](https://learn.microsoft.com/en-us/previous-versions/dynamics365-release-plan/2021wave1/finance-operations/dynamics365-supply-chain-management/plan-maintenance-based-accumulated-asset-counter-values) ·
[Maximo work orders guide](https://www.maximo-users.net/maximo-work-orders-guide/) ·
[Maximo WMATL/APPR/INPRG](http://surenmaximoworks.blogspot.com/2013/02/workorder-wmatl-appr-inprg-status.html) ·
[Infor EAM work order types (CERN)](https://indico.cern.ch/event/562825/contributions/2274188/attachments/1324387/1987495/Guideline_Work_order_types_20160811_v12_docx_cpdf.pdf) ·
[Four codes every EAM should have](https://swainsmith.com/four-codes-that-every-eam-cmms-should-have/) ·
[Odoo 19 maintenance requests](https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/maintenance/maintenance_requests.html) ·
[ERPNext Asset Maintenance](https://docs.erpnext.com/docs/v13/user/manual/en/asset/asset-maintenance) ·
[Fiix fixed vs floating](https://www.fiixsoftware.com/fixed-or-floating-maintenance-schedules/) ·
[UpKeep best CMMS](https://upkeep.com/blog/best-cmms-software/) ·
[MaintainX work requests](https://www.getmaintainx.com/use-cases/work-request-management) ·
[MaintainX QR codes](https://www.getmaintainx.com/blog/optimizing-maintenance-with-qr-codes-and-a-cmms) ·
[Limble QR code](https://help.limblecmms.com/en/articles/3637230-qr-code-overview) ·
[Limble work request portal](https://help.limblecmms.com/en/articles/6801095-how-to-set-up-a-work-request-portal) ·
[Maintenance KPIs (Oxmaint)](https://oxmaint.com/article/maintenance-kpi-dashboard-15-metrics-operational-excellence) ·
[PM KPIs (eWorkOrders)](https://eworkorders.com/preventive-maintenance/preventive-maintenance-kpis/) ·
[Press maintenance (Printers Parts)](https://printersparts.com/how-proper-press-maintenance-keeps-printing-operations-efficient/) ·
[Heidelberg maintenance](https://snehagraphicssolutions.com/blog/how-to-maintain-your-heidelberg-offset-printing-machine) ·
[Accurate aset tetap](https://www.szetoaccurate.com/cara-input-aset-tetap-di-accurate-online/)

---

## 2. Yang diambil & yang sengaja ditolak (skala CV)

| Ambil | Dari | Alasan |
|---|---|---|
| Pisah **Maintenance Request** (siapa saja, 30 detik) vs **Maintenance Work Order** (teknisi, biaya) | SAP, Oracle, Maximo, Limble | operator tidak perlu tahu biaya/spare part; request bisa ditolak/digabung |
| Breakdown P1 → **WO otomatis langsung** tanpa menunggu approval | praktik Maximo (auto-approve emergency) | mesin mati = setiap menit hilang |
| **Plan = template** (checklist + spare part standar) → WO mewarisi salinannya | Oracle Activity, Maximo Job Plan | prinsip snapshot §4 CLAUDE.md |
| Pemicu **waktu ATAU meter (mana dulu)**, mode **fixed / floating** | D365, Maximo, Fiix | offset press → jam jalan/impresi; kompresor → jam; forklift → hari + jam |
| Kode **Component / Problem / Cause / Action** | SAP catalog, Infor, SwainSmith | Pareto "mesin X paling sering rusak di bagian mana & kenapa" |
| Status **Waiting Part** & **Done (teknisi) ≠ Closed (kepala)** | Maximo WMATL / COMP–CLOSE | spare part sering jadi penghambat; verifikasi sebelum biaya final |
| **QR di mesin** → lapor masalah + lihat histori | MaintainX, Limble | lantai produksi pakai HP |
| KPI MTBF/MTTR otomatis | Odoo | kolom `mtbf_hours` sekarang manual → jadi hasil hitung |

| Tolak (untuk sekarang) | Alasan |
|---|---|
| Functional location / hierarki aset bertingkat (SAP) | 45 mesin, 1 pabrik — cukup **Component** sebagai kode di WO |
| Maintenance round, workflow approval request bertingkat | overkill; kepala maintenance cukup Accept/Reject |
| Condition-based dari sensor IoT | belum ada sensor; disiapkan lewat nilai ukur di checklist (min/max) |
| Daily checklist operator sebagai WO | 43 mesin × harian = ratusan WO/bulan yang tak terbaca → jadi **Daily Check** ringan (fase 3) |

---

## 3. Konsep & dokumen

```
                    ┌─────────────────────────┐
 Operator / siapa   │ MAINTENANCE REQUEST (MR)│  QR mesin / Worklist WO "Breakdown" / menu
 saja ─────────────▶│ Open → Accepted/Rejected│
                    └───────────┬─────────────┘
         P1 Breakdown: otomatis │ Accept (kepala maintenance)
                                ▼
 PREVENTIVE PLAN ──generator──▶ MAINTENANCE WORK ORDER (MWO)
 (template: waktu/meter,        Draft → Confirmed(Scheduled) → In Progress ⇄ Waiting Part → Done → Closed
  checklist, spare part)         │ Start: downtime dibuka + machine_status MAINTENANCE/BREAKDOWN
                                 │ Done : downtime ditutup + READY; plan.next_due dihitung ulang
                                 ├─ Spare part: ambil stok (Inventory issue) / kurang → PR
                                 └─ Jasa vendor: PR/PO kategori Jasa/Service
```

**Jenis WO (`wo_type`)**: `PREVENTIVE` (dari plan), `BREAKDOWN` (mesin mati tak terduga), `CORRECTIVE`
(masih jalan tapi ada kelainan — dijadwalkan), `INSPECTION` (pemeriksaan wajib/kalibrasi, termasuk
pemeriksaan K3 berkala). Jenis ini yang membedakan KPI Planned % (PREVENTIVE+INSPECTION+CORRECTIVE terjadwal)
vs reaktif (BREAKDOWN).

**Prioritas**: P1 Emergency (mesin kritis A mati) · P2 High (≤ 24 jam) · P3 Normal (≤ 1 minggu) · P4 Low.
Default dihitung dari `criticality` mesin + "mesin berhenti?" — petugas boleh ubah.

**Lifecycle mengikuti Form v2 yang sudah disetujui** (Draft / Confirmed / Cancelled / Closed, tombol English
satu kata) + **status kerja** di dalam Confirmed: `SCHEDULED → IN_PROGRESS ⇄ WAITING_PART → DONE`.
Tombol tambahan (satu kata): **Start**, **Hold** (Waiting Part), **Resume**, **Done**, **Close**.

---

## 4. Skema tabel (usulan) — DB `tm2prd`, prefix baru `mnt_`

Semua PK BIGINT `id_<entitas>`; teks sistem English; relasi ke `tm2mst`/`tm2inv`/`tm2pur`/`tm2hrm` = logis.
Dummy hanya di `ddt*`.

| Tabel | Kolom penting | Catatan |
|---|---|---|
| `mnt_code` | `id_code`, `code_type` (COMPONENT/PROBLEM/CAUSE/ACTION), `code`, `name` (English, diterjemahkan kamus), `machine_type` null=umum, `activate` | 1 tabel lookup katalog (gaya SAP catalog) |
| `mnt_plan` | `id_plan`, `plan_code` (PM-OFF02-01), `machine_id`, `plan_name`, `wo_type` (PREVENTIVE/INSPECTION), `trigger_mode` (TIME / METER / TIME_OR_METER), `interval_value`+`interval_unit` (DAY/WEEK/MONTH/YEAR), `meter_type` (RUN_HOURS/IMPRESSIONS), `meter_interval`, `schedule_mode` (FIXED/FLOATING), `lead_days`, `est_minutes`, `needs_machine_stop`, `vendor_id` (opsional, jasa luar), `last_done_date`, `last_done_meter`, `next_due_date`, `next_due_meter`, `activate` | master rencana; `next_due_*` dihitung service |
| `mnt_plan_task` | `id_plan_task`, `plan_id`, `seq_no`, `task_text`, `task_kind` (INSPECT/CLEAN/LUBRICATE/ADJUST/REPLACE/MEASURE), `measure_uom`, `min_value`, `max_value`, `is_required` | checklist template; MEASURE + min/max = benih condition-based |
| `mnt_plan_part` | `id_plan_part`, `plan_id`, `material_id`, `qty`, `uom_code` | spare part standar per plan |
| `mnt_machine_part` | `id_machine_part`, `machine_id`, `material_id`, `component_code`, `qty_installed`, `is_critical` | "spare part apa saja milik mesin ini" → saran min-stock & pilihan cepat di WO |
| `mnt_request` | `id_request`, `doc_no` MR-2610-0001, `request_date`, `machine_id`, `request_kind` (BREAKDOWN/CORRECTIVE/SAFETY/IMPROVEMENT), `priority`, `machine_stopped`, `stopped_at`, `symptom`, `photo_path`, `reported_by` (employee), `breakdown_event_id` (dari Worklist WO), `status` (OPEN/ACCEPTED/REJECTED), `reject_reason`, `wo_id`, audit | dokumen 30 detik |
| `mnt_wo` | `id_wo`, `doc_no` MWO-2610-0001, `wo_type`, `source` (PLAN/REQUEST/MANUAL), `plan_id`, `request_id`, `machine_id`, `component_code`, `priority`, `description`, `needs_machine_stop`, `planned_start`, `planned_end`, `est_minutes`, `actual_start`, `actual_end`, `downtime_id`, `meter_at_done`, `assigned_to` (employee), `vendor_id`, `problem_code`, `cause_code`, `action_code`, `findings`, `work_status`, `stat_confirm`, `stat_cancel`, `stat_close`, `cancel_reason`, `closed_by/at`, biaya ringkas `cost_part`, `cost_labor`, `cost_vendor` | dokumen utama |
| `mnt_wo_task` | `id_wo_task`, `wo_id`, salinan kolom plan_task, `result` (OK/NOT_OK/NA), `measured_value`, `note`, `done_by`, `done_at` | snapshot checklist |
| `mnt_wo_part` | `id_wo_part`, `wo_id`, `material_id`, `qty_plan`, `qty_used`, `unit_cost` (snapshot avg), `stock_doc_id` (issue gudang), `pr_line_id` | material & jasa (kategori J) |
| `mnt_wo_labor` | `id_wo_labor`, `wo_id`, `employee_id`, `start_at`, `end_at`, `minutes`, `rate_snapshot` | jam teknisi |
| `mnt_meter_reading` | `id_meter_reading`, `machine_id`, `meter_type`, `reading_at`, `reading_value`, `source` (MANUAL/PRODUCTION) | koreksi counter fisik |

Riwayat status = `doc_state_log` (polymorphic, sudah ada). Kolom lama `prod_machine.maintenance_interval_*`,
`last/next_maintenance_date`, `mtbf_hours` → **jadi cache hasil hitung** dari plan/WO (tidak diketik manual lagi).

---

## 5. Integrasi

| Dengan | Cara |
|---|---|
| **Master Mesin** `prod_machine` | aset = mesin; `is_process_machine=0` tetap bisa punya plan & WO, tidak pernah muncul di Scheduling Board. Tab baru "Maintenance" di form mesin: plan aktif, WO terbuka, MTBF/MTTR, spare part mesin, QR label |
| **Downtime** `prod_machine_downtime` | WO **Start** (bila `needs_machine_stop`) membuka baris downtime (PLANNED_MAINT untuk PREVENTIVE/INSPECTION, BREAKDOWN untuk BREAKDOWN) + `machine_status`; **Done** menutupnya → Availability OEE otomatis benar. Tidak ada tabel downtime kedua |
| **Scheduling Board (PPIC)** | WO PREVENTIVE Confirmed dengan jendela `planned_start/end` tampil sebagai **blok abu-abu bergaris "MTN"** di baris mesin; PPIC tidak bisa menaruh operasi di atasnya. Geser jendela = usulan PPIC, kepala maintenance konfirmasi (engine suggests, officer confirms) |
| **Worklist WO** (`prd_breakdown_event`) | tombol "Breakdown" yang sudah ada → juga membuat MR P1 (auto WO). Sisi produksi (reroute/subkon) tetap di `prd_breakdown_event` |
| **Inventory** | tab Spare Parts: **Issue** → dokumen keluar gudang `rm_stock` (`ref_doc_type='MNT_WO'`, `issued_to_dept` maintenance); stok tampil di baris |
| **Procurement** | stok kurang → **Create PR** (`pr_requisition.i_source='MAINTENANCE'`, baris material/jasa, `i_need_date` = rencana WO); WO otomatis `WAITING_PART` sampai barang diterima |
| **Biaya** | part (avg price snapshot) + jam teknisi × tarif (**konstanta** `MNT_LABOR_RATE_HOUR` di Settings → Constants, bukan gaji — data payroll terenkripsi) + jasa vendor (PO) → per mesin & `cost_center_code`. Posting jurnal = fase lanjut Finance |
| **HRM** | teknisi = pegawai; akses lewat **grup** (bukan jabatan): `MAINT_TECH`, `MAINT_HEAD`, `PPIC`; operator mana pun boleh buat MR |

---

## 6. Peran & user journey

| Peran (grup) | Buka | Lihat | Klik | Dapat |
|---|---|---|---|---|
| **Operator** (semua user produksi) | scan QR di mesin (HP) / Worklist WO "Breakdown" | nama mesin sudah terisi, histori singkat | isi keluhan + foto + "mesin berhenti?" → **Save** | MR-2610-0007; bila P1 langsung jadi MWO & kepala maintenance dapat notifikasi |
| **Kepala Maintenance** | Maintenance › Request | daftar MR Open (prioritas, umur) | **Accept** (jadi MWO, pilih teknisi & jadwal) / **Reject** (alasan) | MWO Confirmed di kalender teknisi |
| **Teknisi** | Maintenance › My Work (HP) | WO miliknya hari ini | **Start** → isi checklist, Problem/Cause/Action, spare part **Issue**, jam kerja → **Done** | mesin READY lagi, downtime tertutup |
| **Kepala Maintenance** | WO Done | biaya, checklist NOT_OK, temuan | **Close** (atau kembalikan) | biaya final, KPI terhitung, plan next due maju |
| **Sistem (harian 06:00)** | `maintenance:generate-pm` | plan yang jatuh tempo dalam `lead_days` / meter terlampaui | — | MWO PREVENTIVE Draft → kepala set jadwal → **Confirm** |
| **PPIC** | Scheduling Board | blok MTN di baris mesin | usul geser | jadwal produksi tidak menabrak perawatan |
| **Manajemen** | Maintenance › Report | MTBF, MTTR, Availability, PM Compliance, Planned %, biaya per mesin, Pareto kerusakan | filter mesin/bulan | keputusan ganti/overhaul mesin |

---

## 7. Menu v2 (usulan) — modul baru **Maintenance** (kode modul `MAINT`)

- **Work** : Request · Work Order · My Work (teknisi, HP) · PM Calendar
- **Planning** : Preventive Plan · Machine Spare Parts
- **Master** : Maintenance Codes (Component/Problem/Cause/Action)
- **Work › Report** (3 laporan kaya kolom, sesuai aturan "sedikit laporan, banyak kolom opsional"):
  1. *Maintenance WO Report* (per WO: jenis, mesin, durasi, biaya, kode) ·
  2. *Machine Reliability* (per mesin: MTBF, MTTR, Availability, jumlah breakdown, biaya) ·
  3. *PM Compliance* (per plan/bulan: terjadwal vs tepat waktu vs terlambat)

Menu lama `PROD_MTN_SCH`/`PROD_MTN_PREV` di v1 diarahkan ke layar v2 ini (tidak dibangun di v1).

---

## 8. Tahap build (setelah OK)

| Fase | Isi | Bukti selesai |
|---|---|---|
| **M1 Inti** | migration `mnt_*` (ddt dulu), model, `mnt_code` + seeder English, Preventive Plan (DbGrid + form MD: Tasks/Parts), Request (grid + form), Work Order (grid + form MD: Checklist/Spare Parts/Labor/Failure/History, tombol Start/Hold/Done/Close), `MaintenanceWoService` (downtime + machine_status), `PmGeneratorService` + command + tombol "Generate now", dummy ddt | test Feature yang membersihkan datanya sendiri + cek DB |
| **M2 Integrasi** | blok MTN di Scheduling Board, Breakdown Worklist → MR P1, Issue spare part (rm_stock), Create PR, tab Maintenance di Master Mesin, cache kolom `prod_machine` | test integrasi |
| **M3 Lantai & KPI** | My Work (HP), QR label mesin (DevLib Print+QR) → form request publik-login, 3 laporan, kartu KPI, meter reading + akumulasi dari output produksi, Daily Check ringan | laporan dari data ddt |

---

## 9. Keputusan yang perlu OK user (dengan rekomendasi saya)

| # | Keputusan | Rekomendasi | Alasan |
|---|---|---|---|
| K1 | Database & prefix | **`tm2prd`, prefix baru `mnt_`** (V1 dulu juga `mnt_breakdown`) | maintenance = pendukung produksi; 1 modul = 1 DB tanpa DB baru |
| K2 | Request & WO dua dokumen atau satu (gaya Odoo) | **Dua**, tapi breakdown P1 otomatis jadi WO | operator cukup lapor; request bisa ditolak/digabung; biaya hanya di WO |
| K3 | Jadwal preventive | **Floating** default (dihitung dari selesai terakhir), **Fixed** untuk inspeksi wajib/K3 | floating tidak menumpuk WO bila terlambat; inspeksi hukum harus tanggal pasti |
| K4 | Pemicu meter | jam jalan & impresi **diakumulasi otomatis dari output WO produksi** + koreksi manual bulanan dari counter fisik; kompresor/forklift = hour-meter manual | tidak ada sensor; output produksi sudah dicatat |
| K5 | Hierarki aset | **Tidak** bertingkat; bagian mesin = kode Component di WO | 45 mesin; cukup untuk Pareto |
| K6 | Tarif teknisi | konstanta `MNT_LABOR_RATE_HOUR` (Settings → Constants) | gaji terenkripsi (tm2pyr) tidak boleh dibaca modul lain |
| K7 | PM di Scheduling Board | blok MTN mengunci slot; PPIC boleh usul geser, kepala maintenance Confirm | engine suggests, officer confirms |
| K8 | Data spare part lama di "Others"/tanpa kategori | dirapikan ke **Spare Part Machine** lewat layar (bukan skrip diam-diam), daftar kandidat ditunjukkan ke user dulu | data real; aturan tanya dulu sebelum mengubah data |
| K9 | Isi plan & checklist nyata per mesin | ddt = contoh dari praktik umum pabrikan (DUMMY); **tm2 diisi kepala maintenance** lewat layar Preventive Plan | spek mesin tidak boleh dikarang |
| K10 | Inspeksi K3 berkala forklift (pesawat angkat/angkut) & kompresor (bejana tekan) | plan `INSPECTION` FIXED tahunan + tanggal berlaku sertifikat — **perlu dicek bagian HR/K3** soal aturan & periode yang berlaku | kewajiban hukum, sering terlupa |
