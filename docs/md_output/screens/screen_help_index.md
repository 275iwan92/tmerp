---
name: screen_help_index
description: Help Center gabungan 3 tab — kirim/lihat tiket bantuan, panduan cara pakai UI/UX, dan penjelasan alur tiap modul
---

# Bantuan (Help Center)

**File**: `resources/views/pages/help/index.blade.php`
**Route**: `/bantuan` -> name `help.index` (grup `auth`+`force.password`, tanpa gate modul — semua user login boleh akses)
**Koneksi DB utama**: sys (tm2sys, via `SysSupportTicket`/`SysSupportReply`/`SysSupportTicketAttachment`) untuk tab Tiket; tab UI & Modul murni konten statis (tidak query DB)
**Modul**: System & Setup

## Tujuan
Satu tempat gabungan untuk bantuan pengguna (instruksi user 2026-08-30: "Combine Help and support yang ticketing and Help and support yang menjelaskan segala hal"), berisi 3 sub-menu (`tab`, `#[Url(as: 'tab')]`): **Tiket Bantuan** (submit tiket baru + riwayat + balas thread ke admin/developer — sisi user dari alur dua-arah dengan `screen_setup_support_tickets.md`), **Cara Pakai Aplikasi** (panduan UI/UX umum: navigasi, grid, form, kanban, gantt, cetak, notifikasi/chat/AI), dan **Modul** (penjelasan alur tiap modul bisnis: Sales, Procurement, Inventory, Production, HR, Master Data, Finance & Accounting, Setup & Security). Dibuka via hotkey F1 (`partials/f1-help-hotkey`, otomatis membawa konteks layar asal ke form tiket lewat `support_context`) atau link di dropdown profil, keduanya target="_blank". MENGGANTIKAN modal "Help & Support" lama di `main-menu.blade.php` (sekarang cuma link ke sini).

## Field & Kolom

### Tab 1 — Tiket Bantuan
Form kirim tiket baru: `support_context` (opsional, otomatis terisi dari `document.title` layar pemicu F1 lewat query param `ctx`, `#[Url(as: 'ctx')]`), `support_description` (textarea, wajib), `support_attachments` (multi-file image upload, preview thumbnail dengan tombol hapus per file).
Detail tiket terpilih: thread balasan (bubble kronologis, dibedakan posisi kiri/kanan berdasarkan `author`), `newReply` (textarea balasan balik ke tim support) — hanya muncul jika status BUKAN DONE/CLOSED.
Sidebar kanan: daftar "Tiket Saya" (list klik untuk buka detail, badge status, jumlah balasan).

### Tab 2 — Cara Pakai Aplikasi
Konten statis (blade markup), sidebar anchor-link ke 8 section: Navigasi Umum, Tabel/Grid, Fitur Grid Lanjutan, Form Isian, Papan Kanban, Papan Gantt, Cetak Dokumen, Notifikasi/Chat/AI. Ada kotak pencarian (`x-model="search"`, Alpine, filter client-side per section via `x-show` cek `innerText`).

### Tab 3 — Modul
Konten statis dari array PHP `$modules` (8 modul: Sales, Procurement, Inventory, Production, Human Resources, Master Data, Finance & Accounting, Setup & Security), tiap modul berisi `desc`, `steps` (alur bertahap), `note`. Kotak pencarian sama seperti Tab 2.

## Aksi / Method
- `myTickets()` (computed) — tiket milik `Auth::id()` (via `created_by_user_id`), eager-load `replies`.
- `selectedTicket()` (computed) — tiket terpilih dari `myTickets`.
- `openTicket(int $id)` — set `selectedTicketId`, reset `newReply`.
- `submitSupportTicket()` — validasi deskripsi tidak kosong, generate `ticket_no` format `SPT{YY}{MM}{seq4digit}`, buat `SysSupportTicket` + `SysSupportTicketAttachment` per file upload.
- `resetSupportForm()` — reset state setelah submit sukses, untuk kirim tiket lain.
- `addReply()` — user balas balik ke thread tiketnya sendiri (`SysSupportReply::create`).

## Tabel & Model yang Disentuh
- `App\Models\Sys\SysSupportTicket` — tabel tiket (tm2sys), filter `created_by_user_id = Auth::id()`.
- `App\Models\Sys\SysSupportReply` — thread balasan dua-arah (tm2sys), sama tabel dengan sisi admin `screen_setup_support_tickets.md`.
- `App\Models\Sys\SysSupportTicketAttachment` — lampiran screenshot (tm2sys), file disimpan ke disk `public` folder `support-tickets`.
Tab UI & Modul tidak menyentuh tabel apa pun — konten hardcode di blade.
Tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Balasan dari user hanya bisa dikirim jika status tiket BELUM DONE/CLOSED (`@unless` guard di blade) — tiket yang sudah selesai/ditutup terkunci dari balasan lanjutan.
- Nomor tiket dibuat dari counter per bulan (`whereYear`+`whereMonth` count + 1) — bukan auto-increment global, format `SPT{tahun 2 digit}{bulan 2 digit}{sequence 4 digit}`.
- Search di Tab 2/3 murni client-side (Alpine `x-show` mengecek `innerText`), tidak memanggil server.
- Footer selalu ada CTA "Kirim tiket ke tim support" yang langsung pindah ke Tab 1 (`$set('tab','tickets')`) dari tab mana pun.

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode.
