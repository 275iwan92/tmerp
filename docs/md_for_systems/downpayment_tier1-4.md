# FINANCE — DOWN PAYMENT / UANG MUKA · TIER 1–4

> Modul **06_finance** · DB `tm2acc` (conn `conn_tm2acc`) · Status: **DESIGN — verified vs skema aktual**
> Konvensi: field English, prosa Indonesia; money `DECIMAL(18,4)`; FK lintas-DB = logical (Eloquent).
> Skema `tm2acc` **sudah ada & matang** — dokumen ini menempel pada tabel yang ada, bukan bikin ulang.

> ## PERUBAHAN 2026-10-02 (keputusan user, menggantikan butir LOCKED di bawah)
>
> Ketentuan yang berlaku sekarang ada di **`customerinvoice_accounting.md`**. Yang berubah dari dokumen ini:
>
> 1. **Tagihan uang muka tidak menjurnal.** Event 1 lama (Dr Piutang / Cr Uang Muka + PPN saat tagihan DP
>    terbit) **dihapus**. Tagihan DP = permintaan pembayaran; bukan piutang dan tidak masuk umur piutang.
> 2. **Jurnal lahir saat uang muka diterima**: Dr Bank / Cr 213200 Uang Muka Penjualan (+ Cr 212100 PPN Keluaran
>    untuk order PPN). Event 2 lama (Dr Bank / Cr Piutang) diganti jurnal ini.
> 3. **PPN uang muka diakui saat uang diterima**, bukan saat tagihan DP terbit (butir 1.4.2). Setelan cadangan
>    `DP_VAT_ON_RECEIPT = 0` mengembalikan ke cara lama perusahaan (PPN seluruhnya di invoice akhir).
> 4. **Event 3, 4, 5 tetap** (invoice penuh, pemotongan uang muka dengan reklas PPN, alokasi per CO, lintas
>    pengiriman). `fin_dp_allocation` dan `v1_fin_dp_outstanding` tetap dipakai.
> 5. **Kode akun** di dokumen ini (`232`, `121`, `221`, `112`, `411`) adalah kode draf lama. Kode di daftar akun
>    yang sebenarnya: 213200 Uang Muka Penjualan, 112100 Piutang Usaha, 212100 PPN Keluaran, 1112xx Bank,
>    411000 Penjualan.
> 6. **WO gate** (butir 1.4.1) tidak diubah oleh keputusan ini; "DP PAID" dibaca dari penerimaan uang muka.
>
> Bagian di bawah dibiarkan sebagai riwayat; bila bertentangan dengan butir di atas, butir di atas yang berlaku.

---

## 0. SKEMA AKTUAL YANG DIPAKAI (verified)

| Objek | Peran dalam alur DP |
|---|---|
| `fin_account` | COA. Akun kunci: **`232`** Uang Muka Penjualan (LIABILITY, `is_recon=1`), **`143`** Uang Muka Pembelian (ASSET, `is_recon=1`), `121` Piutang Usaha, `211` Hutang Dagang, `221` PPN Keluaran, `141` PPN Masukan, `112` Bank |
| `fin_invoice` | Dokumen tagih: DP invoice **dan** invoice final. Punya `co_id`, `partner_id`, `paid_amount`, `payment_status` |
| `fin_voucher` + `fin_voucher_line` | Jurnal GL. `line` punya `partner_id` + **`co_id`** → subledger per customer per CO |
| `fin_dp_allocation` | **M:N** — potongan DP ke invoice final; `allocated_base` + `allocated_tax` |
| `fin_partner_account` | Map partner → AR/AP account + `payment_term` |
| `v1_fin_ar_aging` / `v1_fin_ap_aging` / `v1_fin_trial_balance` | View pelaporan |

**Tidak ada tabel `fin_advance` / `chart_of_accounts` / `subledger_invoices`** — nama-nama itu dari draf awal, sudah ditinggalkan. DP = **pola posting** di atas struktur GL yang ada + satu junction alokasi.

