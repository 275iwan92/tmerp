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
        // Teks JS yang dulu Indonesia mentah (form / sheet / grid / lines), dipindah 2026-10-02.
        'f_action_preview' => ['Aksi: {a} (pratinjau, tidak dikirim)', 'Action: {a} (preview, not sent)'],
        'f_nothing_undo' => ['Tidak ada perubahan untuk di-Undo.', 'There is nothing to undo.'],
        'f_discard_all' => ['Buang semua perubahan yang belum disimpan?', 'Discard all unsaved changes?'],
        'f_ask_delete' => ['Hapus data ini? Tidak bisa dikembalikan.', 'Delete this record? This cannot be undone.'],
        'f_ask_confirm' => ['Confirm dokumen ini? Setelah confirm isi terkunci dan masuk approval (bila ada).', 'Confirm this document? After Confirm it is locked and goes to approval (if any).'],
        'f_ask_cancel' => ['Batalkan dokumen ini?', 'Cancel this document?'],
        'f_ask_draft' => ['Kembalikan dokumen ini ke Draft? Isinya bisa diubah lagi dan harus di-Confirm ulang.', 'Return this document to Draft? It can be changed again and must be Confirmed again.'],
        'f_ask_approve' => ['Setujui dokumen ini di tingkat approval Anda? Keputusan ini final, tidak bisa dibatalkan.', 'Approve this document at your approval level? This decision is final and cannot be undone.'],
        'f_ask_reject' => ['Tolak dokumen ini? Keputusan ini final, tidak bisa dibatalkan (Rejected); pembuat bisa membuat ulang lewat Duplicate.', 'Reject this document? This decision is final and cannot be undone (Rejected); the creator can make it again with Duplicate.'],
        'f_ask_hold' => ['Tahan (Hold) dokumen ini? Status sementara: nanti tetap diputuskan Approve atau Reject.', 'Hold this document? A temporary status: it is still decided later with Approve or Reject.'],
        'f_ask_deactivate' => ['Nonaktifkan data ini? Data tidak muncul lagi di pilihan.', 'Deactivate this record? It will no longer appear in pick lists.'],
        'f_ask_activate' => ['Aktifkan kembali data ini?', 'Activate this record again?'],
        's_tip_date' => ['Pilih tanggal (Alt+Panah Bawah)', 'Pick a date (Alt+Down Arrow)'],
        's_tip_time' => ['Pilih jam (Alt+Panah Bawah)', 'Pick a time (Alt+Down Arrow)'],
        's_tip_calc' => ['Kalkulator (Alt+Panah Bawah)', 'Calculator (Alt+Down Arrow)'],
        's_no_changes' => ['Tidak ada perubahan.', 'No changes.'],
        'g_toggle_failed' => ['Gagal menyimpan status aktif.', 'Could not save the active status.'],
        'g_no_detail' => ['Tidak ada detail', 'No details'],
        'g_n_rows' => ['{n} baris', '{n} rows'],
        'g_row_added' => ['Baris ditambahkan.', 'Row added.'],
        'g_open_tx' => ['Buka transaksi', 'Open transactions'],
        'l_row_detail' => ['Detail baris ({cols})', 'Row details ({cols})'],
        'l_not_found' => ['Tidak ditemukan', 'Not found'],
        // Teks JS approval / calendar / master / notif / pick-matrix (dulu Indonesia mentah), dipindah 2026-10-02.
        'a_n_waiting' => ['{n} menunggu', '{n} waiting'],
        'a_held' => ['Ditahan', 'On hold'],
        'a_all_done' => ['Semua sudah diputuskan', 'Everything is decided'],
        'a_none_waiting' => ['Tidak ada dokumen yang menunggu approval Anda.', 'No document is waiting for your approval.'],
        'a_open_doc' => ['Buka dokumen', 'Open document'],
        'a_submitted_by' => ['Diajukan oleh', 'Submitted by'],
        'a_held_temp' => ['Ditahan sementara', 'On hold for now'],
        'a_by' => ['Oleh', 'By'],
        'a_still_decide' => ['Masih bisa diputuskan: Setujui atau Tolak.', 'It can still be decided: Approve or Reject.'],
        'a_flow' => ['Alur Approval', 'Approval Flow'],
        'a_waiting_you' => ['Menunggu Anda', 'Waiting for you'],
        'a_waiting' => ['Menunggu', 'Waiting'],
        'a_history' => ['Riwayat Dokumen', 'Document History'],
        'a_approve' => ['Setujui', 'Approve'],
        'a_reject' => ['Tolak', 'Reject'],
        'a_hold' => ['Tahan', 'Hold'],
        'a_msg_approve' => ['Dokumen diteruskan ke tahap berikutnya. Keputusan ini final, tidak bisa dibatalkan.', 'The document goes to the next step. This decision is final and cannot be undone.'],
        'a_msg_reject' => ['Dokumen dikembalikan ke pembuat. Keputusan ini final, tidak bisa dibatalkan.', 'The document goes back to its creator. This decision is final and cannot be undone.'],
        'a_msg_hold' => ['Status sementara: dokumen tetap di daftar dan nanti masih bisa Disetujui atau Ditolak.', 'A temporary status: the document stays in the list and can still be Approved or Rejected later.'],
        'c_moved' => ['"{title}" dipindah ke {when}.', '"{title}" moved to {when}.'],
        'c_all_day' => ['Sepanjang hari', 'All day'],
        'c_today_events' => ['Acara hari ini', 'Today\'s events'],
        'c_no_events_today' => ['Tidak ada acara hari ini.', 'No events today.'],
        'c_new_event' => ['Acara baru', 'New event'],
        'c_edit_event' => ['Ubah acara', 'Edit event'],
        'c_title_required' => ['Judul wajib diisi.', 'The title is required.'],
        'c_end_before_start' => ['Waktu selesai tidak boleh sebelum mulai.', 'The end time cannot be before the start.'],
        'c_delete_event' => ['Hapus acara "{title}"?', 'Delete the event "{title}"?'],
        'm_required' => ['Wajib diisi', 'Required'],
        'm_required_n' => ['{n} isian wajib belum diisi.', '{n} required fields are empty.'],
        'm_new_title' => ['{title} baru', 'New {title}'],
        'n_all_read' => ['Semua notifikasi ditandai dibaca.', 'All notifications marked as read.'],
        'n_unread' => ['{n} belum dibaca', '{n} unread'],
        'pm_none_picked' => ['Belum ada baris yang dipilih.', 'No line is picked yet.'],
        // Teks JS kanban / import / gantt (dulu Indonesia mentah), dipindah 2026-10-02.
        'k_move_to' => ['Pindah ke', 'Move to'],
        'k_move_tip' => ['Pindah ke... (tanpa drag)', 'Move to... (without dragging)'],
        'k_add_card' => ['Tambah kartu', 'Add card'],
        'k_add_col' => ['Tambah kolom', 'Add column'],
        'k_move_denied' => ['Tidak boleh pindah dari "{from}" ke "{to}".', 'Moving from "{from}" to "{to}" is not allowed.'],
        'k_wip_over' => ['Kolom "{col}" melewati batas WIP ({n}).', 'Column "{col}" is over its WIP limit ({n}).'],
        'k_act_moved' => ['memindahkan dari {from} ke {to}', 'moved from {from} to {to}'],
        'k_label_search' => ['Cari / buat label...', 'Search / create a label...'],
        'k_label_new' => ['Buat label "{label}"', 'Create label "{label}"'],
        'k_due_label' => ['Tenggat (tanggal & jam, WIB)', 'Due (date & time, WIB)'],
        'k_clear_due' => ['Hapus tenggat', 'Clear due date'],
        'k_checklist_title' => ['Judul checklist', 'Checklist title'],
        'k_add' => ['Tambah', 'Add'],
        'k_priority' => ['Prioritas', 'Priority'],
        'k_card_title' => ['Judul kartu', 'Card title'],
        'k_in_column' => ['di kolom', 'in column'],
        'k_desc_hint' => ['Tambahkan deskripsi yang lebih rinci...', 'Add a more detailed description...'],
        'k_del_item' => ['Hapus item', 'Delete item'],
        'k_add_item' => ['Tambah item', 'Add item'],
        'k_comments' => ['Komentar & Aktivitas', 'Comments & Activity'],
        'k_write_comment' => ['Tulis komentar...', 'Write a comment...'],
        'k_no_activity' => ['Belum ada aktivitas', 'No activity yet'],
        'k_del_card' => ['Hapus kartu', 'Delete card'],
        'k_ask_del_checklist' => ['Hapus checklist "{title}" beserta semua itemnya?', 'Delete the checklist "{title}" with all its items?'],
        'k_ask_del_card' => ['Hapus kartu "{title}"?', 'Delete the card "{title}"?'],
        'i_unmapped' => ['Kolom wajib belum dicocokkan: {cols}.', 'Required columns are not matched yet: {cols}.'],
        'i_ask_import' => ['Import {n} baris valid?', 'Import {n} valid rows?'],
        'i_ask_import_skip' => ['Import {n} baris valid ({bad} baris bermasalah dilewati)?', 'Import {n} valid rows ({bad} rows with problems are skipped)?'],
        'i_done' => ['{n} baris berhasil diimpor.', '{n} rows imported.'],
        'i_done_skip' => ['{n} baris berhasil diimpor, {bad} dilewati.', '{n} rows imported, {bad} skipped.'],
        'i_file_empty' => ['File kosong atau hanya berisi judul kolom.', 'The file is empty or has only column headings.'],
        'i_file_info' => ['{name} · {rows} baris data · {cols} kolom · pemisah "{delim}"', '{name} · {rows} data rows · {cols} columns · delimiter "{delim}"'],
        'i_err_required' => ['wajib diisi', 'required'],
        'i_err_number' => ['bukan angka', 'not a number'],
        'i_err_date' => ['format tanggal (DD/MM/YYYY atau YYYY-MM-DD)', 'date format (DD/MM/YYYY or YYYY-MM-DD)'],
        'i_err_lookup' => ['tidak ada di master', 'not in the master'],
        'i_bad' => ['bermasalah', 'with problems'],
        'i_line' => ['Baris', 'Line'],
        'i_go' => ['Import {n} baris valid', 'Import {n} valid rows'],
        'i_go_none' => ['Tidak ada baris valid', 'No valid rows'],
        'gt_qc_locked' => ['QC Hold terkunci sampai QA memutuskan.', 'QC Hold is locked until QA decides.'],
        'gt_edit_task' => ['Ubah Task', 'Edit Task'],
        'gt_code' => ['Kode', 'Code'],
        'gt_process' => ['Proses', 'Process'],
        'gt_moved' => ['{task} dipindah ke {res}.', '{task} moved to {res}.'],
        'gt_ask_submit' => ['Kirim {n} perubahan simulasi ke Master Schedule?', 'Send {n} simulation changes to the Master Schedule?'],
        'gt_submitted' => ['{n} perubahan dikirim ke Master Schedule.', '{n} changes sent to the Master Schedule.'],
        'gt_stat_all' => ['Total Task Aktif', 'Active Tasks'],
        'gt_sub_reset' => ['klik untuk reset sorotan', 'click to reset the highlight'],
        'gt_stat_none' => ['Belum Terjadwal', 'Not Scheduled'],
        'gt_stat_risk' => ['Berisiko Telat', 'At Risk of Delay'],
        'gt_sub_hi' => ['klik untuk sorot di board', 'click to highlight on the board'],
        'gt_stat_idle' => ['Resource Kosong >{n} jam', 'Resource Idle >{n} hours'],
        'gt_legend_progress' => ['garis dalam blok = progress', 'line inside a block = progress'],
        'gt_no_resource' => ['Tidak ada resource yang cocok dengan filter', 'No resource matches the filter'],
        'gt_mode_live' => ['Mode Live: jadwal hanya bisa dilihat.', 'Live mode: the schedule is view only.'],
        'gt_mode_sim' => ['Mode Simulasi: {n} perubahan belum dikirim ke jadwal Live sampai Submit. QC Hold terkunci sampai QA memutuskan.', 'Simulation mode: {n} changes are not sent to the Live schedule until Submit. QC Hold is locked until QA decides.'],
        'gt_conflict' => ['{task} bertabrakan dengan task lain di resource yang sama.', '{task} overlaps another task on the same resource.'],
        'gt_time' => ['Waktu', 'Time'],
        'gt_scheduled' => ['Terjadwal', 'Scheduled'],
        'k_description' => ['Deskripsi', 'Description'],
        // Teks JS komponen kecil (credit-card, shell, wizard, perm-matrix, signature, chat, side-menu, drill, field, org-chart, barcode, phone, pivot, slider), dipindah 2026-10-02.
        'cc_holder' => ['NAMA PEMEGANG', 'CARD HOLDER'],
        'cc_unknown' => ['Jenis kartu tidak dikenal', 'Unknown card type'],
        'cc_digits' => ['Nomor {brand} harus {n} digit', 'A {brand} number must have {n} digits'],
        'cc_invalid' => ['Nomor kartu tidak valid (cek kembali angkanya)', 'The card number is not valid (check the digits)'],
        'cc_exp_format' => ['Masa berlaku harus MM/YY', 'The expiry must be MM/YY'],
        'cc_exp_month' => ['Bulan berlaku 01–12', 'The expiry month is 01–12'],
        'cc_expired' => ['Kartu sudah kedaluwarsa', 'The card has expired'],
        'cc_cvv' => ['CVV harus {n} digit', 'The CVV must have {n} digits'],
        'sh_scroll_left' => ['Geser kiri', 'Scroll left'],
        'sh_scroll_right' => ['Geser kanan', 'Scroll right'],
        'sh_home_hint' => ['Modul Home. Pilih modul lain di atas untuk menampilkan submenunya di sini.', 'Home module. Pick another module above to show its submenu here.'],
        'sh_pal_none' => ['Tak ada yang cocok dengan “{q}”.', 'Nothing matches “{q}”.'],
        'sh_ai_open' => ['Baik, saya buka “{label}” untuk Anda.', 'All right, opening “{label}” for you.'],
        'sh_ai_none' => ['Saya belum menemukan objek yang cocok. Sebut nama komponen, mis. “login”, “grid”, atau “kanban”.', 'I could not find a matching object. Name a component, e.g. “login”, “grid” or “kanban”.'],
        'w_finish_preview' => ['Selesai: data terkumpul (pratinjau, tidak dikirim).', 'Done: the data is collected (preview, not sent).'],
        'w_change' => ['Ubah', 'Change'],
        'w_required_n' => ['{n} isian wajib di langkah "{step}" belum diisi.', '{n} required fields in step "{step}" are empty.'],
        'pmx_bulk_set' => ['{n} objek diset ke: {level}', '{n} objects set to: {level}'],
        'pmx_dirty' => ['{n} perubahan belum disimpan', '{n} unsaved changes'],
        'pmx_clean' => ['Tidak ada perubahan', 'No changes'],
        'sg_none' => ['Belum ada tanda tangan.', 'There is no signature yet.'],
        'sg_signed' => ['Sudah ditandatangani', 'Signed'],
        'sg_unsigned' => ['Belum ditandatangani', 'Not signed yet'],
        'ch_file_max' => ['File maksimal {n} MB.', 'A file can be {n} MB at most.'],
        'ch_file_empty' => ['File kosong tidak bisa dikirim.', 'An empty file cannot be sent.'],
        'ch_caption_hint' => ['Tambahkan keterangan lalu tekan kirim', 'Add a caption, then press send'],
        'ch_cancel_file' => ['Batalkan lampiran', 'Remove attachment'],
        'ch_signed_in' => ['Sedang login', 'Signed in'],
        'ch_other_convos' => ['Percakapan lain', 'Other conversations'],
        'ch_no_match' => ['Tidak ada yang cocok.', 'Nothing matches.'],
        'ch_nobody_online' => ['Belum ada orang lain yang login.', 'Nobody else is signed in yet.'],
        'ch_all_online' => ['Semua orang sedang login.', 'Everyone is signed in.'],
        'ch_pick' => ['Pilih percakapan', 'Pick a conversation'],
        'ch_pick_hint' => ['Daftar kiri berisi orang yang sedang login. Tekan + untuk chat dengan yang tidak sedang login.', 'The list shows people who are signed in. Press + to chat with someone who is not.'],
        'ch_online' => ['online', 'online'],
        'ch_offline' => ['tidak login', 'not signed in'],
        'ch_last_seen' => ['terakhir dilihat {when}', 'last seen {when}'],
        'ch_today_at' => ['hari ini {time}', 'today {time}'],
        'ch_yesterday' => ['Kemarin', 'Yesterday'],
        'ch_yesterday_at' => ['kemarin {time}', 'yesterday {time}'],
        'ch_photo' => ['Foto', 'Photo'],
        'ch_sending' => ['Mengirim…', 'Sending…'],
        'ch_sent' => ['Terkirim', 'Sent'],
        'ch_delivered' => ['Diterima', 'Delivered'],
        'ch_read' => ['Dibaca', 'Read'],
        'ch_failed' => ['Tidak terkirim', 'Not sent'],
        'ch_send_failed' => ['Pesan tidak terkirim. Coba lagi.', 'The message was not sent. Try again.'],
        'ch_load_failed' => ['Percakapan tidak bisa dimuat. Coba lagi.', 'The conversation could not be loaded. Try again.'],
        'ch_session_ended' => ['Sesi Anda berakhir. Login lagi untuk melanjutkan chat.', 'Your session has ended. Sign in again to keep chatting.'],
        'ch_earlier' => ['Pesan sebelumnya', 'Earlier messages'],
        'ch_back' => ['Kembali', 'Back'],
        'ch_download' => ['Unduh', 'Download'],
        'ch_new_from' => ['Pesan baru dari {name}', 'New message from {name}'],
        'lbl.chat_attach' => ['Lampirkan file', 'Attach file'],
        'lbl.chat_type' => ['Ketik pesan', 'Type a message'],
        'lbl.chat_message' => ['Pesan', 'Message'],
        'lbl.chat_not_signed_in' => ['Tidak sedang login', 'Not signed in'],
        'lbl.chat_back' => ['Kembali', 'Back'],
        'lbl.chat_case' => ['Ganti huruf: kecil / Kapital Tiap Kata / BESAR', 'Change case: lower / Title Case / UPPER'],
        'sm_fav_added' => ['{label} ditambahkan ke Favorit.', '{label} added to Favourites.'],
        'sm_fav_removed' => ['{label} dilepas dari Favorit.', '{label} removed from Favourites.'],
        'dr_n_rows' => ['{n} baris rincian', '{n} detail rows'],
        'dr_hint' => ['Klik 2x baris untuk melihat rinciannya', 'Double-click a row to see its details'],
        'fd_dur_invalid' => ['Durasi: Tanggal akhir tidak valid.', 'Duration: the end date is not valid.'],
        'fd_duration' => ['Durasi: {y} Tahun {m} Bulan', 'Duration: {y} Years {m} Months'],
        'oc_own_child' => ['Tidak bisa dipindah ke bawahannya sendiri.', 'It cannot be moved under its own subordinate.'],
        'oc_moved' => ['{a} kini di bawah {b}.', '{a} is now under {b}.'],
        'bc_invalid' => ['Barcode {format} tidak sah: {value}', 'Invalid {format} barcode: {value}'],
        'ph_landline' => ['telepon rumah/kantor', 'home/office phone'],
        'ph_mobile' => ['HP', 'mobile'],
        'ph_phone' => ['telepon', 'phone'],
        'ph_bad_pattern' => ['Nomor {what} {country} tidak sesuai pola, contoh {ex}', 'The {country} {what} number does not match the pattern, e.g. {ex}'],
        'ph_too_short' => ['Terlalu pendek: minimal {n} digit setelah {dial}', 'Too short: at least {n} digits after {dial}'],
        'pv_source_rows' => ['{n} baris sumber', '{n} source rows'],
        'sl_days' => ['{n} hari', '{n} days'],
        // Teks JS inti (mode developer, pintasan, alasan), dipindah 2026-10-02.
        'dev_pct' => ['{n}% objek dari library', '{n}% of the objects come from the library'],
        'dev_hover' => ['Arahkan mouse ke objek…', 'Point the mouse at an object…'],
        'dev_not_lib' => ['BUKAN dari library', 'NOT from the library'],
        'dev_element' => ['elemen', 'element'],
        'reason_required' => ['Alasan wajib diisi.', 'A reason is required.'],
        'sc_search' => ['Cari menu / perintah', 'Search a menu / command'],
        'sc_esc' => ['Tutup modal / drawer / popup', 'Close the modal / drawer / popup'],
        'sc_help' => ['Bantuan & dukungan (pedoman + tiket)', 'Help & support (guide + tickets)'],
        'sc_dev' => ['Mode Developer nyala / mati (admin)', 'Developer Mode on / off (admin)'],
        'sc_list' => ['Tampilkan daftar pintasan ini', 'Show this list of shortcuts'],
        // Toolbar Grid / Permission Matrix / Sheet: teks Blade yang dulu Indonesia mentah (2026-10-02).
        'g_group' => ['Grup', 'Group'], 'g_cols_shown' => ['Kolom tampil', 'Columns shown'], 'g_group_by' => ['Kelompokkan menurut', 'Group by'],
        'g_density' => ['Kerapatan', 'Density'], 'g_compact' => ['Rapat', 'Compact'], 'g_comfortable' => ['Longgar', 'Comfortable'],
        'g_lines_per_cell' => ['Baris per sel', 'Lines per cell'], 'g_auto' => ['Otomatis', 'Automatic'], 'g_fixed' => ['Tetap', 'Fixed'],
        'g_view_table' => ['Tampilan tabel', 'Table view'], 'g_view_cards' => ['Tampilan kartu', 'Card view'], 'g_selected' => ['dipilih', 'selected'],
        's_worksheet' => ['Lembar kerja', 'Worksheet'],
        's_help' => ['Ketik langsung untuk mengisi · Enter/F2 edit · Tab/panah pindah · Shift/drag pilih rentang · Ctrl+C / Ctrl+V dengan Excel · tarik kotak kecil di pojok seleksi untuk mengisi ke bawah · Ctrl+Z undo', 'Type to fill · Enter/F2 edit · Tab/arrows move · Shift/drag select a range · Ctrl+C / Ctrl+V with Excel · drag the small box at the selection corner to fill down · Ctrl+Z undo'],
        'pmx_module' => ['Modul', 'Module'], 'pmx_all_modules' => ['Semua modul', 'All modules'], 'pmx_set_shown' => ['Set semua yang tampil', 'Set all shown'],
        'pmx_object' => ['Objek', 'Object'], 'pmx_type' => ['Tipe', 'Type'], 'pmx_access' => ['Hak akses', 'Access'],
        // Dialog Email x-dl::doc-share: pemilih kontak + pesan yang dulu Indonesia mentah (2026-10-02).
        'ds_contacts' => ['Cari kontak', 'Find contact'],
        'ds_contacts_ph' => ['Nama, perusahaan atau e-mail (min. 2 huruf)', 'Name, company or e-mail (2 letters or more)'],
        'ds_contacts_hint' => ['Klik kontak untuk menambah ke kotak yang terakhir diklik:', 'Click a contact to add it to the box you clicked last:'],
        'ds_link_copied' => ['Link disalin.', 'Link copied.'],
        'ds_need_to' => ['Isi minimal satu alamat To.', 'Enter at least one To address.'],
        'ds_print_failed' => ['Print gagal', 'Print failed'],
        'ds_email_failed' => ['Email gagal', 'Email failed'],
        'ds_failed' => ['Gagal', 'Failed'],
        // Teks JS yang tertangkap setelah daftar kata penjaga diperluas (2026-10-02).
        'dev_class' => ['turunan', 'class'],
        'dev_source' => ['sumber data', 'data source'],
        'cf_title' => ['Konfirmasi', 'Confirm'],
        'cf_message' => ['Lanjutkan?', 'Continue?'],
        'cf_ok' => ['Ya, lanjutkan', 'Yes, continue'],
        'cf_reason' => ['Alasan', 'Reason'],
        'g_no_group' => ['Tanpa grup', 'No group'],
        'pmx_saved' => ['Hak akses grup disimpan.', 'Group access saved.'],
        'ph_read' => ['Terbaca', 'Read as'],
        'ph_local' => ['lokal', 'local'],
        's_save_failed' => ['Gagal menyimpan.', 'Could not save.'],
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
