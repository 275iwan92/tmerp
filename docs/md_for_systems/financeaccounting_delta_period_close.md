# financeaccounting_delta_period_close.md
**Modul 06 Finance & Accounting — delta `tm2acc` (+ enforcement baru di `tm2inv`).**
Status: **KEPUTUSAN TERKUNCI** — spec self-contained untuk Claude Code.

---

## 0. Masalah yang diselesaikan

Saat ini **tidak ada satu pun mekanisme** yang mencegah voucher/jurnal diposting ke tanggal
mana pun, kapan pun — termasuk ke bulan yang sudah "selesai" tutup buku. Trial Balance /
Laporan Keuangan tidak bisa dipercaya tanpa ini. Riset pembanding (SAP Posting Period Variant,
Oracle period status + subledger-before-GL, Dynamics 365 F&O Open/OnHold/PermanentlyClosed,
Odoo Lock Date) — kesimpulan: TMERP ikut pola **Dynamics 365** (3-status per periode diskrit),
karena TMERP sudah punya konvensi tabel periode di Inventory (`inv_period`) — konsistensi
lintas modul menang atas mengejar pola "ideal" yang beda arsitektur (Odoo lock-date).

---

## 1. KEPUTUSAN TERKUNCI

1. **Model**: tabel periode diskrit per bulan, `status ENUM('OPEN','ON_HOLD','CLOSED')` —
   bukan lock-date ala Odoo.
2. **CLOSED = ireversibel penuh** (pola D365 Permanently Closed). Tidak ada tombol reopen,
   tidak ada exception, tidak ada bypass permission apa pun. Sekali CLOSED, selamanya CLOSED.
3. **Periode 13 (adjustment)** per tahun fiskal — khusus jurnal koreksi audit setelah tutup
   tahun, tidak mengganggu periode bulan berjalan tahun baru (pola SAP special period /
   D365 period 13). Berperilaku sama seperti periode biasa (OPEN/ON_HOLD/CLOSED), hanya
   `is_adjustment=1` dan tidak muncul di dropdown "periode berjalan" normal.
4. **Gate lintas-modul WAJIB, HARD BLOCK**: `fin_period` bulan X **tidak bisa** di-set CLOSED
   selama `inv_period` bulan X (di `tm2inv`) belum berstatus CLOSED. Alasan eksplisit:
   karena CLOSED ireversibel, GL yang terkunci duluan sementara Inventory masih terbuka
   berisiko menciptakan selisih permanen yang tak bisa diperbaiki (penyesuaian stok mundur
   yang tak pernah bisa diposting ke GL). Dicek via query lintas-DB di layer aplikasi
   (LOGICAL, bukan FK — konsisten `globalrules.yaml`).
5. **Prasyarat tersembunyi yang HARUS ikut dikerjakan**: `PeriodService::assertOpen()` di
   Inventory **belum pernah dipanggil di posting manapun** (dorman sejak dibuat). Gate di
   poin 4 tidak berarti apa-apa selama Inventory sendiri tidak menegakkan periodenya.
   Delta ini **mewajibkan** menyambungkan `PeriodService::assertOpen()` ke `StockPostingService`
   sebagai prasyarat, bukan opsional.
6. **Enforcement posting**: SETIAP titik yang menulis `fin_voucher` (Cash & Bank Journal
   manual, `JournalPostingService` auto-jurnal PV/Stock Out, DP Invoice) **wajib** memanggil
   `FinPeriodGuard::assertOpen($tanggal)` sebelum INSERT. Status `ON_HOLD` maupun `CLOSED`
   sama-sama menolak posting baru — bedanya `ON_HOLD` masih bisa dibalik ke `OPEN`, `CLOSED`
   tidak (poin 2).
7. **Auto-create periode**: job/artisan command menghasilkan 13 baris (12 bulan + 1 adjustment)
   per tahun fiskal, dijalankan sekali di awal tahun (atau manual trigger oleh Direksi/Finance).
   Status awal semua periode = `OPEN` kecuali adjustment (`ON_HOLD` sampai dibutuhkan).
