<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Bersih-bersih master mesin CETAK (instruksi user 2026-09-14: "Mesin cetak
// hanya 2 atau 3. Sisanya itu cara lama yang di sambungkan dengan nama orang").
//
// Temuan analisa:
// - prod_machine id 1-5 dibuat manual 19-27 Agu 2026 (master V2 bersih: punya
//   brand_model, max_speed_per_hour, division_id).
// - 37 baris sisanya dibuat 28 Agu 2026 hasil migrasi `tm2old.mst_machine`
//   (tertulis di kolom notes), termasuk 4 entri yang sebenarnya OPERATOR, bukan
//   mesin: KOMORI_GL440_FANDIK / _AGUS / _DIMAS / _YANTO -- keempatnya nol
//   kemunculan di 7.423 baris prod_card_process_legacy.machine_name.
// - Seluruh baris legacy TIDAK dipakai transaksi V2 (prod_part_process,
//   prd_wo_operation, rate, roster) -- hanya nempel di emp_work_center hasil seed.
//
// Yang dilakukan: mesin bertipe Cetak hasil migrasi legacy di-NONAKTIFKAN
// (activate=0), TIDAK dihapus -- baris emp_work_center dan histori tetap utuh,
// dan tinggal balik activate=1 kalau ternyata masih dipakai di lapangan.
// Tipe lain (Potong/Laminasi/Coating) SENGAJA tidak disentuh: duplikat legacy di
// sana masih jadi acuan routing produksi dan bukan yang dikeluhkan user.
return new class extends Migration
{
    private const OPERATOR_ROWS = [
        'KOMORI_GL440_FANDIK',
        'KOMORI_GL440_AGUS',
        'KOMORI_GL440_DIMAS',
        'KOMORI_GL440_YANTO',
    ];

    private const LEGACY_PRESS_ROWS = [
        'DIGITAL_SUNTHINKS',
        'FUJI',
        'HANWAY',
        'KOMORI_GL440',
        'KOMORI_L40',
        'SAKURA',
        'VERSAFIRE',
    ];

    // Bed maksimum = spesifikasi pabrikan dari brand_model yang sudah tercatat.
    // Dibutuhkan ProDev (validasi "canvas tidak boleh melebihi bed"); sebelum ini
    // kolomnya kosong di SEMUA mesin sehingga validasi memakai angka asumsi.
    private const BED = [
        'CETAK-01' => '74x53',   // Heidelberg Speedmaster SM 74 -- max sheet 740 x 530 mm
        'CETAK-02' => '72x52',   // Komori Lithrone L528 -- max sheet 720 x 520 mm
    ];

    // Builder BARU tiap pemanggilan -- kalau satu instance dipakai ulang,
    // where()-nya menumpuk (AND) dan update berikutnya tidak kena baris apa pun.
    private function machines()
    {
        return DB::connection('tm2mst')->table('prod_machine');
    }

    public function up(): void
    {
        $this->machines()->whereIn('machine_code', self::OPERATOR_ROWS)->update([
            'activate' => 0,
            'notes' => DB::raw("CONCAT('[NONAKTIF 2026-09-14] Entri ini OPERATOR mesin KOMORI GL440 (cara lama tm2old: 1 mesin dicatat berulang per nama orang), bukan mesin fisik terpisah.\n', COALESCE(notes, ''))"),
            'updated_by' => 'claude_code',
        ]);

        $this->machines()->whereIn('machine_code', self::LEGACY_PRESS_ROWS)->update([
            'activate' => 0,
            'notes' => DB::raw("CONCAT('[NONAKTIF 2026-09-14] Mesin cetak hasil migrasi tm2old, tidak dipakai transaksi V2. Mesin cetak aktif = CETAK-01 & CETAK-02.\n', COALESCE(notes, ''))"),
            'updated_by' => 'claude_code',
        ]);

        foreach (self::BED as $code => $size) {
            $this->machines()->where('machine_code', $code)->update([
                'max_paper_size' => $size,
                'updated_by' => 'claude_code',
            ]);
        }
    }

    public function down(): void
    {
        $this->machines()->whereIn('machine_code', array_merge(self::OPERATOR_ROWS, self::LEGACY_PRESS_ROWS))
            ->update(['activate' => 1, 'updated_by' => 'claude_code']);

        $this->machines()->whereIn('machine_code', array_keys(self::BED))
            ->update(['max_paper_size' => null, 'updated_by' => 'claude_code']);
    }
};
