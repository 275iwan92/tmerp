---
name: screen_setup_support_tickets
description: Grid admin untuk mengelola tiket Help & Support — lihat/balas/ubah status tiket yang dikirim user dari Help Center
---

# Support Tickets (Admin)

**File**: `resources/views/pages/setup/support-tickets.blade.php`
**Route**: `/setup/support-tickets` -> name `setup.support-tickets` (middleware `admin`)
**Koneksi DB utama**: sys (tm2sys, via `SysSupportTicket`/`SysSupportReply`)
**Modul**: System & Setup

## Tujuan
Layar admin (dropdown profil main-menu) untuk mengelola tiket bantuan/bug report yang dikirim user dari Help Center (`resources/views/pages/help/index.blade.php`, tab "Tiket Bantuan"). Admin/support staff bisa lihat detail tiket + lampiran, membalas thread dua-arah dengan pelapor, dan mengubah status (In progress/Done/Closed). Label UI Bahasa Inggris — kebijakan baru untuk hal baru dibangun sesi tersebut (bukan rename retroaktif layar lain).

## Field & Kolom
Grid (paginated, 20/halaman):
- Kolom: Ticket (no.), Reported by, Screen (konteks layar, dipotong 30 char), Description (dipotong 50 char), Status (badge warna), Action (ikon ↗ Open).
- Filter: `search` (live-debounce 300ms, cari `ticket_no`/`description`), `statusFilter` (select: All/Open/In progress/Done/Closed).
- Stat box: Open, In progress, Done/closed (gabungan DONE+CLOSED).

Modal detail tiket (`selected`):
- Info: ticket_no, pelapor, screen_context, tanggal, deskripsi, lampiran screenshot (thumbnail).
- Thread balasan (`replies`) — bubble kronologis, dibedakan warna berdasarkan `author` sama dengan user login saat ini.
- `newReply` (textarea) — balasan baru dari admin.
- `resolution_note` (textarea) — catatan penyelesaian.

## Aksi / Method
- `updatedSearch()` — reset pagination saat search berubah.
- `stats()` (computed) — hitung Open/In progress/Done+Closed.
- `rows()` (computed) — query ticket dengan search+filter+pagination.
- `selected()` (computed) — tiket terpilih (`selectedId`), eager-load creator/attachments/replies.
- `open(int $id)` — set `selectedId`, load `resolution_note`, reset `newReply`.
- `addReply()` — buat `SysSupportReply` baru; balasan staff pada tiket berstatus OPEN otomatis mengubahnya jadi IN_PROGRESS (kecuali sudah DONE/CLOSED).
- `setStatus(string $status)` — update status + `resolution_note`; jika status DONE/CLOSED, isi `resolved_by`/`resolved_at`.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SysSupportTicket` — tabel tiket (tm2sys), relasi `creator`, `attachments`, `replies`.
- `App\Models\Sys\SysSupportReply` — tabel balasan thread (tm2sys) — dipakai pertama kali di sini (2026-08-30), dua arah dengan `resources/views/pages/help/index.blade.php`.
Tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Balasan staff otomatis memajukan status OPEN -> IN_PROGRESS (sinyal "sedang ditangani"), TIDAK menimpa status yang sudah DONE/CLOSED.
- Tiket ini adalah sisi admin dari alur dua-arah — user melihat & membalas balik dari Help Center (lihat `screen_help_index.md`), sebelumnya `sys_support_replies` ada di skema tapi tidak pernah dipakai sampai fitur balasan ini dibangun.
- Ini MENGGANTIKAN modal "Help & Support" lama di `main-menu.blade.php` (instruksi user 2026-08-30: gabung ticketing + Help Center).

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode.
