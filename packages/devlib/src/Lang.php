<?php

namespace Devlib;

// Kamus teks antarmuka baku (ID/EN) untuk komponen library. Satu tempat:
// ubah kata di sini = semua layar ikut. Aplikasi boleh menambah kamusnya sendiri
// lewat Lang::extend(); tmerp nanti menyambungkan ke Translator/sys_translation.
final class Lang
{
    private static array $extra = [];

    private static ?\Closure $translator = null;

    private static ?\Closure $jsDict = null;

    private static ?array $reverse = null;

    public const DICT = [
        'save' => ['Save', 'Save'], 'cancel' => ['Cancel', 'Cancel'], 'close' => ['Tutup', 'Close'], 'delete' => ['Delete', 'Delete'],
        'discard' => ['Undo', 'Undo'], 'processing' => ['Memproses…', 'Processing…'], 'search' => ['Cari', 'Search'],
        'dirty_title' => ['Ada perubahan belum disimpan', 'You have unsaved changes'],
        'dirty_msg' => ['Perubahan di layar ini belum disimpan. Simpan dulu sebelum pindah?', 'Changes on this screen are not saved. Save before leaving?'],
        'shortcuts' => ['Pintasan keyboard', 'Keyboard shortcuts'], 'created_by' => ['Dibuat', 'Created'], 'updated_by' => ['Diubah', 'Updated'],
        'by' => ['oleh', 'by'], 'no_access' => ['Anda tidak punya hak untuk aksi ini.', 'You are not allowed to do this.'],
        'welcome' => ['Selamat datang', 'Welcome'], 'language' => ['Bahasa', 'Language'],
        'today' => ['Hari ini', 'Today'], 'pick_date' => ['Pilih Tanggal', 'Pick a date'], 'print_lang' => ['Bahasa cetak', 'Print language'], 'printed' => ['Dicetak', 'Printed'],
        // x-dl::print-out
        'pr_preview' => ['Pratinjau Cetak', 'Print Preview'], 'pr_paper' => ['Ukuran', 'Paper'], 'pr_a4' => ['A4 potret', 'A4 portrait'], 'pr_a4l' => ['A4 lanskap', 'A4 landscape'],
        'pr_a5' => ['A5 lanskap', 'A5 landscape'], 'pr_group' => ['Kelompok', 'Group'], 'pr_none' => ['Tanpa', 'None'], 'pr_price' => ['Harga', 'Price'],
        'pr_with_price' => ['Dengan Harga', 'With Price'], 'pr_without_price' => ['Tanpa Harga', 'Without Price'], 'pr_ink' => ['Hemat Tinta', 'Ink Saver'],
        'pr_page' => ['Hal', 'Page'], 'pr_sheet' => ['lembar', 'sheet'], 'pr_printed_from' => ['Dicetak dari DevLib', 'Printed from DevLib'], 'pr_rows' => ['baris', 'rows'],
        'pr_no' => ['No', 'No'], 'pr_subtotal' => ['Subtotal', 'Subtotal'], 'pr_total' => ['Total', 'Total'], 'pr_discount' => ['Diskon', 'Discount'], 'pr_dpp' => ['DPP', 'Tax Base'],
        'pr_vat' => ['PPN', 'VAT'], 'pr_total_due' => ['Total Tagihan', 'Total Due'], 'pr_words' => ['Terbilang', 'Amount in words'],
        // x-dl::doc-share
        'ds_pdf_sub' => ['unduh, dengan QR', 'download, with QR'], 'ds_jpeg_sub' => ['unduh gambar, dengan QR', 'download image, with QR'],
        'ds_online_sub' => ['link + QR serah terima', 'link + QR for handover'], 'ds_printer_sub' => ['dialog cetak browser', 'browser print dialog'],
        'ds_online' => ['Dokumen online', 'Online document'], 'ds_link' => ['Link (tanpa login)', 'Link (no login)'],
        'ds_online_msg' => ['Salinan dokumen saat ini. Pindai QR atau kirim link-nya untuk serah terima.', 'Copy of the document as it is now. Scan the QR or send the link for handover.'],
        'ds_copy' => ['Salin link', 'Copy link'], 'ds_open' => ['Buka', 'Open'], 'ds_email' => ['Email dokumen', 'Email document'],
        'ds_to_hint' => ['Pisahkan beberapa alamat dengan koma.', 'Separate several addresses with commas.'], 'ds_subject' => ['Subjek', 'Subject'], 'ds_message' => ['Pesan', 'Message'],
        'ds_with_link' => ['Link dokumen online (QR)', 'Online document link (QR)'], 'ds_with_image' => ['Dokumen tampil di isi e-mail (gambar)', 'Document shown in the e-mail body (image)'],
        'ds_with_pdf' => ['Lampirkan PDF', 'Attach PDF'], 'ds_send' => ['Kirim', 'Send'],
        'print_unsaved' => ['Perubahan belum disimpan tidak ikut tercetak. Save dulu bila perlu.', 'Unsaved changes are not printed. Save first if needed.'],
    ];

    public static function extend(array $dict): void
    {
        self::$extra = array_merge(self::$extra, $dict);
        self::$reverse = null;
    }

    // Kamus aplikasi untuk browser (window.DL_DICT): fn(lang) => [teks English => terjemahan | ''].
    public static function dictUsing(?\Closure $fn): void
    {
        self::$jsDict = $fn;
    }

    public static function jsDict(): array
    {
        return self::$jsDict ? (self::$jsDict)(Locale::lang()) : [];
    }

    // Teks tampilan yang DITULIS ENGLISH (label layar/komponen, LOCKED user 2026-10-01) -> bahasa tampilan.
    // Kata DevLib sendiri dikenali dari DICT (jalan tanpa database, mis. devlib.test); selain itu lewat kamus aplikasi
    // (tmerp: sys_dictionary, yang juga mencatat teks baru otomatis). Tidak ada terjemahan -> English apa adanya.
    public static function text(?string $en): string
    {
        if ($en === null || trim($en) === '') {
            return (string) $en;
        }
        $lang = Locale::lang();
        $row = $lang === 'en' ? null : self::reverse()[$en] ?? null;
        if ($row && isset($row[$lang])) {
            return $row[$lang];
        }

        return self::$translator ? (self::$translator)($en, $lang) : $en;
    }

    private static function reverse(): array
    {
        if (self::$reverse === null) {
            self::$reverse = [];
            foreach (self::all() as $row) {
                $row = array_is_list($row) ? ['id' => $row[0], 'en' => $row[1]] : $row;
                if (isset($row['en'])) {
                    self::$reverse[$row['en']] ??= $row;
                }
            }
        }

        return self::$reverse;
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
