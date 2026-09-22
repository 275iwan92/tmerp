---
name: screen_notification_index
description: Notification Center pribadi — daftar notifikasi milik user login, filter tipe/status baca, tandai terbaca dengan redirect ke link terkait
---

# Notification Center

**File**: `resources/views/pages/notification/index.blade.php`
**Route**: `/notifications` -> name `notifications.index` (grup `auth`+`force.password`, tanpa gate modul — personal, bukan modul administratif)
**Koneksi DB utama**: sys (tm2sys, via `SysNotification`)
**Modul**: System & Setup

## Tujuan
Layar pribadi (bukan admin-only) menampilkan seluruh notifikasi sistem milik user yang sedang login — mis. info dokumen butuh approval, pendaftaran akun baru menunggu, dsb. Dibuka dari ikon lonceng 🔔 di topbar.

## Field & Kolom
Grid (list, server-side pagination via `HasGridPagination`):
- `search` (live-debounce 300ms) — cari title/message.
- `typeFilter` (select) — Semua/info/success/warning/error.
- `unreadOnly` (checkbox, live) — hanya tampilkan yang belum dibaca.
- Baris: dot warna sesuai `type`, title (bold jika belum dibaca + dot indikator), message, timestamp (format tanggal + relatif "diffForHumans"), "Buka →" jika punya `link`.
- Tombol toolbar "Tandai semua terbaca".

## Aksi / Method
- `notifications()` (computed) — query `SysNotification::forUser(login)` dengan search/type/unreadOnly filter, `orderByDesc('created_at')`, `paginate($this->perPage)` (dari trait `HasGridPagination`).
- `unreadCount()` (computed) — hitung notifikasi belum dibaca milik user.
- `markRead(int $id)` — set `is_read=true` pada notifikasi milik user (guard `forUser()`), redirect ke `link` bila ada.
- `markAllRead()` — set semua notifikasi belum dibaca milik user jadi `is_read=true`.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SysNotification` — tabel notifikasi (tm2sys). Scope `forUser(login)` membatasi ke user login saat ini. Sumber data ditulis dari banyak layar lain di seluruh aplikasi (mis. `screen_auth_register.md` menulis notifikasi ke admin saat ada pendaftaran baru).
Tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Klik baris notifikasi (`markRead`) langsung menandai terbaca DAN redirect ke `link` terkait jika ada — 1 aksi gabungan, bukan 2 langkah terpisah.
- Server-side pagination + WHERE (bukan load-semua-lalu-filter client-side) — mengikuti aturan `grid_performance` di `globalrules.yaml`.

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode.
