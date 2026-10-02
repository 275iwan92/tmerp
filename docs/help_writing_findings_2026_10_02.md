# Temuan saat menulis Help (2026-10-02)

Help ditulis dengan membaca kode tiap layar. Hal di bawah ini terlihat janggal saat membaca; **belum ada yang diperbaiki** dan
belum diverifikasi dengan menjalankan layarnya. Urut dari yang paling berisiko. Yang selesai dipindah ke `docs/LOG_JOB_DONE.md`.

## Perlu dilihat dulu (kerahasiaan / data salah)

| # | Layar | Temuan |
|---|---|---|
| 1 | Master Gaji & Pajak (`hrm-salary`) | Komentar kode menyebut nominal disamarkan bagi yang tidak berhak payroll, tetapi halaman lama dan `SalaryGrid` tetap menampilkan gaji pokok dan tunjangan untuk semua baris. Hanya form edit yang dijaga. |
| 2 | Customer Order, Delivery Order | Kolom harga dan subtotal tampil untuk semua yang punya akses SALES. Belum dicek terhadap aturan A.9 (harga hanya untuk Sales, Marketing, Product Costing, Operational Manager ke atas). |
| 3 | Adjustment Approval (`approval-stock`) | `ApprovalCenter::stockDoc()` mencari dokumen berdasarkan id bergiliran di tabel RM, FG, SFG, dan log alur tidak menyimpan jenis stok. Dua dokumen ber-id sama di jenis berbeda bisa tertukar. |
| 4 | Slow Moving Item (3 laporan) | `HAVING MAX(last_movement) < tanggal` membuang baris ber-`last_movement` NULL, padahal panduan layar menyebut "Never moved" ikut. Ambang tetap 3 bulan (v1 bisa pilih 3 / 6 / 9 / 12). |
| 5 | Master Gudang | `removeLocation()` menghapus lokasi (bin) permanen tanpa cek stok atau riwayat pergerakan. |
| 6 | Recruitment: Requisition, Offer | Bila approver satu langkah belum diatur, `approver_id` tersimpan 0 sehingga langkah itu tidak bisa diputuskan siapa pun. Nomor dokumen dari `count() + 1` per tahun bisa kembar setelah ada yang dihapus. |
| 7 | Offering | Penolakan approver menyimpan status `DECLINED`, sedangkan `OfferGrid` hanya mengenal `REJECTED` (tanpa warna, tidak terhitung). |
| 8 | Assessment | Form menyimpan `PERTIMBANGAN`, grid menunggu `PERTIMBANGKAN` — lencana tidak pernah berwarna. |
| 9 | Down Payment | Tarif PPN ditulis 11% (hitungan DP di-hardcode 0,11) sedangkan Order to Cash / Procure to Pay menulis "PPN 12% × DPP 11/12". Pembayaran selalu penuh ke bank 111200, tanpa pilihan bank. |

## Fungsi yang belum sampai ke layar v2

| Layar | Temuan |
|---|---|
| Down Payment | Grid v2 tidak bisa membuka baris; "Catat Pembayaran" dan "Alokasikan" hanya ada di halaman lama. |
| Order to Cash, Procure to Pay | "Cancel draft" dan "Validate tax invoice" hanya di halaman lama. |
| Fixed Assets, Amortization | "Run Depreciation" / "Run Amortization" hanya di halaman lama. |
| Outstanding PV | Aksi "serahkan ke Finance" tetap di layar v1; laporan v2 hanya baca. |
| Journal Entries | Grid menyebut Reversing Journal dan Year-end Closing, form tidak bisa membuatnya. |
| Trial Balance | Selalu per hari ini, tanpa pilihan tanggal. |
| Stock Out FG, Return FG | Grid tanpa form (tidak ada New / Open). |
| All Inquiries | Wizard New Record: "Dari Master Produk" masih "Segera" (mati), padahal Revise / Recalc sudah bisa memilih dari Master Product. |
| Win-Rate Sales | Tidak ada angka persen atau total. |
| BOP Rule Setting | New / Edit membuka halaman v1 yang hanya bisa diubah admin; di grid v2 tidak ada petunjuk. |
| Slip Gaji | Kolom Printout / Send WhatsApp di grid = centang "ikutkan", bukan status sudah dicetak / dikirim. Kirim WhatsApp belum ada. |
| Menu akun | Edit profil dan Change password = jendela contoh, Save tidak menyimpan. Notifikasi berisi contoh. |
| Grid master | Edit dan New masih membuka form v1. |

