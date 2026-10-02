<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;

// Teks JS layar v1 (resources/js: app-shell, component-library, dirty-guard, tmdieline) ditulis English dan diterjemahkan
// lewat window.TM_T (resources/views/partials/js-text.blade.php, kamus t()); terjemahan Indonesia = teks lama, supaya
// tampilan bahasa id tetap. Teks objek DevLib sendiri ada di Devlib\Lang::DICT (jalan tanpa database).
return new class extends Migration
{
    private const ID = [
        'This menu is not available for your account.' => 'Menu ini tidak tersedia untuk akun Anda.',
        'not available yet' => 'belum tersedia',
        'This screen is not built in V2 yet — display placeholder only.' => 'Layar ini belum digarap di V2 — placeholder tampilan saja.',
        'Enter remarks…' => 'Isi keterangan…',
        'LIST' => 'DAFTAR',
        'Column A' => 'Kolom A',
        'Column B' => 'Kolom B',
        '(no data yet — module coming)' => '(belum ada data — modul menyusul)',
        'All right, opening “:x” for you.' => 'Baik, saya buka “:x” untuk Anda.',
        'I have not found a matching object yet. Name a form/grid, e.g. “quotation” or “inquiry”.' => 'Saya belum menemukan objek yang cocok. Sebut nama form/grid, mis. “quotation” atau “inquiry”.',
        'All right, opening “:x”.' => 'Baik, saya buka “:x”.',
        'No matching dashboard yet. Try e.g. “sales”, “purchasing”, “inventory”.' => 'Belum ada dashboard yang cocok. Coba sebut mis. “sales”, “purchasing”, “gudang”.',
        'Select...' => 'Pilih...',
        ':n selected (:list)' => ':n terpilih (:list)',
        'Choose both dates.' => 'Pilih kedua tanggal.',
        'Duration: the end date is not valid.' => 'Durasi: tanggal akhir tidak valid.',
        'Duration: :y Years :m Months' => 'Durasi: :y Tahun :m Bulan',
        'Save changes?' => 'Simpan perubahan?',
        'This form has unsaved changes.' => 'Ada perubahan di form ini yang belum disimpan.',
        'The Save button is not available on this screen.' => 'Tombol Save tidak tersedia di layar ini.',
        'This type has no<br>dieline specification yet' => 'Tipe ini belum punya<br>spesifikasi dieline',
        'Choose a size / enter the dimensions<br>to see the dieline' => 'Pilih ukuran / isi dimensi<br>untuk melihat dieline',
    ];

    public function up(): void
    {
        Dictionary::seed(self::ID);
    }

    public function down(): void {}
};
