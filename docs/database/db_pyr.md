---
name: db_pyr
description: Payroll (tm2pyr) — database kosong, modul BELUM dibangun sama sekali (CLAUDE.md), meski desain enkripsi sudah dikunci
---

# Database: tm2pyr (koneksi Eloquent: `pyr`)

**Sumber DDL**: `docs/ddl/tm2pyr.sql` — **0 tabel** (dump kosong, hanya header/footer mysqldump).
**Jumlah tabel**: 0

## Status
Sesuai CLAUDE.md §2 & §7 (Finance): **modul Payroll BELUM diimplementasikan sama sekali** — bukan cuma soal enkripsi (yang memang requirement wajib untuk gaji/histori gaji), tapi seluruh skema tabel/model/service/UI 0%. Keputusan desain enkripsi SUDAH DIKUNCI (2026-09-03): kolom yang wajib terenkripsi (`basic_salary`+`net_salary`), lokasi kunci `VERBA_SANCTA` di `sys_constant` (tm2sys) — didokumentasikan di `docs/payroll_encryption_decision.md` — TAPI ini murni catatan desain, kunci sendiri sudah tersimpan di DB sbg prasyarat, belum ada 1 tabel/model Payroll pun yang dibuat.

## Daftar Tabel
Tidak ada (0 tabel).

## Catatan
Jangan mulai implementasi modul ini tanpa konfirmasi ulang ke user (CLAUDE.md §2 eksplisit: "jangan mulai implementasi sendiri tanpa konfirmasi desain — mana kolom, cara rotate key, dampak ke query/laporan yang butuh baca nilai asli").