8. **Tutup tahun (year-end)**: proses terpisah dari tutup bulan — menghasilkan **satu voucher
   penutup** yang memindahkan saldo akun REVENUE/COGS/EXPENSE/OTHER_INCOME/OTHER_EXPENSE
   (net = Laba Tahun Berjalan, akun `322000`) ke `321000` (Laba Ditahan). Voucher ini **wajib
   dibuat SEBELUM** periode 12 tahun tersebut di-set CLOSED — begitu CLOSED, tak ada jalan
   balik untuk mengoreksi angka closing itu (poin 2).
9. **Otorisasi**: hanya Direksi + Costing/Finance Officer (permission `FINACC_PERIOD_CLOSE`)
   yang bisa mengubah status periode. Every perubahan status (OPEN→ON_HOLD, ON_HOLD→OPEN,
   apa pun→CLOSED) tercatat: `changed_by`, `changed_at`, `reason` (wajib diisi, bukan opsional).

---

## 2. DDL — `tm2acc.fin_period`
```sql
CREATE TABLE fin_period (
  period_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fiscal_year     SMALLINT UNSIGNED NOT NULL,
  period_no       TINYINT UNSIGNED NOT NULL COMMENT '1-12 = bulan normal, 13 = adjustment (post year-end)',
  period_name     VARCHAR(30) NOT NULL COMMENT 'mis. "Agustus 2026", "Penyesuaian 2026"',
  start_date      DATE NOT NULL,
  end_date        DATE NOT NULL,
  is_adjustment   TINYINT(1) NOT NULL DEFAULT 0,
  status          ENUM('OPEN','ON_HOLD','CLOSED') NOT NULL DEFAULT 'OPEN',
  closed_by       VARCHAR(100) NULL,
  closed_at       DATETIME NULL,
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (period_id),
  UNIQUE KEY uq_fin_period (fiscal_year, period_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Periode posting GL — CLOSED ireversibel, gate wajib ke inv_period sebelum CLOSED';
```

## 3. DDL — `tm2acc.fin_period_log` (audit setiap perubahan status — WAJIB, bukan opsional)
```sql
CREATE TABLE fin_period_log (
  log_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  period_id    BIGINT UNSIGNED NOT NULL,
  from_status  ENUM('OPEN','ON_HOLD','CLOSED') NULL COMMENT 'NULL saat baris baru dibuat',
  to_status    ENUM('OPEN','ON_HOLD','CLOSED') NOT NULL,
  reason       VARCHAR(255) NOT NULL COMMENT 'wajib diisi user, tidak boleh kosong/default',
  changed_by   VARCHAR(100) NOT NULL,
  changed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_period (period_id),
  CONSTRAINT fk_periodlog_period FOREIGN KEY (period_id) REFERENCES fin_period(period_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Jejak audit perubahan status periode — CLOSED tidak pernah punya baris to_status berikutnya';
```

