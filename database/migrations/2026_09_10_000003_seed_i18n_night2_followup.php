<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// i18n Malam 2/7 lanjutan (docs/BACKLOG.md §13 baris #2): audit ulang
// main-menu.blade.php menemukan string tambahan yang lolos -- modal Edit
// profil/Ubah password, greeting home, hint keyboard, dsb. Ditambahkan
// terpisah dari migration night2_shell utama krn ditemukan saat proses
// audit berjalan (bukan direncanakan di awal sesi).
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['shell_profile_modal_title', 'Edit profil', 'Edit profile'],
            ['shell_not_available_yet', 'Belum tersedia', 'Not available yet'],
            ['shell_profile_change_photo', 'Ubah foto', 'Change photo'],
            ['shell_profile_saved', 'Profil tersimpan.', 'Profile saved.'],
            ['shell_profile_full_name', 'Nama lengkap', 'Full name'],
            ['shell_profile_email', 'Email', 'Email'],
            ['shell_profile_phone', 'No. telepon', 'Phone number'],
            ['shell_profile_role', 'Jabatan', 'Role'],
            ['shell_btn_save', 'Simpan', 'Save'],
            ['shell_password_modal_title', 'Ubah password', 'Change password'],
            ['shell_password_updated', 'Password diperbarui.', 'Password updated.'],
            ['shell_password_old_label', 'Password lama', 'Current password'],
            ['shell_password_old_placeholder', 'Password saat ini', 'Current password'],
            ['shell_password_new_label', 'Password baru', 'New password'],
            ['shell_password_new_placeholder', 'Minimal 8 karakter', 'Minimum 8 characters'],
            ['shell_password_confirm_label', 'Konfirmasi password baru', 'Confirm new password'],
            ['shell_password_confirm_placeholder', 'Ulangi password baru', 'Repeat new password'],
            ['shell_btn_save_password', 'Simpan password', 'Save password'],
            ['shell_user_fallback_name', 'Pengguna', 'User'],
            ['shell_help_f1_hint', 'Jalan pintas: tekan F1 di mana saja', 'Shortcut: press F1 anywhere'],
            ['shell_show_topbar', 'Tampilkan top bar', 'Show top bar'],
            ['shell_tab_close_title', 'Tutup', 'Close'],
            ['shell_home_greeting', 'Selamat datang,', 'Welcome,'],
            ['shell_home_intro', 'Pilih modul di <b>mega menu</b> atas — submenunya muncul di sidebar. Setiap form/grid terbuka sebagai <b>tab</b> di frame ini dan tetap terbuka meski Anda berpindah modul.', 'Pick a module from the <b>mega menu</b> above — its submenu appears in the sidebar. Every form/grid opens as a <b>tab</b> in this frame and stays open even when you switch modules.'],
            ['shell_home_hint', 'Ctrl+K cari cepat · <kbd>F1</kbd> bantuan · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> sembunyikan sidebar · <kbd>︿</kbd> sembunyikan top bar.', 'Ctrl+K quick search · <kbd>F1</kbd> help · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> hide sidebar · <kbd>︿</kbd> hide top bar.'],
            ['shell_theme_mode_dark', 'gelap', 'dark'],
            ['shell_theme_mode_light', 'terang', 'light'],
        ];

        $sys = DB::connection('tm2sys');

        foreach ($rows as [$key, $id, $en]) {
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'id', 'trans_key' => $key], ['trans_value' => $id]);
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'en', 'trans_key' => $key], ['trans_value' => $en]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $keys = [
            'shell_profile_modal_title', 'shell_not_available_yet', 'shell_profile_change_photo', 'shell_profile_saved',
            'shell_profile_full_name', 'shell_profile_email', 'shell_profile_phone', 'shell_profile_role', 'shell_btn_save',
            'shell_password_modal_title', 'shell_password_updated', 'shell_password_old_label', 'shell_password_old_placeholder',
            'shell_password_new_label', 'shell_password_new_placeholder', 'shell_password_confirm_label',
            'shell_password_confirm_placeholder', 'shell_btn_save_password', 'shell_user_fallback_name', 'shell_help_f1_hint',
            'shell_show_topbar', 'shell_tab_close_title', 'shell_home_greeting', 'shell_home_intro', 'shell_home_hint',
            'shell_theme_mode_dark', 'shell_theme_mode_light',
        ];
        $sys->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