### ⚠ DELTA WAJIB sebelum implementasi
```sql
-- 1) Tambah tipe DP ke invoice_type (blocker: tanpa ini DP tak bisa dibedakan)
ALTER TABLE fin_invoice
  MODIFY COLUMN invoice_type
  enum('CUSTOMER_INVOICE','SUPPLIER_BILL','CUSTOMER_DP','SUPPLIER_DP')
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- 2) (disarankan) Cegah alokasi ke diri sendiri
ALTER TABLE fin_dp_allocation
  ADD CONSTRAINT chk_dpa_not_self CHECK (dp_invoice_id <> final_invoice_id);

-- 3) (disarankan) View sisa DP per faktur DP
CREATE OR REPLACE VIEW v1_fin_dp_outstanding AS
SELECT d.invoice_id AS dp_invoice_id, d.invoice_number, d.partner_id, d.co_id,
       d.subtotal AS dp_base, d.tax_amount AS dp_tax, d.total_amount AS dp_total,
       COALESCE(SUM(a.allocated_base),0) AS used_base,
       COALESCE(SUM(a.allocated_tax),0)  AS used_tax,
       d.total_amount - COALESCE(SUM(a.allocated_base+a.allocated_tax),0) AS dp_remaining
FROM fin_invoice d
LEFT JOIN fin_dp_allocation a ON a.dp_invoice_id = d.invoice_id
WHERE d.invoice_type IN ('CUSTOMER_DP','SUPPLIER_DP')
GROUP BY d.invoice_id;
```

---

# TIER 1 — BUSINESS REQUIREMENT & PROCESS MATRIX

## 1.1 Scope
Uang muka dua arah, lifecycle simetris:

| Arah | Peristiwa | Akun UM | `invoice_type` | Hulu |
|---|---|---|---|---|
| **SALES DP** (Customer Advance) | **terima** DP dari customer sebelum barang diserahkan | `232` (CREDIT) | `CUSTOMER_DP` | SO/CO (`tm2sdi`) |
| **PURCHASE DP** (Supplier Advance) | **bayar** DP ke supplier sebelum barang diterima | `143` (DEBIT) | `SUPPLIER_DP` | PO (`tm2pur`) |

Fokus dokumen: **SALES DP** (kasus utama pabrik). PURCHASE DP = cermin (§2B-5).

## 1.2 Aktor & dokumen
Sales (buat DP invoice dari SO) · Finance/Kasir (terima pembayaran, posting) · Finance/AR (terbitkan invoice final + alokasi DP) · Direksi (approval bila > batas).

## 1.3 State
- **DP invoice** (`fin_invoice.payment_status`): `UNPAID → PARTIAL → PAID`.
- **DP saldo** (via `v1_fin_dp_outstanding.dp_remaining`): `> 0 OUTSTANDING → = 0 FULLY_APPLIED`.
- **Voucher** (`fin_voucher.status`): `DRAFT → POSTED → CANCELLED`.

## 1.4 Aturan bisnis kunci (LOCKED)
1. **WO gate:** Work Order tak boleh dibuka sebelum DP penjualan **PAID** (mengikat ke Production; tercermin di mockup DP invoice).
2. **PPN DP diakui saat terbit DP** (PKP: faktur pajak uang muka) → kredit `221`. Saat invoice final, PPN DP porsi teralokasi **direklas** (bukan dobel-pungut).
3. **Alokasi per CO:** `fin_invoice.co_id` & `fin_voucher_line.co_id` menjaga UM tetap terlacak per Customer Order — mendukung **shipment bertahap** & **cross-CO** (satu DP bisa menutup beberapa invoice final).
4. **Σ alokasi ≤ total DP** (guard over-alokasi — app-level, karena butuh agregat lintas baris).
5. **Immutable posted:** voucher POSTED tak diedit; koreksi = voucher balik (REVISE), selaras `doc_type` di `fin_voucher`.

## 1.5 Process matrix
| # | Event | Tabel tersentuh | Efek |
|---|---|---|---|
| 1 | Terbit DP invoice | `fin_invoice`(+CUSTOMER_DP), `fin_voucher(_line)` | akui piutang DP, kredit `232` + `221` |
| 2 | DP dibayar | `fin_invoice.paid_amount/status`, `fin_voucher(_line)` | kas masuk `112`, tutup piutang DP; WO gate terbuka |
| 3 | Invoice final (per shipment) | `fin_invoice`(CUSTOMER_INVOICE, `co_id`), `fin_voucher(_line)` | akui `411` + `221` penuh porsi kirim |
| 4 | Alokasi DP → final | `fin_dp_allocation`, `fin_voucher(_line)` | reklas `232`+`221` menutup sebagian piutang final |
| 5 | DP habis (`dp_remaining=0`) | (derived) | status FULLY_APPLIED |

---

# TIER 2 — BUSINESS LOGIC

