# TIER 1 — PRODUCTION SCHEDULING (PPIC) · BUSINESS CONCEPT & SCOPE
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

| | |
|---|---|
| **Tier** | 1 — Business Concept & Scope |
| **Modul** | 03 · PRODUCTION — submodule Planning (MPS/MRP di GLOBAL_START_HERE, direname konsep di §2) |
| **Status** | ✅ **SEMUA 7 KEPUTUSAN TERJAWAB** (§9.1–9.7) — siap lanjut Tier 2 |
| **Basis** | Diskusi kesulitan riil PPIC (Excel tak sanggup), belum ada mockup/DDL |
| **Pembaca** | Product Owner, Sistem Analis, Developer, DBA |
| **Nama file** | Sementara `production_scheduling_tier1.md` — cek `sys_app_menu.doc_slug` submodule Planning (`PROD_MPS`/`PROD_MRP`) sebelum commit final, ganti nama bila beda |

> ⚠️ **KOREKSI FRAMING — WAJIB DIBACA SEBELUM TIER 2/3**
> Riset awal (chat sebelumnya) mengasumsikan MPS/MRP gaya APICS klasik (forecast-driven, infinite
> capacity netting). Setelah requirement riil dari Telma, itu **framing yang salah** untuk 90%
> kasus TMERP. Masalah sebenarnya adalah **finite-capacity detailed scheduling** ala job-shop
> cetak — domainnya lebih dekat ke SAP PP-DS / Oracle Detailed Scheduling / print-industry MIS
> (EFI Pace, Tharstern, PrintVis) daripada ke MRP tekstbook. Lihat §7.

---

## 1. LATAR BELAKANG

PPIC saat ini kerja pakai Excel dan kesulitan pada hal-hal yang Excel memang tidak dirancang
untuknya:

- Memastikan **tidak ada mesin yang kosong** (idle) dalam suatu window waktu.
- Mengenali **mesin yang jalannya melambat** (degradasi performa) dan mengkompensasi jadwal.
- Menyusun **target kecepatan kerja** ke operator/manual job — harus dalam rentang aman: tidak
  terlalu cepat (risiko kerusakan menumpuk/kualitas turun), tidak terlalu lambat (target kirim
  meleset).
- Menangani **breakdown mesin mendadak** → pekerjaan harus segera dialihkan ke pihak ketiga
  (subkontrak eksternal), lalu hasilnya wajib lolos **QC ketat** sebelum lanjut proses berikutnya.
- Menyerap **interupsi dari Direksi/top management** — pekerjaan mendadak yang harus disisipkan
  dan menggeser antrian yang sudah disusun, tanpa merusak seluruh jadwal.

Karakteristik order:
- **~80% Make-to-Order, short-run** — durasi pengerjaan **1–2 hari kerja per job**, banyak proses
  berurutan (potong→cetak→coating→laminasi→plong→lem→packing, sesuai routing per produk), banyak
  mesin, harus selesai cepat dan presisi. Demand digenerate dari CO confirmed.
- **~20% Make-to-Stock, forecast-driven, short-run juga** — bukan job tanpa arah: replenishment
  ke customer **2-3× per minggu**, forecast disusun **top management/Direksi** (bukan PPIC),
  target akurasi forecast **85-90%**. Lihat §9.1 (terjawab) dan §5A.8 baru.

Ini **bukan** karakter manufaktur assembly long-run (mobil, elektronik) yang jadi basis MRP
klasik. Ini job-shop kecepatan tinggi, siklus pendek, banyak titik keputusan manusia.

---

## 2. RE-FRAMING: BUKAN MPS/MRP KLASIK

| | MPS/MRP klasik (APICS/SAP standar) | Kebutuhan riil TMERP |
|---|---|---|
| Driver | Forecast agregat / demand pooling | Order riil (CO) yang sudah confirmed + spec fixed dari Master Card |
| Horizon | Mingguan/bulanan | Harian, bahkan per jam (1–2 hari kerja) |
| Kapasitas | Infinite (MRP) atau kasar/agregat (RCCP) | **Finite, per mesin, per shift, real-time** |
| Output utama | Planned order (jumlah & tanggal) | **Jadwal presisi**: mesin mana, operator siapa, mulai jam berapa, selesai jam berapa |
| Gangguan | Direplanning batch (overnight run) | **Reschedule dinamis, real-time**, sering di-drag manual oleh PPIC |
| Kontrol kualitas | Di luar scope MRP | **Terintegrasi** — rate band jadi guard kualitas, QC gate wajib per tahap |
| Rute alternatif | Alternate BOM/routing (jarang dipakai) | **Subkontrak eksternal** jadi jalur rutin saat breakdown, bukan pengecualian langka |

**Kesimpulan:** yang perlu dibangun adalah **Production Scheduling Engine** (mesin penjadwalan
detail berbasis kapasitas nyata), dengan **MRP sebagai lapisan tipis di belakangnya** (netting
kebutuhan RM dari Job Order → PR ke Procurement — ini bagian yang memang mirip MRP klasik dan
tetap dibangun, hanya bukan pusat masalah).

Istilah "MPS" di `sys_app_menu` (`PROD_MPS`/`PROD_MRP`) **tetap dipakai sebagai label menu**
(jangan rename menu, sesuai DEC-NAMING), tapi secara konsep, `PROD_MPS` = **Scheduling Board**,
bukan MPS agregat forecast-driven.

> ⚠️ **Nuansa penting (update setelah jawaban §9.1):** kesimpulan di atas **berlaku penuh untuk
> segmen 80% MTO**, tapi **tidak sepenuhnya berlaku untuk segmen ~20% MTS** — MTS TMERP genuinely
> forecast-driven (disusun top management, target akurasi 85-90%, replenish 2-3×/minggu), jadi
> **MPS klasik (forecast vs actual, replenishment cadence) memang relevan di situ**. Solusinya
> **bukan** membangun dua modul terpisah, tapi **memisahkan sumber demand dari mesin eksekusi**:
> - **Demand generation** beda jalur: MTO dari CO confirmed (event-driven), MTS dari forecast
>   Direksi (cadence-driven, lihat §5A.8).
> - **Begitu Job Order lahir** (dari jalur manapun), ia masuk ke **engine eksekusi yang sama**:
>   Backward Scheduling (§5A.3) + Composite Priority Score (§5A.4) + Constraint Rule Library
>   (§5A.7). Tidak ada scheduling engine kedua untuk MTS.

---

## 3. PRINSIP DESAIN

1. **Assisted, bukan fully-automated.** Sistem boleh mengusulkan jadwal awal (auto-suggest), tapi
   PPIC officer selalu punya kendali penuh untuk override manual (drag-drop reassign mesin/waktu).
   Tidak ada "optimizer black-box" yang mengunci keputusan — ini beda filosofi dari APS murni.
2. **Kapasitas finite adalah kebenaran, bukan asumsi.** Tiap mesin punya kalender kerja
   (shift, jam efektif, downtime terjadwal) dan kapasitas real — sistem tak boleh menjadwalkan
   melebihi itu tanpa peringatan eksplisit.
3. **Rate band = guard kualitas sekaligus alat kontrol PPIC.** Tiap kombinasi mesin+proses (atau
   mesin+produk+proses, lihat §9.2) punya rentang kecepatan standar (min–max). Jadwal yang minta
   kecepatan di luar band ditandai sebagai risiko (bukan diblokir — tetap keputusan manusia).
4. **Breakdown → subkontrak adalah jalur normal, bukan anomali.** Beda dari kasus "anomali bisnis"
   yang didokumentasikan-tapi-tak-dimodelkan (pola trust-based order di Sales) — subkontrak akibat
   breakdown **harus jadi state resmi** dalam alur, karena ini rutin terjadi, bukan penyimpangan.
5. **QC ketat sebagai gate wajib, bukan checkbox opsional** — khususnya sesudah barang kembali dari
   subkontrak eksternal (risiko kualitas tertinggi ada di titik ini).
6. **Hot Job (interupsi Direksi) harus tampak jelas & tidak merusak jadwal existing secara diam-diam.**
   Job yang disisipkan mendapat penanda visual khusus (pola sama dengan B11 Inquiry: order
   ditandai warna khusus untuk verifikasi manusia) dan sistem menghitung ulang dampak ke antrian
   lain (siapa mundur, apakah due-date lain masih aman) — tapi **PPIC yang approve eksekusinya**,
   bukan auto-commit.
