# Master Proses & Master Mesin — Enterprise Capability Register

> Dibuat 2026-09-22 atas permintaan user ("every machine have related with
> process; every process pickup will show which machines would be operate"),
> dengan sampel HTML `gemini-code-1790044302556.html` sebagai acuan minimum.
> Layar: `master.machine`, `production.master-process`,
> `production.routing-template`.

---

## 1. MASALAH YANG DISELESAIKAN

Sebelum ini Master Proses hanya punya 4 kolom berarti (`process_name`,
`gantt_color`, `inhouse`, `activate`) dan Master Mesin adalah daftar aset.
Akibatnya tiga hal:

1. **Tidak ada hubungan Proses ↔ Mesin.** Picker mesin di routing/BOP harus
   menebak dari `machine_type` yang ditulis bebas. Tidak ada cara menjawab
   "PLONG bisa dikerjakan di mesin mana saja".
2. **Parameter produksi tidak punya rumah.** Setup time, kapasitas per jam,
   waste — semuanya diketik ulang per part di `prod_part_process`.
3. **Ukuran mesin berupa teks.** `max_paper_size` = `"103x72"`. Tidak bisa
   dipakai query "mesin mana yang muat plano 79×109 cm".

---

## 2. MATRIKS KAPABILITAS — INTI SISTEM

Tabel `tm2mst.prod_process_machine`. Satu baris = **"proses ini BISA
dijalankan di mesin ini"**, plus parameter khusus pasangan tersebut.

Kenapa tabel sendiri, bukan kolom di salah satu master:

- Relasinya **many-to-many nyata**. PLONG jalan di 4 Die Cutter + 9 Cutting
  Plotter; DIE-04 bisa PLONG *dan* KETREK.
- **Parameter beda per PASANGAN, bukan per proses.** Plong di DIE-04
  (otomatis) jauh lebih cepat dan waste lebih kecil daripada DIE-01 manual.
  Tanpa baris per pasangan, jadwal & HPP langsung salah begitu officer memilih
  mesin lain dari yang diusulkan engine.
- **Envelope fisik juga per pasangan.** Mesin yang sama bisa punya batas
  ukuran berbeda untuk proses berbeda (plong butuh area pisau, cetak butuh
  area gripper).

### Resolusi parameter 3 lapis — LOCKED

Satu-satunya tempat urutan ini ditulis:
`MachineCapabilityService::resolveParams()`. Semua pemakai (picker routing,
Scheduling, ProCost) **WAJIB** lewat sana — kalau query tabel sendiri-sendiri,
urutannya akan bercabang dan HPP untuk data yang sama jadi beda.

| Lapis | Sumber | Arti |
|---|---|---|
| 1 | `prod_process_machine.<param>` | Override khusus pasangan (paling spesifik) |
| 2 | `prod_process.default_<param>` | Nilai default proses |
| 3 | `prod_machine.<param>` | Angka milik mesin (khusus kecepatan & tarif jam) |
| — | `MISSING` | Belum terisi di lapis mana pun |

Nilai dikembalikan **bersama asalnya** (`source`) supaya layar bisa
menunjukkan "ini override atau warisan" — tanpa itu officer tidak tahu angka
mana yang masih perlu dia isi.

`NULL` pada kolom override berarti **"warisi default"**, bukan nol.
`prod_part_process_machine_rate` (per product+part) tetap ada sebagai lapis
paling spesifik di atas semuanya.

### Aturan kelayakan

`machinesForProcess($processId, $constraints)` mengembalikan **semua** mesin
yang terdaftar, tapi menandai yang tidak layak beserta alasannya —
`eligible: false` + `reasons[]`. Mesin yang gagal syarat **tidak
disembunyikan**: pola "engine suggests, officer confirms" (CLAUDE.md §4).
Officer boleh memilih yang ditandai, tapi harus tahu risikonya.

Yang diperiksa: status mesin (MAINTENANCE/BREAKDOWN/RETIRED), bukan mesin
proses, envelope ukuran lembar (**lembar boleh diputar 90°** — cukup salah
satu orientasi muat), dan rentang gramatur.

---

## 3. DUA STATUS MESIN YANG SENGAJA DIPISAH

| Kolom | Arti | Kapan berubah |
|---|---|---|
| `activate` | Mesin masih dimiliki perusahaan | Jarang — saat mesin dijual/dilepas |
| `machine_status` | Kondisi hari ini | Harian: READY/RUNNING/SETUP/MAINTENANCE/BREAKDOWN/IDLE/RETIRED |

Sebelumnya keduanya dipaksa jadi satu flag, sehingga mesin rusak sehari harus
di-nonaktifkan dari master — itu menghilangkannya dari picker routing **dan
merusak histori WO**. Sekarang mesin BREAKDOWN tetap `activate=1`:
`isSchedulable()` yang menolak jadwal baru, bukan penghapusan dari master.

`is_process_machine=0` menandai alat penunjang (kompresor, forklift) supaya
tidak ikut muncul di picker routing dan tidak terus terhitung sebagai "mesin
belum dipetakan" di audit.

Setara di sisi proses: `is_manual=1` memisahkan **"proses tangan, memang tanpa
mesin"** (QC, HITUNG, JEMUR) dari **"belum dipetakan"**. Tanpa flag ini
keduanya terlihat identik dan audit cakupan tidak bisa dipercaya.

---

## 4. ANGKA YANG BELUM ADA TIDAK DIGANTI NOL

Aturan keras di seluruh modul ini. CostingEngine & Scheduling membaca master
ini; `0` akan diperlakukan sebagai fakta.

- `estimateHours()` mengembalikan **NULL** kalau kecepatan belum ada di lapis
  mana pun — bukan 0 jam.
- OEE mengembalikan **NULL per faktor** beserta alasannya, dan OEE total NULL
  selama salah satu faktor belum terukur. "OEE 0%" (mesin mati) dan "OEE belum
  bisa dihitung" (pencatatan belum jalan) adalah dua hal yang sangat berbeda
  bagi manajer produksi.
- Kolom **Kesiapan** (`readinessScore()`) di grid yang menunjukkan mana yang
  masih perlu dilengkapi, bukan angka palsu yang menyamarkannya.

---

## 5. OEE AKTUAL vs TARGET

Sampel HTML hanya punya kotak "Target OEE" — angka manual yang tidak pernah
dibandingkan dengan kenyataan. `MachineOeeService` menghitung sisi
kenyataannya:

| Faktor | Rumus | Sumber data |
|---|---|---|
| Availability | (jam tersedia − stop tak terencana) / jam tersedia | kalender mesin + `prod_machine_downtime` |
| Performance | output aktual / (jam jalan × `speed_target_per_hour`) | `prd_wo_operation` + `prd_wo_operation_output` |
| Quality | qty bagus / (bagus + spoilage + waste) | `prd_wo_operation_output(_defect)` |

- **Stop TERENCANA tidak menghukum Availability.** Terencana/tidak ditentukan
  otomatis dari `downtime_kind`, **bukan dipilih operator** — kalau bisa
  dipilih, Availability gampang dipoles.
- **Jam tersedia tidak pernah diasumsikan 24/7.** Tanpa
  `shifts_per_day` + `hours_per_shift`, Availability = NULL.
- **Quality tidak pernah memakai qty rencana sebagai pengganti output bagus** —
  itu akan membuat Quality selalu terlihat sempurna.
- MTTR/MTBF aktual dihitung dari log breakdown, sebagai pembanding nyata untuk
  `prod_machine.mtbf_hours` yang diisi manual dari manual mesin.

**Status data 2026-09-22:** `prd_wo_operation_output` masih 0 baris, jadi
Performance & Quality belum bisa dihitung di data nyata. Itu bukan bug —
pencatatan output per operasi memang belum jalan di lantai produksi.

---

## 6. ROUTING TEMPLATE & ATURAN URUTAN

`prod_routing_template` + `prod_routing_step`. Urutan operasi standar per
tipe/kategori produk; titik awal BOP ProDev, officer tetap boleh mengubah.

- Nomor urut **berkelipatan 10** supaya langkah bisa disisipkan (15, 25) tanpa
  menomori ulang seluruh routing.
- `branch_code` (MAIN/BODY/LID/L/R) sejalan dengan
  `prod_part_process.branch_code`.

Nilai utamanya bukan penyimpanan urutan, tapi **validatornya**
(`RoutingTemplateService::validate()`):

| rule_type | Arti |
|---|---|
| `MUST_FOLLOW` | Harus SESUDAH proses pembanding |
| `MUST_PRECEDE` | Harus SEBELUM proses pembanding |
| `FORBIDDEN_AFTER` | TIDAK BOLEH sesudah proses pembanding |
| `REQUIRES` | Mewajibkan proses pembanding ada di routing |

`severity=BLOCK` menolak routing; `WARN` hanya memperingatkan. Ditambah cek
`NO_MACHINE`: proses yang butuh mesin tapi belum punya satu pun kapabilitas =
routing tidak bisa dijadwalkan walau urutannya benar.

**Aturan dinilai PER CABANG** — langkah di cabang BODY bukan pendahulu langkah
di cabang LID. Proses yang muncul 2× (cetak 2 lintasan) memakai kemunculan
pertama sebagai acuan urutan.

`expand()` mengubah template jadi langkah siap-pakai: usulan mesin dari
matriks + parameter hasil resolusi 3 lapis. Mesin pilihan template dipakai
**hanya kalau masih memenuhi syarat** untuk pekerjaan itu; kalau tidak, jatuh
ke usulan teratas dan `fallback_reason` menjelaskan kenapa berubah.

12 aturan awal di-seed hanya untuk hal yang **tidak bisa dibantah** di offset
(laminasi sesudah cetak, kupas sesudah plong, dst) supaya validator tidak
langsung berisik dengan aturan tebakan. Sisanya diisi officer.

---

## 7. CHANGEOVER — SETUP TERGANTUNG TRANSISI

`prod_machine_changeover`: tambahan waktu & waste akibat **pergantian**
kondisi (COLOR/SUBSTRATE/PLATE/DIE/SIZE/INK_TYPE), bukan milik mesinnya
sendiri. Ganti 4 warna → 1 warna sekaligus ganti bahan jauh lebih lama
daripada job sejenis berturutan. Tanpa ini jadwal mesin cetak selalu
optimistis.

`from_value`/`to_value` NULL = berlaku dari/ke kondisi apa pun.

> Tabel sudah ada dan modelnya siap, **tapi belum ada layar pengisiannya** dan
> belum dibaca Scheduling. Itu pekerjaan berikutnya, jangan dianggap aktif.

---

## 8. WHERE-USED — INTEGRITAS LINTAS-DB

FK ke modul lain (`tm2sdi.iqp_part_process`, `tm2prd.prd_wo_operation`) adalah
FK **logis** — MySQL tidak bisa menjaganya lintas-database (CLAUDE.md §2).
`whereUsedProcess()` / `whereUsedMachine()` menghitung pemakaian di 5 tempat,
ditampilkan di form sebelum officer menonaktifkan.

Karena itu `capRemove()` **menonaktifkan, tidak menghapus**: baris pasangan
menyimpan parameter yang mungkin sudah dipakai routing/WO lama. Hard delete
memutus jejak kenapa sebuah WO dulu memakai mesin tertentu.

---

## 9. TEMUAN AUDIT SAAT MEMBANGUN (2026-09-22)

Audit `coverageAudit()` saat matriks pertama kali diisi menemukan:

1. **3 proses master belum pernah dibuat** padahal mesinnya ada dan dipakai —
   sudah dicek juga ejaan lain (WAX/LILIN/CTP/PLATE/SEAM), semua nihil:
   - `PLATE MAKING` (CTP-01), `WAXING` (WAX-01), `SEAMING` (SEA-01/02).
   - Ditambahkan + langsung dipetakan. **Kode & parameter ongkos dibiarkan
     NULL** — belum ada angka resmi dari lapangan, dan tebakan lebih berbahaya
     daripada kosong karena CostingEngine akan memakainya sebagai HPP.
2. **CMP-01 (kompresor) & FKL-01 (forklift)** bukan mesin proses → ditandai
   `is_process_machine=0`.
3. **42 dari 45 mesin aktif belum punya ukuran mm** dan **43 belum punya tarif
   jam.** Hanya OFF-02 yang string `max_paper_size`-nya bisa di-parse otomatis.
   Ini gap data lapangan, bukan bug — ditampilkan sebagai kotak statistik &
   kolom Kesiapan, tidak diisi tebakan.

Sesudah perbaikan: **0 proses tanpa mesin, 0 mesin tanpa proses,
66 pasangan kapabilitas aktif.**

---

## 9b. PURGE DATA MESIN LEGACY (perintah user 2026-09-22)

User menolak keputusan migration `2026_09_21_000007` yang hanya men-set
`activate=0` pada 29 baris warisan ScriptCase: daftar aset resmi diberikan
untuk **mengganti** data lama, bukan berdampingan dengannya.

### Cacat datanya

`prod_machine` mencampur tiga hal yang bukan mesin:

| Contoh baris | Sebenarnya apa |
|---|---|
| `KOMORI_GL440_FANDIK` / `_AGUS` / `_DIMAS` / `_YANTO` | **Mesin + NAMA KRU.** Satu Komori GL440 fisik jadi 5 "mesin" karena sistem lama mengunci baris per mesin+operator per shift |
| `SUBKON_HOTPRINT`, `SUBKON_LAMINATING`, `SUBCON_PLONG`, `SUBKON_UV` | **Subkontraktor**, bukan mesin |
| `LAM_YOUBOND` | **Merek film laminasi**, bukan mesin |
| `LIPAT`, `LAMINASI MANUAL` | Pekerjaan tangan, bukan unit mesin |

### Cara menghapusnya

Ke-29 baris **semuanya** masih dirujuk, jadi DELETE polos akan meng-orphan WO
yang sedang jalan dan roster HR. Referensi dipindah lebih dulu:

- **Keluarga KOMORI → OFF-02.** Pasti: hanya satu Komori di daftar aset resmi,
  dan "Lithrone 40" = "GL440" mesin yang sama. Nama kru dibuang, identitas
  mesin dipertahankan sehingga histori WO tetap menunjuk unit fisik yang benar.
- **Sisanya → `machine_id` NULL.** **Sengaja tidak ditebak**: "PLONG 3" tidak
  bisa dipastikan DIE-01/02/03/04 yang mana, dan menempelkan histori WO ke unit
  fisik yang SALAH lebih merusak daripada mengosongkannya.
  `prd_wo_operation.process_name` tetap menyimpan proses apa yang dikerjakan.

Cadangan mentah 29 baris + seluruh referensinya:
`storage/app/backup_legacy_machine_*.json` (di luar git).

### Pencemaran yang sama di emp_work_center

Work center dulu di-generate 1:1 dari `prod_machine`, jadi ikut tercemar
(`WC-KOMORI_GL440_YANTO`). Diukur dulu sebelum dihapus: 10 work center
tercemar, ketergantungannya hanya 12 baris
`emp_employee_work_center_skill` dan **0** baris `emp_shift_roster`.

- 4 work center bernama kru **digabung** ke work center kanonis mesin yang
  sama; 12 baris skill dipindah, yang duplikat digabung. **Sertifikasi tidak
  hilang** — diverifikasi: 3 pegawai yang sama (MAHMUD SUPA'AT, HERU
  KURNIAWAN, +1) tetap tersertifikasi di work center OFF-02.
- 6 work center subkon/merek bahan **dibuang** (0 ketergantungan).

### Cacat di migration 000008 yang ketemu saat audit ulang

`prd_breakdown_event.machine_id` ternyata **NOT NULL**, sehingga update ke NULL
gagal — dan blok `catch` di migration itu **menelan error-nya**, meninggalkan 1
referensi yatim yang tidak ketahuan kalau tidak diaudit ulang ke DB.
Diperbaiki di `000009`: kolomnya dibuat nullable (konsisten dengan
`prd_wo_operation.machine_id` yang sudah nullable) lalu yatimnya dibersihkan.
**Pelajaran: jangan menelan exception pada operasi integritas data.**

### Hasil akhir terverifikasi

| | Sebelum | Sesudah |
|---|---|---|
| `prod_machine` | 74 (29 legacy nonaktif) | **45, semua aktif** |
| `emp_work_center` | 67 | 57 |
| Pasangan proses × mesin | 66 | **68** |
| Mesin tanpa proses | 6 | **0** |
| Referensi yatim (8 tabel diperiksa) | 1 | **0** |
| `emp_shift_roster` | 628 | 628 (utuh) |

### SETIAP MESIN WAJIB PUNYA PROSES

Perintah user. CMP-01 (kompresor) & FKL-01 (forklift) sebelumnya saya tandai
"bukan mesin proses" — itu keliru. Keduanya punya fungsi produksi nyata dan
sekarang punya proses sendiri: **SUPLAI UDARA** (tanpanya die cutter & press
berhenti) dan **HANDLING MATERIAL**. `is_process_machine` dikembalikan ke 1.

Migration `000008` juga memasang **jaring pengaman**: mesin aktif yang belum
punya pasangan otomatis dipetakan ke proses yang jenisnya cocok, dan kalau
prosesnya belum ada, dibuat dari nama jenis mesin. Jadi aturan "setiap mesin
punya proses" tidak bisa bocor lagi saat mesin baru ditambahkan lewat ETL.

### Sisa yang belum dibereskan (butuh keputusan user)

Masih ada **4 work center menunjuk OFF-02** (`WC-CETAK-01`, `WC-CETAK-02`,
`WC-KOMORI_GL440`, `WC-KOMORI_L40`) — duplikat mesin yang sama, tapi sudah
tidak mengandung nama kru. Nama warisan lain seperti `WC-LILIN` juga masih
ada. Menggabungkannya menyentuh roster HR, jadi tidak diputuskan sendiri.

---

## 10. YANG BELUM DIBANGUN

Jangan diasumsikan sudah ada:

- **Layar changeover matrix** — tabel & model siap, UI belum; Scheduling belum
  membacanya.
- **Capacity/load board** (jam tersedia vs jam terjadwal per mesin) —
  `availableHoursPerWeek()` sudah ada sebagai bahannya.
- **Sertifikasi operator per mesin** — `emp_employee_work_center_skill` sudah
  ada, belum disambung ke matriks kapabilitas.
- **Versioning tarif mesin** (`effective_date`) — `hourly_rate` masih nilai
  tunggal; pola rate versioned CLAUDE.md §4 belum diterapkan di sini.
- **Pemakaian matriks oleh ProDev/ProCost/Scheduling.** Sejauh ini pemakai di
  luar 2 layar master baru **hanya** picker mesin di Routing Template.
  Menyambungkan `prod_part_process` & `prd_wo_operation` ke matriks ini adalah
  langkah berikutnya yang paling berdampak.
- **Preventive maintenance scheduler** — kolom interval & tanggal jatuh tempo
  ada + peringatan di grid, tapi belum ada worklist/notifikasi otomatis.

---

## 11. FILE TERKAIT

| Jenis | Path |
|---|---|
| Layar | `resources/views/pages/master/machine.blade.php` |
| | `resources/views/pages/production/master-process.blade.php` |
| | `resources/views/pages/production/routing-template.blade.php` |
| Service | `app/Services/Production/MachineCapabilityService.php` |
| | `app/Services/Production/MachineOeeService.php` |
| | `app/Services/Production/RoutingTemplateService.php` |
| Trait | `app/Livewire/Concerns/ManagesProcessMachineCapability.php` |
| Model | `app/Models/Master/ProdProcessMachine.php`, `ProdRoutingTemplate.php`, `ProdRoutingStep.php`, `ProdProcessPrecedence.php`, `ProdMachineChangeover.php`, `ProdMachineDowntime.php` |
| Migration | `2026_09_22_000002` … `000007` |
| Test | `tests/Feature/MachineProcessCapabilityTest.php` (25 test) |