## 2A. Happy path (SALES DP, single CO, multi-shipment)
```
SO/CO disetujui (price PRICE_APPROVED)
  → Sales terbitkan DP invoice (% dari nilai SO)          [Event 1]
  → Customer bayar DP → payment_status PAID → WO dibuka    [Event 2]
  → Produksi jalan → kirim bertahap
      tiap shipment: invoice final porsi kirim             [Event 3]
                     + alokasi DP proporsional             [Event 4]
  → Σ alokasi = DP → dp_remaining = 0                      [Event 5]
```
Prinsip alokasi default = **proporsional** terhadap % DP awal (mis. DP 50% → tiap final dipotong 50% nilainya s.d. DP habis). Boleh **manual override** (mis. sengaja habiskan DP di shipment pertama untuk menutup CO lama — lihat 2B-2).

## 2B. Special cases
1. **Partial shipment** — DP dipotong sebagian per invoice final; sisa DP jalan ke shipment berikут (kasus di §3.3).
2. **Cross-CO consolidated** — satu DP `co_id=A`, tapi shipment gabung menutup CO-A lalu CO-B. Karena alokasi di level **baris** (`dp_invoice_id`↔`final_invoice_id`) dan invoice final punya `co_id` masing-masing, satu faktur DP boleh dialokasikan ke invoice final beda CO. Alokasi manual/intentional (mis. tutup CO lama dulu untuk invoicing).
3. **Over-alokasi** — tolak bila `Σ allocated_base+allocated_tax` (existing + baru) `> dp_total`. Cek via `v1_fin_dp_outstanding` sebelum insert.
4. **Cancel/REVISE** — invoice final dibatalkan: `ON DELETE RESTRICT` menahan hapus selama ada alokasi; prosedur benar = voucher balik + hapus baris alokasi pada status DRAFT (delete guard global: hard-delete hanya DRAFT).
5. **PURCHASE DP (mirror)** — DP ke supplier: `Dr 143 + Dr 141(PPN Masukan) / Cr 112`; saat receiving/bill final, alokasi menutup `143` ke `211` (Hutang Dagang). `invoice_type='SUPPLIER_DP'`.

## 2C. State–Form–Button
| Layar | State | Tombol aktif | Guard |
|---|---|---|---|
| DP Invoice | draft | Terbitkan & Kirim | SO ada + price approved |
| DP Invoice | UNPAID | Catat Pembayaran | — |
| DP Invoice | PAID | (kunci) | trigger buka WO |
| Alokasi DP | pilih DP + final | Alokasikan | `remaining ≥ jumlah`; `dp≠final` |
| Alokasi DP | teralokasi | Batalkan (DRAFT only) | voucher belum POSTED |

---

# TIER 3 — DDL & SAMPLE RECORD

## 3.1 Objek existing → lihat `tm2acc.sql` (fin_account, fin_invoice, fin_voucher, fin_voucher_line, fin_dp_allocation). 3.2 Delta → §0.

## 3.3 SAMPLE RECORD — PT Nutri Food (angka tie-out)

**Skenario.** CO-2026-0899, nilai barang **Rp100.000.000** + PPN 11% = kontrak **Rp111.000.000**. DP **50%** dari pokok = Rp50.000.000 + PPN Rp5.500.000 = tagihan DP **Rp55.500.000**. Kirim 2 tahap: **40%** lalu **60%**. `partner_id=501` (customer), `co_id=899`.

### Event 1 — Terbit DP invoice (VCH-SAL-…-0101)
`fin_invoice`: `id=101, type=CUSTOMER_DP, partner=501, co_id=899, subtotal=50.000.000, tax=5.500.000, total=55.500.000, status=UNPAID`.
Jurnal (journal SAL):
| account | debit | credit |
|---|---:|---:|
| `121` Piutang Usaha | 55.500.000 | |
| `232` Uang Muka Penjualan | | 50.000.000 |
| `221` PPN Keluaran (DP) | | 5.500.000 |

### Event 2 — DP dibayar
`fin_invoice 101`: `paid_amount=55.500.000, status=PAID` → **WO dibuka**. Jurnal (BNK): `Dr 112 Bank 55.500.000 / Cr 121 Piutang 55.500.000`.

### Event 3a — Invoice final Kirim-1 (40%)
`fin_invoice`: `id=201, type=CUSTOMER_INVOICE, partner=501, co_id=899, subtotal=40.000.000, tax=4.400.000, total=44.400.000`.
Jurnal: `Dr 121 Piutang 44.400.000 / Cr 411 Penjualan 40.000.000 / Cr 221 PPN 4.400.000`.