## 4. Service — `App\Services\Finance\FinPeriodGuard`
```php
class FinPeriodGuard
{
    // Dipanggil SETIAP titik tulis fin_voucher, sebelum INSERT.
    public function assertOpen(\DateTimeInterface $postingDate): void
    {
        $period = FinPeriod::where('start_date', '<=', $postingDate)
            ->where('end_date', '>=', $postingDate)
            ->where('is_adjustment', 0)
            ->first();

        if (!$period) {
            throw new PeriodNotFoundException("Tidak ada periode GL untuk tanggal {$postingDate->format('Y-m-d')}.");
        }
        if ($period->status !== 'OPEN') {
            throw new PeriodClosedException(
                "Periode {$period->period_name} berstatus {$period->status}. Posting ditolak."
            );
        }
    }

    // Dipanggil SEBELUM set status CLOSED. HARD BLOCK, bukan warning.
    public function assertCanClose(FinPeriod $period): void
    {
        if ($period->status === 'CLOSED') {
            throw new \LogicException('Periode sudah CLOSED — tidak ada cara mengubahnya lagi.');
        }

        // Gate lintas-modul: cek inv_period bulan yang sama, koneksi tm2inv.
        $invPeriod = DB::connection('tm2inv')->table('inv_period')
            ->where('fiscal_year', $period->fiscal_year)
            ->where('period_no', $period->period_no)
            ->first();

        if (!$invPeriod || $invPeriod->status !== 'CLOSED') {
            throw new SubledgerNotClosedException(
                "Inventory periode {$period->period_name} belum CLOSED (status: " .
                ($invPeriod->status ?? 'TIDAK ADA') . "). Tutup Inventory dulu sebelum GL."
            );
        }
    }

    public function changeStatus(FinPeriod $period, string $newStatus, string $reason, string $user): void
    {
        if ($newStatus === 'CLOSED') {
            $this->assertCanClose($period);
        }
        $from = $period->status;
        $period->update([
            'status' => $newStatus,
            'closed_by' => $newStatus === 'CLOSED' ? $user : $period->closed_by,
            'closed_at' => $newStatus === 'CLOSED' ? now() : $period->closed_at,
        ]);
        FinPeriodLog::create([
            'period_id' => $period->period_id,
            'from_status' => $from,
            'to_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $user,
        ]);
    }
}
```

## 5. Prasyarat WAJIB di Inventory — sambungkan `PeriodService::assertOpen()`