7. **Idle & slow-machine harus proaktif terlihat**, bukan ditemukan manual dari grid. Dashboard/board
   wajib menyorot mesin kosong dalam window waktu tertentu, dan mesin yang historisnya di bawah
   rate normal.
8. **MRP harus dinamis soal bahan, bukan cuma netting kaku.** Kalau bahan exact (ukuran presisi)
   tidak tersedia/tidak cukup, sistem cari kandidat pengganti dengan kriteria (✅ terjawab §9.4):
   **gramatur sama + kualitas sama, ukuran boleh lebih besar** (jadi Cutting Yield Override, hasil
   potong ke ukuran pas — sisa/waste tercatat, bukan hilang dari pembukuan stok) — **diprioritaskan
   dari **Dead Stock/Slow Moving Stock bahan baku** (sekaligus kosongkan gudang, selaras Prinsip
   #9) — lihat §5A.10 untuk definisi baku & catatan cross-modul ke Inventory.
   Bukan fuzzy-tolerance cross-material — exact-match pada gramatur+kualitas. Usulan tetap perlu
   **approval manusia** (minimal Manager Operasional — §9.4), bukan auto-execute.
9. **Filosofi procurement = lean/JIT, bukan buffer besar.** Karena kapasitas gudang bahan baku
   maupun barang jadi kecil, target sistem adalah **meminimalkan stok tersimpan** — kedatangan
   bahan baku harus diselaraskan presis dengan kapan Job Order itu mulai diproses (bukan datang
   jauh lebih awal dan menumpuk, atau datang telat dan macet). Ini berarti **timeline penuh dari
   Inquiry sampai Purchase Receive harus tersambung** sebagai satu rantai waktu, bukan modul-modul
   terpisah yang masing-masing punya due-date sendiri tanpa saling tahu. Rantai itu:
   Inquiry (harga+urutan proses jelas) → Imposition/Costing → kebutuhan bahan tergambar jelas →
   PR → PO → PO diterima (PV) → **jadwal kedatangan ini yang mengunci kapan Job Order boleh mulai**.
10. **Lead time historis wajib terpantau dan otomatis jadi parameter berikutnya.** Tiap durasi
    riil (PR→PO, PO→SENT, SENT→PV/received, dan nanti task→task di scheduling) dicatat sebagai
    histori. Nilai ini **feed back otomatis** jadi estimasi default lead time untuk perhitungan
    next process/next order — bukan angka lead time statis yang di-hardcode sekali di awal dan
    tak pernah update mengikuti kenyataan lapangan.

---

## 4. ISTILAH BAKU

| Istilah | Definisi |
|---|---|
| **Job Order (JO)** | Satuan kerja produksi. Untuk MTO: diturunkan dari CO yang sudah `submit_to_production` (spec dari Master Card sudah fixed). Untuk MTS: dipicu independen (sumber trigger — lihat §9.1). |
| **Job Operation (Task)** | Instance dari satu baris routing (`m_product_parts_process`) untuk JO tertentu. **Ini unit terkecil yang dijadwalkan** — untuk task mesin: 1 mesin + Crew (Operator Utama + 0..N Co-Operator) + 1 window waktu; untuk task manual: 1..N orang langsung + 1 window waktu. Detail §5A.9. |
| **Resource** | Dua jenis: **Machine Resource** (mesin) dan **Human Resource** (operator/crew/pekerja manual). Task mesin butuh **keduanya** sekaligus (mesin + crew); task manual (non-mesin) hanya butuh Human Resource. Jawaban final §9.2 — lihat detail §5A.9. |
| **Crew** | Susunan orang yang mengerjakan satu Task mesin: **Operator Utama** (wajib, 1 orang, penanggung jawab jalannya mesin) + **Co-Operator** (0..N orang, pendamping/pembantu). Untuk Task manual (bukan mesin), tak ada konsep crew berjenjang — dijadwalkan **per orang langsung**. |
| **Machine Calendar** | Jam kerja efektif per mesin per shift, termasuk downtime terjadwal (preventive maintenance). |
| **Rate Band** | Rentang kecepatan standar (unit/jam atau jam/1000pcs) minimum–maksimum, per kombinasi resource+proses. **Nilai default dihitung dari histori output produk sejenis** (bukan murni angka statis di-hardcode), dengan fallback ke master data manual bila histori belum cukup data — lihat §5A.6. Dasar hitung estimasi durasi task & guard kualitas. |
| **Composite Priority Score** | Skor gabungan (Hot Job → Critical Ratio → EDD → resource fit → FIFO) yang dipakai engine untuk mengurutkan antrean job saat resource kosong — lihat §5A. |
| **Backward Schedule** | Target start/finish per Job Operation, dihitung **mundur** dari due date JO melalui seluruh routing terbalik memakai durasi dari Rate Band. Dasar Critical Ratio per-operasi & pemicu kapan Time-Phased Procurement harus mulai. Lihat §5A.3. |
| **Simulation Layer (Sandbox)** | Ruang kerja "what-if" terpisah dari Master Schedule real — PPIC susun/uji jadwal dulu di sini sebelum submit resmi. Tidak memengaruhi jadwal berjalan sampai di-submit. |
| **Decision Feedback Log** | Catatan tiap keputusan PPIC (opsi mana yang dipilih, dalam kondisi apa) — dipakai untuk analitik pola & penyesuaian bobot rekomendasi berikutnya. |
| **Customer Interruption Profile** | Rekap historis pola interupsi per customer (frekuensi, dampak rata-rata) — sinyal untuk perencanaan buffer kapasitas. |
| **Constraint Rule Library** | Tabel data (bukan kode) berisi pola operasional lapangan — Batching & Sequencing Rule — yang bisa ditambah PPIC/Plant Manager tanpa developer, terikat per resource/mesin. Lihat §5A.7. |
| **Batching Rule** | Aturan yang menggabungkan beberapa Job Operation berbeda produk jadi satu unit terjadwal (mis. dieline sama → gabung plong). |
| **Sequencing Rule** | Aturan yang mengurutkan ulang secara lokal job-job berprioritas setara untuk minimalkan changeover mesin (mis. warna muda dulu, gelap belakangan) — tidak mengalahkan due-date/hot-job. |
| **Breakdown Event** | Catatan insiden mesin rusak mendadak di tengah jadwal berjalan. Trigger opsi reschedule: pindah mesin lain, atau kirim ke **Subcontract**. |
| **Subcontract Operation** | Task yang dialihkan ke pihak ketiga di luar pabrik. Wajib QC gate saat barang kembali sebelum lanjut ke task berikutnya. |
| **Hot Job** | Job/task yang disisipkan mendadak (biasanya oleh Direksi), prioritas di atas antrian normal, ditandai visual khusus di board. |
| **Scheduling Board** | Tampilan utama PPIC: Gantt per mesin (baris = mesin, kolom = waktu), drag-drop reassign, kode warna per status. |
| **Idle Guard** | Mekanisme highlight mesin yang tidak punya task terjadwal dalam window mendatang. |
| **Slow Machine Flag** | Penanda mesin yang performanya historis di bawah rate normal — dipakai PPIC untuk kasih buffer waktu ekstra saat menjadwalkan. |
| **Material Substitution / Cutting Yield** | **Satu mekanisme (✅ terjawab §9.4, bukan dua konsep terpisah)**: cari stok dengan gramatur+kualitas sama, ukuran lebih besar dari kebutuhan, prioritas ke **Dead Stock/Slow Moving Stock** — dipotong ke ukuran pas. Waste hasil potong tercatat, bukan hilang dari pembukuan. Lihat §5A.10. |
| **Dead Stock** | Item bahan baku **tanpa pergerakan keluar sama sekali** selama periode ambang tertentu (angka pasti Tier 2, mis. >180 hari) — dihitung dari `doc_date` transaksi OUT terakhir di kartu stok (`rm_stockcard`, sudah ada di Inventory, tak perlu tabel baru). |
| **Slow Moving Stock** | Item bahan baku yang **masih bergerak, tapi jauh di bawah kecepatan normal** (turnover ratio di bawah ambang, angka pasti Tier 2) — beda dari Dead Stock yang pergerakannya nol. |
| **Time-Phased Procurement** | Jadwal kedatangan bahan baku (PR→PO→PV) diselaraskan presisi dengan tanggal mulai Job Order — prinsip lean, minim stok tersimpan. |
| **Lead Time Historis (Rolling)** | Durasi riil tiap tahap rantai (PR→PO→PV, dan task→task produksi) tercatat & otomatis jadi parameter estimasi default berikutnya. **Granularitas hybrid (✅ terjawab §9.5)**: per supplier+material bila data cukup, fallback ke per-material saja bila belum. |
| **Production Output Recording** | Pencatatan hasil aktual per Job Operation — baik dari mesin maupun manual job (mis. packing) — qty baik/reject/waste per shift/task. |

### 4.1 Referensi Master Card Riil (BOM + BOP gabungan)

Dari contoh fisik Master Card (attachment Telma) — ini konfirmasi struktur `m_product_parts_material`
+ `m_product_parts_process` yang sudah ada di Sales, sekaligus daftar field yang **PPIC harus paham**
karena jadi input scheduling:

- **Header identitas**: Kode Item, No. Desain, Nama Barang, Nama Pelanggan, Tanggal.
- **Spesifikasi fisik**: Dimensi P/L/T, Material (jenis+kualitas/gsm), Ukuran Bahan (roll/sheet),
  Ukuran Cetak, Jenis Finishing (mis. Varnish/WB), Kode & Ukuran Pisau (dieline/cutting die).
- **Layout**: gambar bentuk impose, Bentuk Sambungan (join pattern), Isi dalam Satu Cetakan (up-count).
- **Warna per unit cetak** — Warna Pertama & Warna Kedua, **per unit mesin (Unit 1–4)** — ini detail
  granular yang **belum tentu ter-capture penuh** di skema Sales saat ini; catat sebagai potensi
  delta requirement ke Master Card generation (domain Sales, bukan modul ini — lihat catatan
  cross-modul di bawah).
- **Urutan Pekerjaan (routing)** — ditulis literal termasuk **nama mesin utama** (contoh nyata:
  "Cetak di mesin: Lithrone G40"). Artinya **mesin cetak utama sudah ditentukan di level Master
  Card** (keputusan desain/produksi awal), sementara **modul Scheduling (ini) tetap yang menjadwalkan
  presisi waktu + mesin-mesin proses lain (coating/laminasi/plong/dst) + operator + urutan antre**.
  Jangan disamakan — Master Card = *apa & di mesin jenis apa*, Scheduling = *kapan & antrean di
  mana persisnya*.
- **Approval chain**: Designer, Marketing, PPIC, QA, Plant Manager — konfirmasi PPIC memang salah
  satu pemberi tanda tangan resmi di Master Card, bukan cuma konsumen pasif.

> ⚠️ **Catatan cross-modul**: kebutuhan detail lebih jauh (warna/tinta spesifik, **jenis lem** yang
> dipakai di tiap titik lem) adalah delta ke **Master Card generation di Sales**, bukan tabel baru
> di modul Production. Modul ini (Scheduling) **mengonsumsi** data itu, tidak memilikinya. Catat
> kebutuhan ini ke `SALES_STATE.yaml` / dibahas terpisah saat delta Master Card digarap — jangan
> didesain dobel di sini.

---

## 5. CAKUPAN FITUR (turunan, bukan final — detail di Tier 2)

- **F1 — Job Order Generation**: JO otomatis tergenerate dari CO confirmed (MTO) atau trigger MTS
  (§9.1); routing/task pecah otomatis dari `m_product_parts_process` milik produk tsb.
- **F2 — Auto-Suggest Schedule (Priority Dispatching Rules)**: sistem usulkan alokasi awal
  (mesin+waktu) berbasis Composite Priority Score — **detail mekanisme di §5A**. Hanya usulan,
  bukan commit langsung.
- **F3 — Scheduling Board (manual)**: Gantt drag-drop per mesin, PPIC bisa reassign/reschedule
  kapan saja.
- **F4 — Breakdown & Subcontract Flow**: catat breakdown event → pilih reroute (mesin lain /
  subkontrak) → jika subkontrak: due-date pengiriman & kembali, **wajib generate PO tipe
  `SUBCONTRACT`** ke Procurement (eks-§9.3, terjawab) → QC gate wajib saat kembali.
- **F5 — QC Gate per Tahap**: checkpoint QC di titik kritis routing (bukan cuma di akhir), wajib
  lebih ketat pasca-subkontrak.
- **F6 — Hot Job / Interruption Insert (multi-opsi)**: Direksi/PPIC/customer urgent bisa sisipkan
  job prioritas tinggi ke Master Schedule **live**; sistem hasilkan **beberapa opsi penyisipan**
  (bukan 1 jawaban tunggal) lengkap dengan dampak terukur per opsi — PPIC pilih satu, sistem commit
  sesuai pilihan. Detail di §5A.2.
- **F7 — Rate Band Monitoring**: alert bila jadwal/actual output di luar rentang rate standar.
- **F8 — Idle Machine Alert**: sorot mesin kosong dalam window X jam ke depan.
- **F9 — Slow Machine Flag**: penanda mesin performa historis di bawah standar.
- **F10 — MRP Netting (lapisan tipis)**: dari JO → hitung gross requirement RM per layer material →
  net terhadap stok Inventory → generate PR draft ke Procurement. Lot sizing: lot-for-lot saja
  (v1, sesuai sifat MTO — lihat riset sebelumnya).
- **F11 — Material Substitution / Cutting Yield Engine** (F11+F12 digabung — ✅ terjawab §9.4:
  keduanya satu mekanisme): saat netting menemukan bahan exact tak cukup, cari kandidat
  **gramatur+kualitas sama, ukuran ≥ kebutuhan**, urutkan prioritas **Dead Stock → Slow Moving
  Stock → stok normal** (§5A.10) → usulkan potong ke ukuran pas, hitung & catat waste yang
  dihasilkan (masuk pembukuan, bukan hilang) → tampil sebagai opsi, approval minimal Manager
  Operasional (§9.4).
- **F13 — Time-Phased Procurement Scheduling**: hitung mundur dari tanggal mulai Job Order → kapan
  PR harus dibuat, kapan PO harus dikirim, kapan barang harus tiba (pakai Lead Time Historis F14
  sebagai basis hitung) — target stok RM minimal, selaras kapasitas gudang kecil.
- **F14 — Lead Time Historical Tracking**: catat durasi riil tiap tahap rantai (PR→PO, PO→SENT,
  SENT→PV, dan task→task produksi); jadi basis rolling-average yang otomatis update estimasi
  default berikutnya (bukan angka statis).
- **F15 — Production Output Monitoring**: pencatatan hasil aktual per Job Operation — termasuk
  **manual job non-mesin** (packing, dst) — qty baik/reject/waste, dibandingkan terhadap target
  (rate band) untuk evaluasi & penyesuaian jadwal berikutnya.
- **F16 — Simulation Mode (Sandbox)**: PPIC tarik CO confirmed+valid (qty & duedate fix dari Sales)
  ke ruang simulasi, engine usulkan jadwal (PDR), PPIC review & adjust, baru **submit eksplisit**
  ke Master Schedule real. Tidak menyentuh jadwal live sampai di-submit. Detail §5A.1.
- **F17 — Exception Dashboard (real-time)**: sorot otomatis job yang **belum terjadwal**, task yang
  **berisiko telat** (Critical Ratio < 1), dan resource idle/slow — PPIC tak perlu scan manual satu
  per satu. Ini kebutuhan kecepatan eksplisit dari Telma: PPIC tidak sanggup memindai ini dalam
  hitungan detik/menit tanpa bantuan.
- **F18 — Decision Feedback Loop**: tiap pilihan PPIC atas opsi yang diusulkan engine (F6, F16)
  dicatat sebagai `Decision Feedback Log` — dipakai untuk analitik pola & penyesuaian bobot
  rekomendasi berikutnya (rule-based, bukan black-box ML — lihat §5A.5).
- **F19 — Customer Interruption Pattern Tracking**: rekap historis interupsi per customer (siapa,
  seberapa sering, dampak rata-rata) sebagai `Customer Interruption Profile` — sinyal untuk
  perencanaan buffer kapasitas, bukan pengambil keputusan otomatis atas SLA/kontrak.
- **F20 — MTS Forecast & Replenishment**: input forecast periodik oleh Direksi/top management per
  item MTS, banding forecast vs actual, Forecast Accuracy Tracking (target 85-90%), trigger Job
  Order replenishment (cadence 2-3×/minggu) — detail §5A.8.

---

## 5A. ENGINE PENJADWALAN: PRIORITY DISPATCHING RULES (PDR)

> Menjawab pertanyaan Telma soal algoritma: **Genetic Algorithm (GA) DITOLAK** untuk mesin
> penjadwalan ini. GA adalah metaheuristik batch/offline yang butuh banyak iterasi untuk konvergen,
> hasilnya black-box (sulit dijelaskan "kenapa job ini duluan"), dan tidak natural untuk reschedule
> parsial saat disrupsi — tiga hal ini bertentangan langsung dengan Prinsip #1 (tidak ada
> optimizer black-box) dan kebutuhan respons real-time PPIC. **Priority Dispatching Rules (PDR)**
> dipilih sebagai gantinya: cepat (milidetik), transparan, gampang dijelaskan, dan alami untuk
> local-repair saat ada gangguan. GA baru masuk akal untuk masalah lain yang statis (gang-run
> nesting — lihat `sales_imposition_enhancement_backlog.md` §2.3), bukan untuk scheduling dinamis.