## Label dan konsistensi

| Layar | Temuan |
|---|---|
| Approval Center (CO, PR, PO, Return, Stock) | Tombol Setujui / Tahan / Tolak berbahasa Indonesia (aturan C.5: English). |
| PR, PO, SPB, Return stok, Down Payment, form v1 produksi | Label campur Indonesia / English. |
| Laporan CO | Kolom `Confirm` = "Yes" hanya bila status CONFIRMED, jadi CO yang CLOSED tampil "No". Filter `Qty Minim` tidak berfungsi di Summary. Teks panduan menyebut 3 grup, kodenya 6. |
| Purchase Order | Teks form: Cash PO disetujui Finance Director; aturan A.11 hanya menyebut Product Costing lalu Operational Manager. |
| Purchase Requisition | Jenis Production / Non Production (C.24) hanya ada di format cetak, tidak ada isian di form. |
| Inquiry Revise vs Recalc | Dimensi berlabel mm di Revise, cm di Recalc dan Master Product. |
| Pro-Dev Revise | Grid tidak menampilkan Revision Scope / Source Inquiry seperti Inquiry Revise. |
| Material Booking | Dokumen menyebut `BOOKING_LEAD_DAYS` (7 hari kerja); kode memakai `BOOKING_NEED_WORKDAYS` (3). |
| Katalog Model Mesin | Pilihan Drive "Manual" / "Otomatis" tersimpan Indonesia. |
| Master Mesin | Tab Maintenance di form mesin tumpang tindih dengan modul Maintenance baru; sumber kebenaran belum jelas. |
| Rule Library | Pemilih mesin hanya mesin aktif divisi OFFSET. |
| Stock Adjustment | "Line Reason" bertanda wajib tetapi tidak dicek dan tidak disimpan. |
| Return from Production | Dropdown lokasi scrap menampilkan lokasi dari semua gudang. |
| Cashier | Grid mengenal SUBMITTED / APPROVED, filter status hanya Draft / Posted / Cancelled. |
| Presensi | Kotak Absent di grid menghitung IS dan CL, halaman lama tidak. |
| Grid DevLib | Subtotal grup hanya dari baris halaman aktif (baris total bawah benar). Beberapa teks belum lewat kamus ("Total (semua hasil)", "Aksi", "+ Tambah baru"). Ctrl+K mungkin tidak jalan saat fokus di dalam tab layar. |
| Dialog tinggalkan form | Tombolnya Save / Undo / Cancel, bukan Save / Don't Save / Cancel (C.8). |
| Halaman Tema | Masih ada baris Density (C.29: tidak ada pintu kedua). |
| `Flows.php` | Langkah Customer Order masih menulis mode INSTANT "belum ada". |
| Master Supplier | `is_subcon` tidak punya isian di form (hanya diisi migration) padahal dibaca hutang / subkon. Kontak dan cabang hanya bisa ditambah lewat panel detail halaman lama. |
| Master Customer, Supplier | `is_blacklisted`, `credit_limit`, `credit_check_enabled`, tarif pajak supplier tersimpan dan tampil, tetapi tidak ditemukan dibaca oleh logika dokumen mana pun. "Mata Uang" customer diketik bebas, bukan dipilih dari Currency. |
| Kategori Bahan Baku | Lencana Stock Class di grid untuk RM / FG / SFG, form menyimpan RAW / INDIRECT / SERVICE — tidak pernah cocok. |
| Setting Approval, Officer Pengganti | Baris yang isinya kurang (tanpa posisi / user / tanggal) dilewati saat simpan tanpa pesan. |
| Customer Receiving Hours | Label "Open" dipakai dua kali (centang dan jam buka). |