Sebelum §4 berguna, `App\Services\Inventory\PeriodService::assertOpen()` (sudah ada, dorman)
**harus** dipanggil dari `StockPostingService::postDocument()` — titik yang sama yang sudah
mengecek `period_id` OPEN secara manual (lihat `inventory_tier2.md` langkah 2: "Tolak bila
period_id tidak OPEN"). Kemungkinan besar logikanya **sudah ada** tapi belum lewat method
terpusat `PeriodService` — cukup pastikan satu jalur, jangan duplikasi logic.

> **Verifikasi dulu** (jangan asumsi): buka `StockPostingService::postDocument()` aktual,
> cek apakah pengecekan period sudah ada inline atau benar-benar tidak ada sama sekali.
> Kalau sudah ada inline, refactor ke `PeriodService::assertOpen()` supaya satu sumber
> kebenaran (dipakai juga nanti oleh `FinPeriodGuard::assertCanClose()` via cross-DB read).

## 6. Auto-create periode (artisan command)
```php
// php artisan fin:period:generate {fiscal_year}
// Membuat 13 baris (1-12 bulan kalender + 13 adjustment), start_date/end_date dari kalender
// standar (bukan fiscal year custom — perusahaan cetak ini pakai tahun kalender Jan-Des,
// verifikasi asumsi ini ke user bila salah). Status default OPEN kecuali period_no=13
// (default ON_HOLD, diaktifkan manual saat dibutuhkan untuk jurnal penyesuaian audit).
// Idempotent: skip bila fiscal_year sudah ada baris.
```

## 7. Year-end closing — voucher penutup (SEBELUM periode 12 di-set CLOSED)
```php
// App\Services\Finance\YearEndClosingService::generateClosingVoucher(int $fiscalYear): FinVoucher
// 1. Hitung saldo bersih semua akun account_type IN (REVENUE, COGS, EXPENSE, OTHER_INCOME, OTHER_EXPENSE)
//    untuk seluruh voucher POSTED tahun berjalan (via v1_fin_trial_balance atau query langsung).
// 2. Buat 1 voucher journal GEN, tanggal = end_date periode 12:
//    - Baris per akun P&L: balik saldo ke 0 (debit akun kredit-normal, kredit akun debit-normal)
//    - 1 baris penyeimbang ke 322000 (Laba Tahun Berjalan) = net semua P&L
//    - Baris terpisah: pindahkan saldo 322000 (termasuk saldo TAHUN LALU bila ada) ke 321000
//      (Laba Ditahan) -- HANYA bila user konfirmasi (lihat UI §8, bukan otomatis tanpa review)
// 3. Voucher berstatus DRAFT dulu -- Direksi/Finance HARUS review & POST manual sebelum
//    periode 12 di-set CLOSED. TIDAK auto-post.
```
> Ini **bukan** proses otomatis penuh seperti D365 Year End Close routine — sengaja dibuat
> "usulan voucher DRAFT" dulu karena TMERP belum punya kepastian struktur P&L per
> profit_center/dimension yang cukup matang untuk auto-post tanpa review manusia.

## 8. UI — Layar Period Manager (baru, Sisi A: Direksi + Costing/Finance Officer)
| Elemen | Fungsi |
|---|---|
| Grid 13 baris per tahun fiskal | period_name, tanggal, status (badge OPEN hijau/ON_HOLD kuning/CLOSED abu terkunci) |
| Tombol ubah status | Modal wajib isi `reason`; ke CLOSED memicu `assertCanClose()` — kalau gagal, tampilkan pesan Inventory periode X belum closed, **jangan silent-fail** |
| Badge peringatan | Kalau coba CLOSED tapi ada voucher DRAFT bertanggal periode itu → tampilkan daftar voucher DRAFT tsb, blokir close sampai semua POSTED/CANCELLED (baris B tambahan, lihat catatan) |
| Tombol "Buat Voucher Penutup Tahun" | Muncul hanya di periode 12, generate draft dari §7, redirect ke layar Voucher untuk review |

### Catatan tambahan (B — validasi close, konsisten pola sisi lain di TMERP)
Selain gate ke `inv_period` (§1.4), `assertCanClose()` juga **wajib** menolak close bila masih
ada `fin_voucher` berstatus `DRAFT` dengan tanggal jatuh di periode tersebut — sama prinsip
dengan closing periode ATT (`TIER_1_ATTENDANCE.md` T1-12: "closing tidak boleh dipaksakan").
Tambahkan pengecekan ini ke §4 `assertCanClose()`.

---

## 9. TASK CLAUDE CODE (urut)

1. Verifikasi `StockPostingService::postDocument()` aktual (§5) — cek apakah period check
   sudah inline atau benar-benar belum ada.
2. Migration `tm2inv`: refactor/sambungkan `PeriodService::assertOpen()` ke `StockPostingService`
   (prasyarat wajib, bukan opsional — §1.5).
3. Migration `tm2acc`: `fin_period` + `fin_period_log` (§2-3).
4. Service `FinPeriodGuard` (§4) + `YearEndClosingService` (§7).
5. Sambungkan `FinPeriodGuard::assertOpen()` ke SEMUA titik tulis `fin_voucher`:
   Cash & Bank Journal manual entry, `JournalPostingService` (auto-jurnal PV/Stock Out),
   DP Invoice posting.
6. Artisan command `fin:period:generate` (§6).
7. Permission `FINACC_PERIOD_CLOSE` — grant Direksi + Costing/Finance Officer.
8. UI: layar Period Manager (§8), termasuk validasi DRAFT-voucher-blocking (catatan B).
9. Uji: (a) posting ke periode CLOSED ditolak; (b) close GL period ditolak selama inv_period
   bulan sama belum CLOSED; (c) close GL period ditolak selama masih ada voucher DRAFT
   di periode itu; (d) sekali CLOSED, tidak ada endpoint/tombol apa pun yang bisa mengubahnya;
   (e) year-end voucher draft dihasilkan benar (net P&L masuk 322000, lalu ke 321000 setelah
   direview & di-POST manual).

---

## 10. REMINDER UPDATE STATE (untuk Anda, bukan Claude Code)

- `financeaccounting_state.yaml` — tambah keputusan engine periode (model D365 3-status,
  CLOSED ireversibel, gate wajib ke Inventory) + artefak `financeaccounting_delta_period_close.md`.
- `inventory_state.yaml` — catat bahwa `PeriodService::assertOpen()` AKHIRNYA disambungkan
  (bukan lagi dorman) — ini prasyarat Finance, dampaknya lintas modul, wajib dicatat di kedua state.
