---
name: screen_main_menu
description: Shell utama aplikasi (app shell) -- mega menu modul, sidebar, tab kerja, notifikasi, chat, tema, dan menu profil.
---

# Main Menu (App Shell)

**File**: `resources/views/pages/main-menu.blade.php`
**Route**: `menu` (GET `/menu`, middleware `auth`+`force.password`)
**Koneksi DB utama**: sys (tm2sys) -- `sys_app_menu`, `sys_app_menu_color`, `sys_theme`, `sys_notifications`, `sys_chat_*`, `sys_user_menu`
**Modul**: System & Setup

## Tujuan
Ini adalah shell/kerangka utama aplikasi (bukan halaman biasa) -- semua modul lain (Sales, Procurement, Inventory, Production, HRM, Finance, Master Data, Setup) dibuka sebagai `<iframe>` di dalam tab pada halaman ini. Berisi: mega menu modul di topbar, sidebar kontekstual (isinya berubah sesuai modul yang dipilih), sistem tab kerja (multi-layar tetap terbuka), command palette (Ctrl+K), drawer Notifikasi & Chat, drawer "Asisten AI" (pratinjau/preview, pencocokan kata kunci client-side, bukan LLM sungguhan), picker tema (matrix 4 mode x 3 corak + toggle cepat terang/gelap), dan dropdown menu profil (edit profil, ubah password, logout, Configuration/Theme/Bahasa).

Digunakan oleh semua user yang sudah login. Menu yang tampil difilter oleh `SecUser::visibleAppMenuCodes()` (berbasis `sec_module`). Grup "Settings" dan "Security" di sidebar kiri bawah HANYA tampil untuk `Auth::user()->is_admin`.

## Field & Kolom
Bukan form CRUD biasa. Properti Livewire utama:
- `profile_name`, `profile_email`, `profile_phone` -- field modal "Edit profil" (wajib: name, email; phone opsional).
- `old_password`, `new_password`, `new_password_confirmation` -- field modal "Ubah password" (new_password: required, min:8, confirmed).
- `activeConversationId`, `chatInput` -- state drawer Chat.
- Data pohon menu (`menuTree`, `flatItems`) dibangun dari `sys_app_menu` (module/group/item hierarchy) + `itemRoutes()` (mapping kode menu -> nama route Laravel, hardcoded array besar).
- `themeMatrix` -- dari `sys_theme` (kolom `family`/`hue`/`mode`), dipakai render matrix picker tema.

## Aksi / Method
- `menuTree()`, `flatItems()` (Computed) -- bangun struktur menu dari `sys_app_menu` + route mapping.
- `brandName()`, `brandTagline()`, `brandLogoUrl()` (Computed) -- identitas aplikasi dari `App\Support\Branding\Brand` (bersumber `sys_constant`).
- `saveProfile()` -- update nama/email/phone user login.
- `savePassword()` -- ganti password (cek password lama dgn `Hash::check`, lalu `Hash::make` yang baru).
- `logout()` -- `Auth::logout()` + invalidate session, redirect ke `login`.
- `setTheme(string $code)` / `quickToggleTheme()` -- ganti `sec_user.theme_code`; toggle cepat pilih varian `_dark` dari tema aktif atau sebaliknya. Perlu `location.reload()` di sisi client karena `theme-vars.blade.php` dibaca ulang saat full page load.
- `markNotifRead(int $id)` / `markAllNotifRead()` -- tandai `sys_notifications` terbaca.
- `selectConversation(int $id)`, `sendMessage()` -- buka percakapan & kirim pesan (`sys_chat_message`/`sys_chat_participant`).
- `greetName()`, `roleLabel()` -- teks sapaan & label grup/role user.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SysAppMenu`, `SysAppMenuColor` -- struktur menu & warna status badge (NOT_BUILT/READY_FOR_QC/LOCKED).
- `App\Models\Sys\SysTheme` -- tema (family/hue/mode).
- `App\Models\Sys\SysNotification` -- notifikasi user (`forUser()`, `is_read`).
- `App\Models\Sys\SysChatConversation`, `SysChatMessage`, `SysChatParticipant` -- chat internal.
- `App\Models\Sys\SysUserMenu` -- item dropdown avatar (section "Pesan & Info"/"Akun"), data-driven dari DB.
- `App\Models\Sys\SecUser` -- user login (nama percakapan lawan bicara).
- `App\Support\Branding\Brand`, `App\Support\ActiveTheme` -- helper non-Eloquent baca `sys_constant`/tema aktif.
Semua tabel berada di 1 koneksi (`sys`/tm2sys), tidak ada query lintas-DB di file ini.

## Alur / Business Logic Penting
- Status badge module/group DIDERIVE dari status anak-anaknya (worst-case menang: ada 1 anak NOT_BUILT -> parent ikut NOT_BUILT), bukan disimpan sendiri di DB (`deriveStatus()`).
- Item menu yang tidak punya route mapping di `itemRoutes()` tetap tampil di menu tapi pakai placeholder pane (bukan iframe).
- Toggle tema butuh reload penuh halaman -- perubahan tema TIDAK bisa direfleksikan lewat re-render Livewire biasa.
- "Settings" (SET_CONFIG, is_admin only) dan grup "Security" (SET_USER/SET_GROUP/SET_PERM/SET_MIGRATION/SET_TICKETS, is_admin only) dipasak permanen di bagian bawah sidebar kiri, terpisah dari `menuTree` biasa.
- Drawer "Asisten AI" murni pratinjau client-side (pencocokan kata kunci ke `flatItems`), belum terhubung ke LLM sungguhan.

## Catatan
Tidak ada gap/TODO eksplisit di komentar kode untuk file ini di luar catatan historis migrasi menu (SALES_IMPO, MD_CURRENCY sempat broken link, sudah diperbaiki).