### 5A.1 Alur kerja PPIC: Simulasi → Submit → Master Schedule Real

Ini prinsip inti yang diminta Telma — **PPIC tidak langsung menjadwalkan ke jadwal live**, tapi
lewat ruang simulasi dulu:

```
CO CONFIRMED (qty & duedate valid dari Sales)
        │
        ▼
[1] Tarik ke SIMULATION LAYER (sandbox, tak sentuh jadwal real)
        │
        ▼
[2] Engine jalankan PDR → usulan jadwal (draft Gantt) di sandbox
        │
        ▼
[3] PPIC review — exception dashboard (F17) langsung tampil:
    job belum terjadwal? job berisiko telat? resource idle?
        │
        ├── Belum cocok → PPIC adjust manual (drag-drop) di sandbox → re-run [2]
        │
        └── Cocok → [4] SUBMIT ke Master Schedule REAL
                     (validasi ulang: pastikan tak ada breakdown/hot-job baru
                      yang masuk selagi simulasi berjalan — kalau ada, re-sync
                      dulu sebelum commit final)
```

**Kenapa dipisah dua layer (simulasi vs real):** supaya PPIC bisa bereksperimen bebas tanpa risiko
mengacaukan jadwal yang sudah berjalan dan sudah dikomunikasikan ke operator/mesin. Master Schedule
real **hanya berubah** lewat submit eksplisit ini, atau lewat alur interupsi §5A.2 (yang memang
menyasar jadwal live secara langsung karena sifatnya darurat).

