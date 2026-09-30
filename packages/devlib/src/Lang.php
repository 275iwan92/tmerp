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
        // Label objek DevLib (2026-10-01): ditulis English di komponen; pasangan Indonesia di sini supaya jalan tanpa database.
        'lbl.000' => ['No. WhatsApp aktif', 'Active WhatsApp no.'],
        'lbl.001' => ['Tambah baris (Enter di baris terakhir)', 'Add row (Enter on the last row)'],
        'lbl.002' => ['Semua Proses', 'All Processes'],
        'lbl.003' => ['Semua Resource', 'All Resources'],
        'lbl.004' => ['Dibagi ke HPP item (landed cost)', 'Allocated to item COGS (landed cost)'],
        'lbl.005' => ['Sesuai yang tercetak', 'As printed'],
        'lbl.006' => ['Kembali ke layar sebelumnya', 'Back to the previous screen'],
        'lbl.007' => ['Ubah password', 'Change password'],
        'lbl.008' => ['Chat di sisi kanan layar', 'Chat on the right side of the screen'],
        'lbl.009' => ['Pilih tanggal', 'Choose a date'],
        'lbl.010' => ['Pilih menu', 'Choose a menu'],
        'lbl.011' => ['Pilih aksi', 'Choose an action'],
        'lbl.012' => ['Hapus format', 'Clear formatting'],
        'lbl.013' => ['Batal pilih', 'Clear selection'],
        'lbl.015' => ['Kolom', 'Columns'],
        'lbl.016' => ['Password saat ini', 'Current password'],
        'lbl.017' => ['Tanggal lahir', 'Date of birth'],
        'lbl.018' => ['Hapus baris', 'Delete row'],
        'lbl.019' => ['Unduh baris gagal', 'Download failed rows'],
        'lbl.020' => ['Tarik untuk melebarkan', 'Drag to widen'],
        'lbl.021' => ['Export CSV = kolom yang sedang tampil (atur dulu lewat Kolom)', 'Export CSV = the visible columns (set them first via Columns)'],
        'lbl.022' => ['Halaman pertama', 'First page'],
        'lbl.023' => ['Nama lengkap', 'Full name'],
        'lbl.024' => ['Masuk DPP (kena PPN)', 'In tax base (subject to VAT)'],
        'lbl.025' => ['Halaman terakhir', 'Last page'],
        'lbl.026' => ['Nama ibu kandung', 'Mother\'s maiden name'],
        'lbl.027' => ['Pindahkan chat ke kanan', 'Move chat to the right'],
        'lbl.028' => ['Chat baru', 'New chat'],
        'lbl.029' => ['Chat baru (orang yang tidak sedang login)', 'New chat (people who are not logged in)'],
        'lbl.030' => ['Password baru', 'New password'],
        'lbl.031' => ['Tidak ada notifikasi', 'No notifications'],
        'lbl.032' => ['Jumlah baris', 'Number of rows'],
        'lbl.033' => ['Kode OTP dari WhatsApp', 'OTP code from WhatsApp'],
        'lbl.034' => ['Pilih tanggal & jam', 'Pick a date & time'],
        'lbl.035' => ['Pilih jam', 'Pick a time'],
        'lbl.036' => ['Kembalikan ke bawaan', 'Restore defaults'],
        'lbl.038' => ['Cari (Ctrl K)', 'Search (Ctrl K)'],
        'lbl.039' => ['Cari / perintah (Ctrl K)', 'Search / command (Ctrl K)'],
        'lbl.040' => ['Cari kartu', 'Search cards'],
        'lbl.041' => ['Cari kartu...', 'Search cards...'],
        'lbl.042' => ['Cari perintah', 'Search commands'],
        'lbl.043' => ['Cari perintah…', 'Search commands…'],
        'lbl.044' => ['Cari pegawai', 'Search employee'],
        'lbl.045' => ['Cari pegawai / jabatan...', 'Search employee / position...'],
        'lbl.046' => ['Cari acara', 'Search events'],
        'lbl.047' => ['Cari acara...', 'Search events...'],
        'lbl.048' => ['Cari menu', 'Search menu'],
        'lbl.049' => ['Cari menu…', 'Search menu…'],
        'lbl.050' => ['Cari nama', 'Search name'],
        'lbl.051' => ['Cari nama…', 'Search name…'],
        'lbl.052' => ['Cari objek', 'Search objects'],
        'lbl.053' => ['Cari objek…', 'Search objects…'],
        'lbl.054' => ['Cari orang', 'Search people'],
        'lbl.055' => ['Cari orang…', 'Search people…'],
        'lbl.056' => ['Cari...', 'Search...'],
        'lbl.057' => ['Pilih semua hasil', 'Select all results'],
        'lbl.059' => ['Kirim OTP', 'Send OTP'],
        'lbl.060' => ['Kirim foto', 'Send photo'],
        'lbl.061' => ['Kirim tautan reset', 'Send reset link'],
        'lbl.062' => ['Set semua', 'Set all'],
        'lbl.063' => ['Keluar aplikasi', 'Sign out'],
        'lbl.064' => ['Kirim pendaftaran', 'Submit registration'],
        'lbl.065' => ['Tukar menu dan chat', 'Swap menu and chat'],
        'lbl.066' => ['Nomor harus punya WhatsApp — kode OTP dikirim ke sana.', 'The number must have WhatsApp — the OTP code is sent there.'],
        'lbl.068' => ['Ketik nama form, grid, atau laporan…', 'Type a form, grid or report name…'],
        'lbl.069' => ['Garis bawah', 'Underline'],
        'lbl.070' => ['Username (otomatis dari email)', 'Username (automatic from e-mail)'],
        'lbl.071' => ['Username atau email', 'Username or e-mail'],
        'lbl.072' => ['Lihat kode layar ini', 'View this screen\'s code'],
        'lbl.073' => ['Kami cek keabsahan email lalu kirim tautan verifikasi.', 'We check that the e-mail is valid and then send a verification link.'],
        'lbl.074' => ['terisi dari email', 'filled from e-mail'],
        'lbl.075' => ['nama@perusahaan.com, ...', 'name@company.com, ...'],
        'lbl.076' => ['nama@telasih.co.id', 'name@telasih.co.id'],
        'lbl.077' => ['atau', 'or'],
        'lbl.row' => ['Baris', 'Row'],
        // Teks bawaan JS grid / lines / sheet / form (T / Tf), 2026-10-01.
        'g_rows_preview' => ['{n} baris (pratinjau)', '{n} rows (preview)'],
        'g_open_dbl' => ['Buka: {name} (klik 2x)', 'Open: {name} (double-click)'],
        'g_all' => ['Semua', 'All'],
        'g_selection' => ['Pilihan', 'Selection'],
        'g_per_column' => ['Per kolom', 'Per column'],
        'g_select_page' => ['Pilih semua di halaman', 'Select all on this page'],
        'g_open_hint' => ['Buka (klik 2x baris)', 'Open (double-click the row)'],
        'g_edit_hint' => ['Edit (klik 2x baris)', 'Edit (double-click the row)'],
        'g_select_row' => ['Pilih baris', 'Select row'],
        'g_open_detail' => ['Buka detail', 'Open detail'],
        'g_rows_n' => ['{n} baris', '{n} rows'],
        'g_no_match' => ['Tidak ada data yang cocok', 'No matching data'],
        'g_no_match_hint' => ['Ubah kata kunci, lepas filter, atau pilih tab lain.', 'Change the keyword, clear the filters or choose another tab.'],
        'g_empty_group' => ['(kosong)', '(empty)'],
        'g_add' => ['Tambah', 'Add'],
        'g_enter_add' => ['Enter = tambah', 'Enter = add'],
        'g_matched' => ['{n} baris cocok dari {total}', '{n} rows match out of {total}'],
        'g_first' => ['Halaman pertama', 'First page'],
        'g_prev' => ['Sebelumnya', 'Previous'],
        'g_next' => ['Berikutnya', 'Next'],
        'g_last' => ['Halaman terakhir', 'Last page'],
        'g_range' => ['[{from} – {to} dari {total}]', '[{from} – {to} of {total}]'],
        'g_search_chip' => ['Cari: “{q}”', 'Search: “{q}”'],
        'g_clear' => ['Lepas', 'Clear'],
        'g_clear_all' => ['Lepas semua', 'Clear all'],
        'g_select_all_n' => ['Pilih semua {n} hasil', 'Select all {n} results'],
        'g_all_selected' => ['Semua hasil terpilih', 'All results selected'],
        'g_first_field' => ['Isian pertama', 'First field'],
        'g_required_x' => ['{x} wajib diisi.', '{x} is required.'],
        'g_export_done' => ['Export {n} baris × {c} kolom (kolom yang tampil).', 'Exported {n} rows × {c} columns (visible columns).'],
        'l_delete_row_n' => ['Hapus baris {n}?', 'Delete row {n}?'],
        'l_primary' => ['✓ Utama', '✓ Primary'],
        'l_pick' => ['Pilih', 'Choose'],
        'l_yes' => ['Ya', 'Yes'],
        'l_no' => ['Tidak', 'No'],
        'l_search' => ['Cari...', 'Search...'],
        'l_delete_row' => ['Hapus baris', 'Delete row'],
        'l_no_rows' => ['Belum ada baris.', 'No rows yet.'],
        'l_click_x' => ['Klik {x}.', 'Click {x}.'],
        'l_sum' => ['Jumlah', 'Total'],
        'l_min_rows' => ['Minimal {n} baris terisi.', 'At least {n} filled rows.'],
        'l_required_cells' => ['{n} sel wajib belum diisi.', '{n} required cells are empty.'],
        's_filled' => ['{n} sel diisi otomatis.', '{n} cells filled automatically.'],
        's_delete_n' => ['Hapus {n} baris terpilih?', 'Delete {n} selected rows?'],
        's_bad' => ['{n} sel belum benar (wajib / kembar / angka / tanggal / jam / pilihan).', '{n} cells are not valid yet (required / duplicate / number / date / time / choice).'],
        's_saved' => ['{n} baris disimpan.', '{n} rows saved.'],
        's_pick_time' => ['Pilih Jam', 'Pick a time'],
        's_hour' => ['Jam', 'Hour'],
        's_unsaved' => ['{n} perubahan belum disimpan', '{n} unsaved changes'],
        's_rows_changed' => ['{n} baris · {c} berubah', '{n} rows · {c} changed'],
        'f_required_n' => ['{n} isian wajib belum diisi.', '{n} required fields are empty.'],
        'f_required' => ['Wajib diisi', 'Required'],
        'lbl.shell_intro1' => ['Pilih modul di <b>mega menu</b> atas — submenunya muncul di sidebar.', 'Choose a module in the top <b>mega menu</b> — its submenu appears in the sidebar.'],
        'lbl.shell_hint1' => ['Ctrl+K cari cepat · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> sembunyikan sidebar · <kbd>⤒</kbd> sembunyikan top bar.', 'Ctrl+K quick search · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> hide sidebar · <kbd>⤒</kbd> hide top bar.'],
        'lbl.shell_intro2' => ['Pilih modul di <b>menu kiri</b>; klik cabang untuk turun satu layer, <b>Back</b> untuk naik.', 'Choose a module in the <b>left menu</b>; click a branch to go down one layer, <b>Back</b> to go up.'],
        'lbl.shell_hint2' => ['Ctrl+K cari cepat · roda gigi di sebelah nama = Chat, Profile, Notifikasi, Menu Classic.', 'Ctrl+K quick search · the gear next to the name = Chat, Profile, Notifications, Classic Menu.'],
        'lbl.shell_tabs' => ['Setiap form/grid terbuka sebagai <b>tab</b> di frame ini dan tetap terbuka meski Anda berpindah modul.', 'Every form/grid opens as a <b>tab</b> in this frame and stays open when you switch modules.'],
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
