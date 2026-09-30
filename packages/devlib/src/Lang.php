<?php

namespace Devlib;

// Kamus teks antarmuka baku (ID/EN) untuk komponen library. Satu tempat:
// ubah kata di sini = semua layar ikut. Aplikasi boleh menambah kamusnya sendiri
// lewat Lang::extend(); tmerp nanti menyambungkan ke Translator/sys_translation.
final class Lang
{
    private static array $extra = [];

    private static ?\Closure $translator = null;

    public const DICT = [
        'save' => ['Save', 'Save'], 'cancel' => ['Cancel', 'Cancel'], 'close' => ['Tutup', 'Close'], 'delete' => ['Delete', 'Delete'],
        'discard' => ['Undo', 'Undo'], 'processing' => ['Memproses…', 'Processing…'], 'search' => ['Cari', 'Search'],
        'dirty_title' => ['Ada perubahan belum disimpan', 'You have unsaved changes'],
        'dirty_msg' => ['Perubahan di layar ini belum disimpan. Simpan dulu sebelum pindah?', 'Changes on this screen are not saved. Save before leaving?'],
        'shortcuts' => ['Pintasan keyboard', 'Keyboard shortcuts'], 'created_by' => ['Dibuat', 'Created'], 'updated_by' => ['Diubah', 'Updated'],
        'by' => ['oleh', 'by'], 'no_access' => ['Anda tidak punya hak untuk aksi ini.', 'You are not allowed to do this.'],
        'welcome' => ['Selamat datang', 'Welcome'], 'language' => ['Bahasa', 'Language'],
        'today' => ['Hari ini', 'Today'], 'pick_date' => ['Pilih Tanggal', 'Pick a date'], 'print_lang' => ['Bahasa cetak', 'Print language'],
    ];

    public static function extend(array $dict): void
    {
        self::$extra = array_merge(self::$extra, $dict);
    }

    public static function all(): array
    {
        return array_merge(self::DICT, self::$extra);
    }

    // Bahasa selain id/en (ja, zh, ...): teks English entri ini diterjemahkan lewat kamus aplikasi (tmerp: sys_dictionary).
    public static function translateUsing(?\Closure $fn): void
    {
        self::$translator = $fn;
    }

    // Entri = [ID, EN] atau ['id' => .., 'en' => .., 'ja' => ..]. Tidak ada terjemahan -> English.
    public static function t(string $key, ?string $fallback = null): string
    {
        $row = self::all()[$key] ?? null;
        if (! $row) {
            return $fallback ?? $key;
        }
        $lang = Locale::lang();
        $row = array_is_list($row) ? ['id' => $row[0], 'en' => $row[1]] : $row;
        $en = $row['en'] ?? reset($row);

        return $row[$lang] ?? (self::$translator ? (self::$translator)($en, $lang) : $en);
    }
}