### 5A.2 Alur Interupsi/Hot Job: Multi-Opsi, Bukan Auto-Silent-Change

Beda dari alur simulasi (untuk job baru batch), interupsi menyasar **jadwal yang sudah berjalan
live**. Prinsip: **engine menghitung, PPIC memutuskan** — karena PPIC tak sanggup menghitung
dampak dalam hitungan detik, tapi keputusan akhir tetap harus manusia (Prinsip #6).

```
Interupsi masuk (Direksi / customer urgent / internal rework)
        │
        ▼
Engine cari slot penyisipan → hitung dampak tiap kemungkinan → generate opsi
        │
        ├── Opsi 1: sisip di slot X, geser Job Y
        │           → dampak: Y mundur N jam, masih aman dari due-date
        │
        ├── Opsi 2: sisip di slot X, geser Job Z
        │           → dampak: Z BERISIKO TELAT (dampak dihitung eksplisit, mis. "+M jam
        │             dari due-date Z" — bukan sekadar label "risiko")
        │
        └── Opsi 3: reroute ke resource alternatif / subkontrak
                    → dampak: +biaya subkontrak, tapi tidak ada job lain tergeser
        │
        ▼
PPIC pilih SATU opsi → engine commit ke Master Schedule real sesuai pilihan
        │
        ▼
Keputusan tercatat ke Decision Feedback Log (§5A.5)
```

Jumlah opsi **tidak selalu 3** — hanya opsi yang valid secara constraint (kapasitas, urutan wajib
routing, rate band) yang ditampilkan. Kalau cuma ada 1 jalan valid, tampil 1 opsi saja (bukan
dipaksa mengarang opsi tambahan).

### 5A.3 Backward Scheduling — Mundur dari Due Date per Kemampuan Tiap Proses

Ini jawaban atas requirement Telma: penjadwalan **tidak dihitung maju dari "sekarang"**, tapi
**mundur dari due date**, memakai kemampuan/target tiap proses (Rate Band, §5A.6) untuk menentukan
kapan tiap Job Operation harus mulai & selesai.

**Cara kerja:**

```
JO Due Date (tanggal kirim yang disepakati)
        │
        ▼  mundur, langkah demi langkah, melalui routing terbalik
Task terakhir (mis. PACKING)  → finish = Due Date (dikurangi buffer kirim bila ada)
                               → start  = finish − durasi(PACKING, dari Rate Band)
        │
        ▼
Task sebelumnya (mis. KUPAS)  → finish = start Task berikutnya (tanpa jeda, atau dikurangi
                                 buffer handoff bila didefinisikan)
                               → start  = finish − durasi(KUPAS, dari Rate Band)
        │
        ▼
        ... berlanjut mundur melalui seluruh routing (LEM, PLONG, LAMINASI, COATING, CETAK,
            BELAH PLANO) ...
        │
        ▼
Task pertama (BELAH PLANO)    → start = **titik ini menentukan "produksi harus mulai kapan"**
```

**Perhitungan durasi tiap task:** `durasi = qty ÷ Rate Band(resource, proses)` — dan Rate Band
sendiri nilainya historis (§5A.6), bukan statis, jadi hasil backward schedule ikut makin akurat
seiring data historis bertambah. Perhitungan **wajib menghormati Machine Calendar** (§4) — jam
non-kerja/shift kosong dilompati, bukan dianggap tersedia.

**Kegunaan output backward schedule (dua arah):**
1. **Ke dalam scheduling** — tiap Job Operation punya *target start* & *target finish* sendiri
   (bukan cuma satu due date besar di level JO). Ini menyempurnakan **Critical Ratio** di §5A.4
   Lapis 1 — CR bisa dihitung **per-operation** memakai target dari backward schedule ini, bukan
   cuma dari due date JO secara agregat. Operasi yang mundur dari target-nya sendiri (meski JO
   secara keseluruhan masih terlihat "aman") langsung kelihatan sebagai risiko lebih dini.
2. **Ke luar scheduling, ke Procurement** — titik start Task pertama = kapan bahan baku **wajib
   sudah ada di tangan**. Ini yang menjalankan Prinsip #9 (Time-Phased Procurement) — tanggal ini
   yang mengunci kapan PR/PO harus diproses mundur lagi lewat Lead Time Historis (§4), bukan
   dikira-kira manual oleh PPIC.

**Catatan penting:** backward schedule ini **dihitung ulang** tiap kali due date berubah, tiap kali
ada interupsi (§5A.2) yang menggeser suatu Task, atau tiap kali Rate Band ter-update dari data
baru — bukan dihitung sekali lalu dianggap tetap. Karena berbasis aritmetika sederhana (bukan
optimasi iteratif), ini tetap cepat/real-time, konsisten kebutuhan Telma bahwa PPIC tak bisa
menunggu proses lambat.

---

### 5A.4 Composite Priority Score (mesin di balik PDR)

Saat resource (mesin) kosong dan ada beberapa task menunggu, engine urutkan pakai skor berlapis
(bukan satu formula tunggal — supaya tiap lapis bisa dijelaskan terpisah ke PPIC):

| Lapis | Aturan | Penjelasan |
|---|---|---|
| **0 — Hot Job Override** | Task berlabel HOT selalu di atas antrean normal | Interupsi Direksi/customer urgent tidak menunggu giliran biasa |
| **1 — Critical Ratio (CR)** | `CR = (target_finish_operasi − sekarang) / sisa_waktu_proses` — `target_finish_operasi` diambil dari **Backward Schedule per-operation** (§5A.3), bukan due date JO secara agregat | CR < 1 = operasi ini **sudah** berisiko telat meski tak ada gangguan tambahan lagi — prioritas tertinggi di luar hot job. Karena target per-operasi (bukan cuma due date akhir), risiko kelihatan lebih dini |
| **2 — Earliest Due Date (EDD)** | Due-date terdekat menang saat CR setara | Tie-break utama untuk short-run 1–2 hari |
| **3 — Resource/Rate Fit** | Hindari assign ke `Slow Machine Flag` kalau resource lebih cepat tersedia dalam window wajar; **cek juga ketersediaan Crew** (Operator Utama + Co-Operator), bukan cuma mesin — detail §5A.9 | Cegah pemborosan waktu ke mesin yang diketahui lambat, dan cegah bentrok penjadwalan orang yang sama di dua mesin sekaligus |
| **4 — FIFO** | Confirmed_at terlama menang | Tie-break terakhir, adil untuk sisa kasus |

**Kalau ada konflik antar hot job** (dua interupsi bersaing) → tie-break turun ke Lapis 1 (CR) di
antara sesama hot job — tidak ada aturan "hot job kedua" yang otomatis kalah tanpa dasar.

Skor ini **dijalankan ulang tiap kali ada trigger**: resource selesai/kosong, job baru masuk,
interupsi terjadi, atau PPIC minta re-run manual di sandbox. Karena berbasis aturan sederhana
(bukan iterasi optimasi), hitung ulang ini **cepat** (bisa real-time), cocok untuk kebutuhan
Telma bahwa PPIC tidak bisa menunggu proses lambat.

### 5A.5 Decision Feedback Loop — Adaptif tapi Tetap Bisa Dijelaskan

Setiap keputusan PPIC (pilih opsi mana di §5A.2, atau adjust manual di sandbox §5A.1) dicatat ke
`Decision Feedback Log`: kondisi input (customer siapa, jenis interupsi, resource terlibat, job
yang dikorbankan) + opsi yang dipilih + opsi yang tidak dipilih.

Dipakai untuk **dua hal yang berbeda tujuannya** — jangan dicampur:

1. **Analitik pola (pasif, laporan)** — contoh: "Customer X memicu interupsi 4×/bulan, PPIC 80%
   pilih 'geser job lain' vs 20% 'reroute subkontrak'". Ini jadi masukan untuk perencanaan buffer
   kapasitas & evaluasi SLA customer (lihat `Customer Interruption Profile`, F19) — actionnya
   manual oleh PPIC/manajemen, sistem hanya menyajikan data.
2. **Penyesuaian bobot rekomendasi (aktif, mengubah urutan opsi default)** — kalau pola pilihan
   PPIC konsisten condong ke satu jenis opsi dalam kondisi serupa, engine **menyesuaikan urutan
   tampil opsi** (opsi yang biasanya dipilih PPIC ditaruh sebagai rekomendasi utama/opsi 1) —
   tapi ini **tetap penyesuaian bobot rule yang bisa dilihat & di-reset**, bukan model machine
   learning opaque. PPIC/Plant Manager harus bisa lihat "kenapa urutan opsi berubah" dan bisa
   kembalikan ke default kapan saja. Ini konsisten dengan Prinsip #1 — kalau model
   pembelajarannya tak bisa dijelaskan, itu melanggar prinsip inti, sekalipun hasilnya "lebih
   akurat".
   > Frekuensi/mekanisme penyesuaian bobot ini — otomatis terus-menerus, atau kalibrasi berkala
   > (mis. mingguan, direview PPIC/Plant Manager sebelum diterapkan)? **Lihat §9.6, masih terbuka.**

### 5A.6 Rate Band & Lead Time dari Data Historis (bukan statis)

Selaras Prinsip #10 (lead time historis) — Rate Band (§4) juga mengikuti pola sama: nilai
default kecepatan standar per resource+proses **dihitung dari histori output produk sejenis**
(rolling average dari `Production Output Recording`, F15), bukan angka master yang di-hardcode
sekali di awal. Kriteria "produk sejenis" & jumlah data minimum sebelum histori dianggap valid
dipakai — sama pertanyaannya dengan lead time di §9.5, dijawab bersamaan.

### 5A.7 Constraint Rule Library — Pola Batching & Sequencing sebagai DATA

Selain Composite Priority Score (§5A.4 — soal *urgency*, "job mana duluan"), ada kelas aturan
berbeda: pola operasional dari pengalaman lapangan tentang *bagaimana mengelompokkan/mengurutkan*
pekerjaan untuk efisiensi mesin — dua contoh dari Telma:
- **Batching**: produk berbeda tapi pisau pond (dieline) sama → digabung jadi satu batch plong,
  hemat ganti-alat.
- **Sequencing**: di mesin cetak, warna muda didahulukan, warna gelap belakangan — meminimalkan
  waktu/biaya cuci unit tinta antar-job.

**Kenapa ini TIDAK BOLEH di-hardcode:** Telma sendiri menegaskan pola ini **akan bertambah** dan
**berubah seiring pergantian mesin** (mesin baru punya karakter beda, mesin lama pensiun sama
aturannya). Kalau ditulis sebagai kode program, tiap pola baru = perlu developer + deploy ulang —
lambat dan menumpuk technical debt. **Solusinya: Rule Library** — tabel data yang PPIC/Plant
Manager bisa tambah/nonaktifkan sendiri, engine baca rule dari situ saat runtime. Ini pola yang
sama persis dengan `dieline_spec` DSL yang sudah berjalan di Sales (rule = data, bukan program).

**Dua kategori rule, beda titik kerja di alur PDR:**

| Kategori | Kapan dijalankan | Contoh | Efek |
|---|---|---|---|
| **Batching Rule** | **Pre-processing**, sebelum Composite Priority Score jalan | "Dieline sama → gabung 1 batch plong" | Beberapa Job Operation berbeda produk digabung jadi **1 unit terjadwal** untuk resource tsb (hemat setup) |
| **Sequencing Rule** | **Tie-break lokal**, di dalam antrean resource yang sama (mengisi celah antar-lapis §5A.4, tak mengalahkan due-date/hot-job) | "Warna muda dulu, gelap belakangan" | Job-job dengan prioritas setara (CR/EDD mirip) diurutkan ulang secara lokal untuk minimalkan changeover — **tidak** boleh menggeser job yang memang harus duluan karena due-date/hot-job |

**Struktur data rule (konsep, detail kolom di Tier 2):**
- `rule_type`: BATCHING atau SEQUENCING (extensible — bisa nambah kategori baru nanti tanpa ubah
  struktur inti).
- `applies_to`: scope mesin — bisa per mesin spesifik, per tipe mesin, atau per proses. **Inilah
  mekanisme "seirama pergantian mesin"**: rule terikat ke `resource_id`/`resource_type`, bukan
  global. Mesin baru masuk → rule lama otomatis tak berlaku sampai didefinisikan ulang untuk mesin
  itu; mesin pensiun → rule-nya tinggal dinonaktifkan, bukan dihapus (jejak historis tetap ada).
- `condition`: atribut yang dibandingkan (mis. `dieline_ref` untuk batching; `ink_darkness_level`
  atau kode warna terurut untuk sequencing) — didekati seperti expression sederhana (bukan
  se-kompleks evaluator dieline DSL, karena umumnya cukup "field sama → group" atau "urutkan
  berdasarkan field X ascending/descending").
- `action`: GROUP (untuk batching) atau SORT ascending/descending pada field tertentu (untuk
  sequencing).
- `status` + `effective_from`/`effective_to`: siklus hidup rule — aktif/nonaktif, kapan mulai
  berlaku, kapan berhenti (jejak historis terjaga, konsisten prinsip audit trail yang sudah
  dipakai di modul lain).
- `created_by`/`approved_by`: siapa yang menambah rule baru — lihat §9.7, masih terbuka.

**Prinsip kerja engine:** saat menyusun jadwal (baik di Simulation Layer §5A.1 maupun re-run saat
interupsi §5A.2), urutan proses jadi:
1. Ambil Job Operation yang menunggu untuk resource tertentu.
2. **Terapkan Batching Rule yang aktif untuk resource itu** → gabungkan yang memenuhi kondisi jadi
   batch.
3. Hitung Composite Priority Score (§5A.4) per batch/job.
4. **Terapkan Sequencing Rule sebagai penghalus urutan** di antara item-item dengan skor setara —
   tanpa melanggar hasil Lapis 0–2 (hot job, CR, EDD tetap jadi batas atas prioritas).
5. Hasil akhir tampil sebagai usulan — PPIC tetap bisa override manual seperti biasa (Prinsip #1).

**Seed contoh rule (bukan daftar final, ilustrasi saja):**
- BATCHING: `dieline_ref` sama → group (plong).
- BATCHING: bahan+gramatur+jumlah warna sama → group kandidat gang-run (nyambung ke ide
  `sales_imposition_enhancement_backlog.md` §2.3).
- SEQUENCING: `ink_darkness_level` ascending (muda→gelap) per mesin cetak.
- SEQUENCING (contoh potensi ke depan, belum diverifikasi Telma): jenis coating sama dikelompokkan
  berurutan untuk hindari ganti tangki.

---

### 5A.8 MTS Forecast & Replenishment — Sumber Demand Kedua

Melengkapi §2 (nuansa hybrid): begini cara segmen ~20% MTS **menghasilkan Job Order**, terpisah
dari jalur MTO (CO confirmed) tapi **bermuara ke engine eksekusi yang sama**.

**Alur:**
```
Top Management/Direksi susun FORECAST periodik per item MTS
        │  (cadence: selaras replenishment 2-3×/minggu — bukan bulanan ala MPS klasik)
        ▼
Forecast vs Actual (pengiriman riil) dibandingkan tiap siklus
        │
        ▼
Forecast Accuracy Tracking — target 85-90%. Di bawah target = sinyal ke Direksi untuk
        │  koreksi angka forecast periode berikutnya (bukan otomatis diubah sistem)
        ▼
Replenishment trigger (tiap cadence, atau saat stok FG MTS mendekati safety level)
        │
        ▼
Job Order (MTS) tergenerate → masuk ke engine eksekusi yang SAMA dengan MTO:
Backward Scheduling (§5A.3) → Composite Priority Score (§5A.4) → Constraint Rule Library (§5A.7)
```

**Prinsip kunci:**
- **Forecast adalah input manusia (Direksi), bukan hasil algoritma sistem.** Sistem hanya
  menyimpan, membandingkan ke actual, dan menghitung akurasi — tidak "meramal" sendiri. Ini
  konsisten Prinsip #1 (tidak ada black-box) sekaligus realistis: forecasting bisnis (channel,
  musim, hubungan customer) adalah keputusan komersial di luar apa yang bisa dihitung sistem dari
  data internal saja.
- **Begitu Job Order MTS lahir, ia setara Job Order MTO** — tidak ada perlakuan scheduling
  berbeda di lantai produksi. Bedanya hanya di **hulu** (asal permintaan), bukan di **hilir**
  (cara dijadwalkan/dieksekusi).
- **Safety stock/level replenishment MTS** perlu didefinisikan (per item, berapa hari/berapa
  siklus buffer) — detail angka masuk Tier 2, tapi prinsipnya sudah selaras Prinsip #9
  (lean/JIT, minim stok tersimpan) — MTS bukan alasan untuk kembali ke pola stok besar.

> Detail mekanisme input forecast (form/cadence review, siapa approve angka final, bagaimana
> safety stock dihitung per item) — **masuk Tier 2**, bukan diputuskan di Tier 1 ini. Yang
> dikunci di sini hanya: forecast dari Direksi, akurasi target 85-90%, dan Job Order MTS
> bermuara ke engine eksekusi yang sama dengan MTO.

---

### 5A.9 Resource Ganda — Mesin + Crew (jawaban §9.2)

**Koreksi dari saran awal saya** (yang menyarankan "mesin saja, operator jadi atribut") — jawaban
Telma lebih presisi dan itu yang dipakai: **task mesin butuh dua dimensi resource sekaligus**,
bukan satu.

**Dua jenis resource:**

| Jenis Task | Resource dibutuhkan | Struktur |
|---|---|---|
| **Task Mesin** (cetak, coating, laminasi, plong, dst) | Machine Resource **+** Crew | Crew = **Operator Utama** (wajib, 1) + **Co-Operator** (0..N) |
| **Task Manual** (packing, kupas, dst — tanpa mesin) | Human Resource langsung | **Per orang** — dijadwalkan individual, tanpa struktur crew berjenjang |

**Konsekuensi ke Scheduling Board & konflik kapasitas:**
- Satu Task mesin **dianggap terjadwal sah** hanya kalau **mesin DAN crew-nya** sama-sama available
  di window waktu itu — bukan cukup mesin kosong saja.
- **Operator/co-operator adalah resource yang bisa bentrok lintas-mesin** — orang yang sama tak
  boleh jadi Operator Utama di dua mesin berbeda pada jam yang sama (deteksi konflik ganda: cek
  ketersediaan mesin **dan** ketersediaan tiap orang di crew).
- **Composite Priority Score Lapis 3 (§5A.4, "Resource/Rate Fit")** diperluas: bukan cuma cek mesin
  mana yang tercepat tersedia, tapi juga **crew mana yang tersedia & sesuai** (mis. operator
  tersertifikasi untuk mesin tsb — kalau sertifikasi per-operator per-mesin memang ada, dicatat
  sebagai atribut Human Resource, bukan aturan terpisah).
- **Rate Band (§4, §5A.6)** tetap terikat kombinasi resource+proses — histori kecepatan idealnya
  **memperhitungkan siapa crew-nya** juga (operator senior vs baru bisa beda rate riil), tapi ini
  detail granularitas Tier 2, jangan diputuskan sekarang supaya tak kompleks prematur.

**Bukan bagian dari resource scheduling ini** (pengingat, konsisten §6): sertifikasi/skill-matrix
operator penuh, dan payroll — kedua itu domain HR/Attendance, modul ini hanya konsumen status
"operator tersedia + tersertifikasi untuk resource X" sebagai fakta, bukan pengelola datanya.

---

### 5A.10 Dead Stock & Slow Moving Stock — Kriteria Prioritas Cutting Yield (jawaban lanjutan §9.4)

Melengkapi §9.4: "stok paling lama idle" dipertegas Telma **selaras istilah standar inventory**,
bukan istilah ad-hoc — dipakai definisi baku:

- **Dead Stock**: item **tanpa pergerakan keluar sama sekali** selama periode ambang tertentu.
- **Slow Moving Stock**: item **masih bergerak, tapi jauh di bawah kecepatan normal** (turnover
  rendah) — beda dari Dead Stock yang pergerakannya benar-benar nol.

**Urutan prioritas kandidat Cutting Yield**: Dead Stock dulu → baru Slow Moving Stock → baru stok
normal (kalau memang tak ada Dead/Slow Moving yang gramatur+kualitas+ukuran cocok).

**Sumber data — TIDAK PERLU tabel baru.** Kartu stok (`rm_stockcard`) sudah menyimpan riwayat
lengkap tiap pergerakan RM. Klasifikasi Dead/Slow Moving bisa dihitung dari `MAX(doc_date)` transaksi
OUT terakhir per item (view baca, konsisten pola `v_rm_available`/`v_rm_onhand` yang sudah ada di
Inventory) — bukan kolom tersimpan yang perlu dihitung ulang manual.

> ⚠️ **Catatan cross-modul — perlu konfirmasi Telma, bukan keputusan final saya:**
> 1. **Beda tujuan dari "penyisihan barang usang"** yang eksplisit di luar scope fase ini di
>    `inventory_tier1.md` §11 — itu untuk **write-down nilai aset** (kebutuhan Accounting).
>    Klasifikasi Dead/Slow Moving di sini untuk **prioritas reuse operasional** (kebutuhan
>    Production Scheduling) — **tujuan beda, tapi bisa pakai satu komputasi aging yang sama**
>    di Inventory, dikonsumsi dua konteks berbeda. Jangan dibangun dua kali.
> 2. **Berpotensi tumpang tindih dengan baris existing di `inventory_tier1.md` §7**: *"Staff PPIC
>    ... Menyetujui substitusi material pada Stock Out."* — ini beda dari keputusan §9.4 baru
>    (approval minimal Manager Operasional). **Dugaan saya** (perlu dikonfirmasi, bukan fakta):
>    ini dua titik keputusan beda — **perencanaan** (MRP netting memutuskan *akan* substitusi/
>    cutting-yield → Manager Operasional+, keputusan §9.4) vs **eksekusi** (Stock Out fisik
>    mengeluarkan barang sesuai rencana yang *sudah* disetujui → PPIC cukup konfirmasi, bukan
>    approval ulang). Kalau dugaan ini salah, `inventory_tier1.md` §7 perlu direvisi supaya
>    konsisten dengan Tier 1 ini.
>
> **Delta requirement ke Inventory** (dicatat di sini, dieksekusi saat Inventory Tier 2/3
> digarap, bukan di modul ini): view aging/klasifikasi Dead Stock/Slow Moving per item RM,
> threshold hari & turnover ratio ditentukan bersama (kemungkinan Manager Operasional/Direksi).

---

## 6. YANG BUKAN SCOPE MODUL INI

- **Perencanaan kapasitas jangka panjang / investasi mesin** — itu keputusan strategis, bukan
  fitur operasional harian.
- **Payroll/insentif operator berbasis kecepatan** — rate band di sini murni alat *scheduling &
  quality guard*, bukan dasar hitung gaji (itu domain Payroll, di luar scope modul ini, jangan
  dicampur).
- **Preventive maintenance scheduling penuh** (submodule `PROD_MTN` terpisah di peta modul) — modul
  ini hanya *konsumen* info downtime terjadwal dari situ, bukan yang mengelolanya.

---

## 7. REFERENSI ERP PEMBANDING

| Sistem | Relevansi |
|---|---|
| **SAP PP-DS** (embedded di S/4HANA, dulu APO) | Paling dekat secara konsep: planning board grafis, finite scheduling per resource, optimizer *bisa dimatikan* untuk full manual reschedule, **external processing operation** di routing = pola persis untuk subkontrak breakdown. |
| **Oracle Advanced Supply Chain Planning — Production Scheduling** | Gantt berbasis resource, exception-driven, pegging network penuh. Kedalaman logic bagus, UI berat. |
| **Dynamics 365 SCM — Master Planning** | Planning worksheet interaktif (grid, bukan Gantt) — filosofi "propose lalu manusia approve" mirip yang kita mau, tapi kurang kuat di sisi *visual per-mesin real-time*. |
| **Odoo MRP** | **Tidak cocok** sebagai referensi utama — Odoo standar reaktif (reorder rules), tak punya finite scheduling board yang memadai untuk job-shop kecepatan tinggi. |
| **Print-industry MIS** (EFI Pace, Tharstern, PrintVis, Avanti Slingshot) — *bukan ERP generik, tapi solusi spesifik industri cetak* | **Referensi paling relevan secara karakter bisnis**: job-based scheduling board per device/mesin, hot job insert, subcontract tracking bawaan, QC checkpoint per stage. Generic ERP (SAP/Oracle) solve masalah ini via modul tambahan mahal (PP-DS/APO); software print-MIS solve ini sebagai fitur inti karena memang didesain untuk job-shop cetak short-run. **Rekomendasi: pelajari pola UX-nya (board per mesin, warna status, drag-drop) sebagai acuan desain layar**, bukan struktur datanya (biasanya proprietary/tak terdokumentasi publik).

---

## 8. KEPUTUSAN TERKUNCI (dari diskusi ini)

- Engine ini **assisted-scheduling**, bukan full-auto optimizer — manusia (PPIC) selalu final
  authority.
- Kapasitas dimodelkan **finite per mesin/shift**, bukan infinite ala MRP klasik.
- **Subkontrak akibat breakdown = state resmi dalam alur**, bukan anomali yang didokumentasikan-saja
  (beda dari pola "trust-based order" di Sales).
- **Rate band** dipakai sebagai guard kualitas + alat bantu jadwal, **bukan** dasar hitung
  insentif/gaji.
- **Hot Job dari Direksi** wajib bertanda visual khusus + sistem tampilkan dampak ke antrian, tapi
  eksekusi perubahan tetap manual/approval PPIC.
- **QC gate wajib** di titik kritis routing, diperketat khusus pasca-subkontrak.
- MRP (netting RM) tetap dibangun tapi sebagai **lapisan sekunder** di belakang scheduling, pakai
  lot-for-lot v1.
- MRP **dinamis**: mendukung usulan substitusi bahan setara & cutting-yield override saat bahan
  exact tak cukup — keduanya usulan sistem, eksekusi tetap keputusan manusia.
- Filosofi procurement = **lean/JIT** — rantai Inquiry→Costing→PR→PO→PV tersambung sebagai satu
  garis waktu, target minim stok RM/FG tersimpan (kapasitas gudang kecil).
- **Lead time historis wajib tercatat & rolling** — jadi basis estimasi default berikutnya secara
  otomatis, bukan angka statis.
- **Production Output Monitoring** (mesin + manual job) masuk scope modul ini — PPIC pantau hasil
  aktual, bukan cuma menjadwalkan rencana.
- Detail granular Master Card (warna/tinta per unit, jenis lem) **bukan tabel baru di modul ini** —
  itu delta ke Master Card generation di Sales; modul ini hanya konsumen datanya.
- **Algoritma scheduling = Priority Dispatching Rules (PDR)**, bukan Genetic Algorithm — cepat,
  transparan, natural untuk local-repair saat disrupsi. GA ditolak untuk domain ini (lihat §5A).
- **Alur wajib: Simulasi → Submit → Master Schedule Real** — PPIC tak boleh langsung menjadwalkan
  ke jadwal live untuk job baru; harus lewat sandbox dulu (§5A.1). Interupsi terhadap jadwal live
  yang sudah berjalan adalah pengecualian sah (§5A.2), karena sifatnya darurat.
- **Interupsi selalu menghasilkan opsi (1–3), bukan 1 jawaban otomatis** — PPIC pilih, sistem
  commit sesuai pilihan. Tidak ada auto-silent-change ke jadwal live.
- **Decision Feedback Loop wajib rule-based/explainable** — boleh menyesuaikan bobot rekomendasi
  dari histori keputusan PPIC, tapi TIDAK BOLEH jadi model ML opaque yang tak bisa dijelaskan
  alasannya.
- **Exception Dashboard real-time wajib ada (F17)** — PPIC tidak akan sanggup memindai manual
  job belum terjadwal/berisiko telat dalam hitungan detik/menit; ini kebutuhan eksplisit, bukan
  nice-to-have.
- **Penjadwalan dihitung mundur dari due date (Backward Scheduling, §5A.3)** — bukan maju dari
  "sekarang" — memakai target/kemampuan tiap proses (Rate Band) untuk menentukan kapan tiap Job
  Operation harus mulai & selesai; ini juga yang mengunci kapan Time-Phased Procurement (Prinsip
  #9) harus mulai.
- **Otorisasi ACTIVE-kan rule di Constraint Rule Library**: Manager Produksi, Manager Operasional,
  Admin App, atau level di atas Manager Operasional — role-based (lihat detail eks-§9.7 di atas).
- **Order TMERP = 2 tipe JO, bukan 3**: MTO (80%, CO-driven) + MTS (20%, forecast-driven oleh
  Direksi, akurasi target 85-90%, replenish 2-3×/minggu). MTS bermuara ke engine eksekusi yang
  sama dengan MTO begitu Job Order lahir (lihat eks-§9.1, §5A.8).
- **Resource = dua dimensi**: Machine Resource + Human Resource. Task mesin butuh **mesin + Crew**
  (Operator Utama wajib + Co-Operator opsional); task manual dijadwalkan **per orang langsung**.
  Operator/co-operator adalah resource yang dicek ketersediaannya sendiri (bisa bentrok
  lintas-mesin), bukan sekadar atribut task (lihat eks-§9.2, §5A.9).
- **Subkontrak akibat breakdown wajib PO resmi** tipe khusus `SUBCONTRACT`, terhubung ke
  Procurement (`purchase_requisition`/`purchase_order`), entry simplified karena sifatnya urgent
  (eks-§9.3).
- **Approval substitusi bahan/cutting-yield**: bisa diajukan dari PPIC/Purchasing/Product
  Development/Product Costing, tapi **approval final minimal Manager Operasional** (atau di
  atasnya: GM, Direksi) — flat minimum level, bukan digradasi by-threshold besaran substitusi.
  **Kriteria "setara"**: gramatur sama + kualitas sama + ukuran boleh lebih besar (jadi satu
  mekanisme dengan Cutting Yield, bukan dua) + prioritas ke **Dead Stock → Slow Moving Stock**
  (eks-§9.4, detail §5A.10).
- **Granularitas Lead Time Historis = hybrid**: per supplier+material kalau data cukup, fallback
  ke per-material saja kalau belum — pola sama dengan fallback Rate Band (eks-§9.5).
- **Kalibrasi bobot Decision Feedback Loop = berkala + approval** (mingguan/bulanan, kadensi pasti
  di Tier 2), bukan otomatis terus-menerus — mencegah drift tanpa disadari (eks-§9.6).

---

## 9. KEPUTUSAN MENGGANTUNG (jawab sebelum Tier 2)

### 9.1 ~~Klasifikasi 10% sisa order~~ ✅ TERJAWAB
**Jawaban Telma:** bukan tipe JO ketiga — ini **MTS juga**, hanya lebih spesifik: short-run,
replenishment 2-3×/minggu, forecast disusun top management/Direksi, target akurasi 85-90%. Total
jadi **80% MTO + 20% MTS** (2 tipe JO saja, sesuai saran awal), tapi MTS-nya genuinely
forecast-driven — lihat pembaruan di §1, §2 (nuansa hybrid), dan §5A.8 (mekanisme baru).

### 9.2 ~~Granularitas Resource~~ ✅ TERJAWAB
**Jawaban Telma — lebih presisi dari saran awal saya:** task mesin butuh **mesin + Crew** (Operator
Utama wajib 1 + Co-Operator 0..N), task manual dijadwalkan **per orang langsung** (tanpa struktur
crew). Bukan "operator sebagai atribut" seperti saran awal — operator/co-operator adalah resource
yang bisa bentrok lintas-mesin dan harus dicek ketersediaannya sendiri. Detail lengkap §5A.9.

### 9.3 ~~Skema Subkontrak — terhubung Procurement atau internal saja?~~ ✅ TERJAWAB
**Jawaban Telma:** wajib PO resmi tipe khusus `SUBCONTRACT` — bukan catatan internal saja, bukan
juga tergantung threshold nilai. Terhubung ke `purchase_requisition`/`purchase_order` yang sudah
live di Procurement, entry simplified karena breakdown = urgent (tak sempat proses PR berjenjang
normal).

---

### 9.4 ~~Kriteria & Approval Substitusi Bahan / Cutting Yield~~ ✅ TERJAWAB
**Approval:** usulan bisa **diajukan** dari beberapa fungsi berbeda (PPIC, Purchasing, Product
Development, Product Costing — masing-masing punya sudut pandang beda: dampak jadwal,
ketersediaan bahan, dampak spec, dampak harga), tapi **approval final minimal Manager Operasional**
(atau lebih tinggi: GM, Direksi). **Bukan** digradasi by-threshold toleransi kecil/besar seperti
saran awal saya — jadi flat minimum level, berapa pun besaran substitusinya.

**Kriteria "setara" — jawaban Telma, lebih sederhana dari dugaan saya:** bukan cross-material
matching dengan toleransi gramatur (saran awal saya, TIDAK dipakai). Kriterianya:
- **Gramatur sama** (persis, bukan toleransi ±X%).
- **Kualitas/grade sama** (persis).
- **Ukuran boleh beda — asal lebih besar** (ini pada dasarnya **selalu skenario Cutting Yield**,
  bukan substitusi material berbeda jenis — jadi §3 Prinsip #8 "Substitusi bahan" vs "Cutting
  yield override" secara praktik **menyatu jadi satu mekanisme** di TMERP: cari stok gramatur+
  kualitas sama, ukuran ≥ kebutuhan, potong ke ukuran pas).
- **Prioritas ke Dead Stock lalu Slow Moving Stock** (definisi baku, lihat §5A.10) — bukan sekadar
  "stok besar mana saja yang tersedia". Ini selaras dua prinsip yang sudah terkunci sekaligus:
  Prinsip #9 (lean/JIT, minim stok tersimpan — pakai stok lama = kosongkan gudang) dan mencegah
  write-off akibat bahan tak terpakai lama di gudang.

**Konsekuensi desain:** query pencarian kandidat substitusi/cutting-yield jadi **exact-match**
pada gramatur+kualitas (bukan fuzzy-tolerance), **diurutkan** Dead Stock → Slow Moving Stock →
normal (dihitung dari kartu stok yang sudah ada di Inventory, lihat §5A.10 untuk sumber data &
catatan cross-modul) — kandidat paling lama
menganggur muncul paling atas. Ini jauh lebih sederhana dari opsi "tabel eksplisit A-boleh-ganti-B"
maupun "toleransi gramatur otomatis" yang saya usulkan sebelumnya — **tidak perlu keduanya**.

### 9.5 ~~Granularitas Penyimpanan Lead Time Historis~~ ✅ TERJAWAB (rekomendasi diterima)
**Keputusan final: Hybrid.** Simpan rolling lead time per **kombinasi supplier+material**
(presisi), tapi kalau kombinasi tertentu belum punya cukup data (di bawah jumlah minimum data
point — angka pasti ditentukan Tier 2, mis. minimal 5 transaksi), sistem **fallback otomatis** ke
rata-rata per **material saja** sebagai estimasi sementara. Begitu kombinasi supplier+material itu
terkumpul cukup data, otomatis pindah pakai angka presisinya sendiri — tanpa intervensi manual.
Pola ini **sama** dengan fallback Rate Band (§5A.6) — konsisten, tidak menambah konsep baru.

### 9.6 ~~Mekanisme Kalibrasi Bobot Decision Feedback Loop~~ ✅ TERJAWAB (rekomendasi diterima)
**Keputusan final: Kalibrasi berkala dengan approval** (bukan otomatis terus-menerus). Bobot
rekomendasi opsi (§5A.5 poin 2) di-review berkala (mis. mingguan/bulanan — kadensi pasti di Tier 2)
dan butuh approval PPIC/Plant Manager sebelum diterapkan. Alasan: otomatis-terus-menerus berisiko
"drift" tanpa disadari (bobot bergeser pelan-pelan sampai rekomendasi jadi bias tanpa PPIC sadar
kenapa) dan sulit di-audit; kalibrasi berkala tetap adaptif tapi terkendali & bisa dijelaskan di
titik waktu yang jelas — konsisten Prinsip #1 (no black-box).

### 9.7 ~~Siapa yang Berwenang Menambah/Menonaktifkan Rule di Constraint Rule Library~~ ✅ TERJAWAB
**Jawaban Telma:** otorisasi ACTIVE-kan rule (Batching/Sequencing, §5A.7) dipegang oleh **Manager
Produksi, Manager Operasional, Admin App, atau level di atas Manager Operasional** (role-based,
bukan satu jabatan tunggal). PPIC officer tetap bisa buat rule baru sebagai draft/testing, tapi
butuh salah satu role di atas untuk mengubah status jadi `ACTIVE`. Ini masuk **keputusan terkunci**
(pindah ke §8), bukan lagi pertanyaan terbuka.

> Catatan implementasi (Tier 2 nanti): perlu dipetakan role-role ini ke `role_id`/`permission` yang
> sudah ada di sistem otorisasi TMERP (Gate/policy Laravel) — bukan hardcode nama jabatan.

---

## 10. LANGKAH BERIKUTNYA

1. ~~Telma jawab §9.1–§9.3~~ ✅ selesai — seluruh §9.1–§9.7 terjawab, tidak ada lagi keputusan
   menggantung di Tier 1 ini.
2. Lanjut **Tier 2** (business logic & rules): state machine JO/Task, matriks status-tombol,
   detail algoritma PDR (§5A) jadi pseudocode/spesifikasi implementasi, detail alur
   breakdown→subkontrak→QC, detail form input forecast MTS (§5A.8), detail struktur data
   Constraint Rule Library (§5A.7) & Crew (§5A.9).
3. **Buat `production_state.yaml`** (belum ada) begitu Tier 2 mulai digarap — ikuti pola
   `SALES_STATE.yaml`/`sales_state.yaml` yang sudah ada di project knowledge.
