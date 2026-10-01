<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Layar Purchase Quotation v2: label Indonesia mentah diganti English (utang backlog09 §13 butir 1, ratchet id_label 31 -> 0).
// Teks Indonesianya yang lama diisi PERSIS ke kamus, jadi tampilan bahasa Indonesia tidak berubah. Hanya mengisi yang
// belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        'Compare Quotations' => 'Bandingkan Penawaran',
        'Unit price in IDR (after the rate). Ticked by default = the cheapest with the same unit as the PR; you may change it (e.g. faster lead time) or Skip.' => 'Harga per satuan dalam IDR (setelah kurs). Default tercentang = termurah dengan satuan sama dengan PR; boleh diganti (mis. lead time lebih cepat) atau Lewati.',
        'terms' => 'termin', 'until' => 's/d', 'Already ordered' => 'Sudah di-PO', 'Already ordered (PO)' => 'Sudah jadi PO', 'Unit differs from the PR' => 'Satuan beda dengan PR',
        'Create a Draft PO for the selected lines?' => 'Buat Draft PO untuk baris yang dipilih?',
        'Search PQ no., supplier, PR, file name...' => 'Cari no. PQ, supplier, PR, nama file...',
        'Reference PR' => 'PR Acuan', 'Valid Until' => 'Berlaku s/d', 'Reading Result' => 'Hasil Baca', 'Needs check' => 'Perlu cek', 'Closed (ordered)' => 'Closed (jadi PO)',
        'Compare quotations per PR' => 'Bandingkan penawaran per PR',
        'Choose a PR that has Confirmed PQs from several suppliers, then pick the winner per line.' => 'Pilih PR yang punya PQ Confirmed dari beberapa supplier, lalu pilih pemenang per baris.',
        'Upload the quotation file exactly as received from the supplier e-mail. One file = one Draft PQ; it is read and matched automatically, you only check it.' => 'Upload file penawaran persis seperti yang diterima dari email supplier. Satu file = satu PQ Draft; isinya dibaca & dicocokkan otomatis, Anda tinggal cek.',
        'The AI reader is not active (ANTHROPIC_API_KEY is not set in .env): Excel / CSV are still read automatically; PDF and photos become an empty Draft PQ to fill in by hand.' => 'Pembaca AI belum aktif (ANTHROPIC_API_KEY belum diisi di .env): Excel/CSV tetap dibaca otomatis, PDF & foto menjadi PQ Draft kosong yang diisi manual.',
        'Reference PR (optional)' => 'PR Acuan (opsional)', '— no PR (price survey) —' => '— tanpa PR (survei harga) —',
        'Quotation files (PDF, Excel, CSV, JPG, PNG — max. 10 files, 20 MB each)' => 'File penawaran (PDF, Excel, CSV, JPG, PNG — maks. 10 file, 20 MB/file)',
        '(entered by hand)' => '(diisi manual)',
        'lines marked ⚠ are not a sure match — choose the PR line / Material Master item, check qty, unit and price, then Confirm.' => 'baris bertanda ⚠ belum pasti cocok — pilih baris PR / item Master Bahan, cek qty, satuan & harga, lalu Confirm.',
        'supplier not chosen yet' => 'supplier belum dipilih', 'Quotation File' => 'File Penawaran',
        'Turn every line of this PQ into a Draft PO for this supplier?' => 'Jadikan semua baris PQ ini Draft PO ke supplier ini?',
        'PQ No.' => 'No. PQ', '(on Confirm)' => '(saat Confirm)', '— choose supplier —' => '— pilih supplier —', 'Quotation Date' => 'Tanggal Penawaran',
        'Supplier in the file' => 'Supplier di file', 'no PR' => 'tanpa PR', 'Payment Terms' => 'Termin Bayar', 'e.g. 30 days' => 'mis. 30 hari',
        'Rate to IDR' => 'Kurs ke IDR', 'Lead Time (days)' => 'Lead Time (hari)', 'No file' => 'Tanpa file',
        'This PQ was created without a quotation file.' => 'PQ ini dibuat tanpa file penawaran.', 'Cancel reason' => 'Alasan batal', '+ Manual row' => '+ Baris manual',
        'Text in the file' => 'Teks di file', 'Match' => 'Cocok', 'PR Line' => 'Baris PR', '— not from the PR —' => '— bukan dari PR —',
        'Material Master Item' => 'Item Master Bahan', 'Description (for the PO)' => 'Uraian (untuk PO)', 'Lead time (days)' => 'Lead time (hari)', 'Ordered (PO)' => 'Sudah jadi PO',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
