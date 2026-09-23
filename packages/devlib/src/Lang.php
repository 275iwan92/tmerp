<?php

namespace Devlib;

// Kamus teks antarmuka baku (ID/EN) untuk komponen library. Satu tempat:
// ubah kata di sini = semua layar ikut. Aplikasi boleh menambah kamusnya sendiri
// lewat Lang::extend(); tmerp nanti menyambungkan ke Translator/sys_translation.
final class Lang
{
    private static array $extra = [];

    public const DICT = [
        'save' => ['Simpan', 'Save'], 'cancel' => ['Batal', 'Cancel'], 'close' => ['Tutup', 'Close'], 'delete' => ['Hapus', 'Delete'],
        'discard' => ['Buang perubahan', 'Discard changes'], 'processing' => ['Memproses…', 'Processing…'], 'search' => ['Cari', 'Search'],
        'dirty_title' => ['Ada perubahan belum disimpan', 'You have unsaved changes'],
        'dirty_msg' => ['Perubahan di layar ini belum disimpan. Simpan dulu sebelum pindah?', 'Changes on this screen are not saved. Save before leaving?'],
        'shortcuts' => ['Pintasan keyboard', 'Keyboard shortcuts'], 'created_by' => ['Dibuat', 'Created'], 'updated_by' => ['Diubah', 'Updated'],
        'by' => ['oleh', 'by'], 'no_access' => ['Anda tidak punya hak untuk aksi ini.', 'You are not allowed to do this.'],
        'welcome' => ['Selamat datang', 'Welcome'], 'language' => ['Bahasa', 'Language'],
    ];

    public static function extend(array $dict): void
    {
        self::$extra = array_merge(self::$extra, $dict);
    }

    public static function all(): array
    {
        return array_merge(self::DICT, self::$extra);
    }

    public static function t(string $key, ?string $fallback = null): string
    {
        $pair = self::all()[$key] ?? null;

        return $pair ? $pair[Locale::lang() === 'en' ? 1 : 0] : ($fallback ?? $key);
    }
}