### Event 4a — Alokasi DP → invoice 201 (potong 50%)
`fin_dp_allocation`: `dp_invoice_id=101, final_invoice_id=201, allocated_base=20.000.000, allocated_tax=2.200.000`.
Jurnal reklas:
| account | debit | credit |
|---|---:|---:|
| `232` Uang Muka Penjualan | 20.000.000 | |
| `221` PPN Keluaran (reklas DP) | 2.200.000 | |
| `121` Piutang Usaha (inv 201) | | 22.200.000 |

→ sisa tagih tunai invoice 201 = 44.400.000 − 22.200.000 = **Rp22.200.000**.

### Event 3b/4b — Kirim-2 (60%) + alokasi sisa DP
`fin_invoice 202`: `subtotal=60.000.000, tax=6.600.000, total=66.600.000`.
`fin_dp_allocation`: `dp_invoice_id=101, final_invoice_id=202, allocated_base=30.000.000, allocated_tax=3.300.000`.
Reklas: `Dr 232 30.000.000 / Dr 221 3.300.000 / Cr 121(inv202) 33.300.000` → sisa tagih invoice 202 = **Rp33.300.000**.

### Verifikasi (tie-out)
```
DP terpakai (base): 20.000.000 + 30.000.000 = 50.000.000  = DP pokok  ✓
DP terpakai (PPN) :  2.200.000 +  3.300.000 =  5.500.000  = PPN DP    ✓
v1_fin_dp_outstanding.dp_remaining (inv 101) = 0            → FULLY_APPLIED ✓
Total kas diterima: 55,5jt(DP) + 22,2jt(inv201) + 33,3jt(inv202) = 111.000.000 = kontrak+PPN ✓
Saldo akun 232 (Uang Muka Penjualan) akhir = 0             ✓
```

### INSERT sample (alokasi)
```sql
USE tm2acc;
INSERT INTO fin_dp_allocation
  (dp_invoice_id, final_invoice_id, allocated_base, allocated_tax, allocation_date, created_by)
VALUES
  (101, 201, 20000000.0000, 2200000.0000, '2026-09-05', 'finance'),
  (101, 202, 30000000.0000, 3300000.0000, '2026-09-20', 'finance');
-- cek sisa: SELECT * FROM v1_fin_dp_outstanding WHERE dp_invoice_id = 101;  -- dp_remaining = 0
```

---

# TIER 4 — UI / LIVEWIRE BINDING

## 4.1 Layar "Tagihan Uang Muka" (DP Invoice)
Mockup: `dp_invoice_mockup.html` (token TMERP, topbar link-to-menu). Layout **terpusat** (dokumen). Binding:
- `SO ref` → `co_id`; `Customer` → `partner_id` (read-only dari `v_customer`).
- `Metode DP` (% SO / nominal) → hitung `subtotal`; `PPN` → `tax_amount`; `Total` → `total_amount`.
- `Akun Kewajiban UM` = `232` (locked).
- Tombol **Terbitkan** → insert `fin_invoice(CUSTOMER_DP)` + voucher Event 1 (status DRAFT→POSTED).
- Badge: 🔒 field terkunci · = hasil hitung (hijau).

## 4.2 Layar "Alokasi DP" (baru — usulan)
Layout **full-page** (operasional). Struktur: pilih DP invoice (grid `v1_fin_dp_outstanding`, tampil `dp_remaining`) → pilih invoice final (grid AR open per `co_id`) → input `allocated_base`/`allocated_tax` → **Alokasikan**.
- Guard UI: `base+tax ≤ dp_remaining`; `dp≠final`; tombol Alokasikan disabled bila melanggar.
- Setelah alokasi: tulis `fin_dp_allocation` + voucher Event 4; refresh sisa DP & outstanding invoice final.
- `.qtbl` + toolbar (radio row-select) sesuai konvensi UI existing.

## 4.3 Guard & konvensi
- Server-side pagination (`WithPagination`) + index `idx_dpa_dp/idx_dpa_final` untuk grid alokasi.
- Over-alokasi: validasi Livewire baca `v1_fin_dp_outstanding` sebelum commit.
- Semua nilai uang tampil terformat Rp; simpan `DECIMAL(18,4)`.

---

## STATUS
**LOCKED:** akun `143`/`232` (is_recon=1), `fin_dp_allocation`, alur jurnal Tier 3, WO gate, alokasi per-CO.
**DELTA WAJIB (belum dijalankan):** enum `invoice_type` + tipe DP (§0). Setelah itu + view/CHECK opsional, spec ini handoff-ready ke Claude Code.
**OPEN kecil:** penomoran `invoice_number` DP (prefix `INV-DP-{YY}-…`, reset tahunan); default aturan alokasi proporsional vs manual sebagai setting.
