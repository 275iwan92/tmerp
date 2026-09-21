<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Daftar aset mesin nyata dari user (2026-09-21, 46 mesin) menggantikan isi
// tm2mst.prod_machine yang sebelumnya campuran seed dummy + hasil ETL tm2old.
// Kode mesin dibakukan: prefix jenis mesin (English) + nomor urut tanggal datang.
//
// Baris lama TIDAK dihapus, hanya activate=0: seluruh 42 baris lama dirujuk
// emp_work_center, sebagian juga prod_part_process & prd_wo_operation -- delete
// memutus histori routing/WO. 13 baris yang identitas mesinnya jelas sama
// (brand cocok) dipakai ulang id-nya supaya kaitan lama tetap benar.
return new class extends Migration
{
    // [kode, nama, jenis, brand_model, acquired_date, tahun, division_code, reuse_machine_id]
    private const MACHINES = [
        ['OFF-01', 'Sakurai Oliver 58', 'Offset Printing', 'Sakurai Oliver 58', null, 2000, 'OFFSET', 35],
        ['OFF-02', 'Komori GL440', 'Offset Printing', 'Komori GL440', '2015-09-27', 2015, 'OFFSET', 1],

        ['DIG-01', 'Hanway 2500B', 'Digital Printing', 'Hanway 2500B', '2020-04-07', 2020, 'DIGITAL', 13],
        ['DIG-02', 'Heidelberg Versafire', 'Digital Printing', 'Heidelberg Versafire', '2022-02-11', 2022, 'DIGITAL', 42],
        ['DIG-03', 'Sunthink SC645', 'Digital Printing', 'Sunthink SC645', '2022-07-26', 2022, 'DIGITAL', 8],
        ['DIG-04', 'Taimes T5S-512i-8H', 'Digital Printing', 'Taimes T5S-512I-8H', '2023-09-18', 2023, 'DIGITAL', null],
        ['DIG-05', 'Taimes Heater', 'Digital Printing', 'Taimes Heater', '2023-09-18', 2023, 'DIGITAL', null],
        ['DIG-06', 'Xenons', 'Digital Printing', 'Xenons', '2026-01-31', 2026, 'DIGITAL', null],

        ['CTP-01', 'AGFA Avalon C', 'CTP Plate Setter', 'AGFA Avalon C', '2016-07-09', 2016, 'OFFSET', null],

        ['CUT-01', 'Robdcut ERC-115', 'Paper Cutting', 'Robdcut ERC-115', null, 2008, 'OFFSET', null],
        ['CUT-02', 'Echo Guillotine', 'Paper Cutting', 'Echo', '2016-12-12', 2016, 'OFFSET', null],
        ['CUT-03', 'Guoxin Guillotine', 'Paper Cutting', 'Guoxin', '2023-12-07', 2023, 'OFFSET', 12],

        ['DIE-01', 'Ruian Die Cutter', 'Die Cutting', 'Ruian', null, 2015, 'OFFSET', null],
        ['DIE-02', 'Shanghai Die Cutter', 'Die Cutting', 'Shanghai', null, 2020, 'OFFSET', null],
        ['DIE-03', 'Daxiang Die Cutter', 'Die Cutting', 'Daxiang', '2021-10-13', 2021, 'OFFSET', null],
        ['DIE-04', 'Daxiang Auto Die Cutter', 'Die Cutting', 'Daxiang (otomatis)', '2023-08-11', 2023, 'OFFSET', null],

        ['PLT-01', 'Summa D60R #1', 'Cutting Plotter', 'Summa D60R', '2020-08-19', 2020, 'DIGITAL', 7],
        ['PLT-02', 'Summa D60R #2', 'Cutting Plotter', 'Summa D60R', '2020-09-21', 2020, 'DIGITAL', null],
        ['PLT-03', 'IECHO BK4', 'Cutting Plotter', 'IECHO BK4', '2021-03-05', 2021, 'DIGITAL', 6],
        ['PLT-04', 'SAGA SG-SC A3+ II Pro #1', 'Cutting Plotter', 'SAGA SG-SC A3+ II PRO', '2024-10-23', 2024, 'DIGITAL', null],
        ['PLT-05', 'SAGA SG-SC A3+ II Pro #2', 'Cutting Plotter', 'SAGA SG-SC A3+ II PRO', '2025-06-24', 2025, 'DIGITAL', null],
        ['PLT-06', 'Summa S1 D60', 'Cutting Plotter', 'Summa S1 D60', '2025-12-20', 2025, 'DIGITAL', null],
        ['PLT-07', 'SAGA SG-SC A3+ II Pro #3', 'Cutting Plotter', 'SAGA SG-SC A3+ II PRO', '2026-07-23', 2026, 'DIGITAL', null],
        ['PLT-08', 'SAGA SG-SC A3+ II Pro #4', 'Cutting Plotter', 'SAGA SG-SC A3+ II PRO', '2026-07-23', 2026, 'DIGITAL', null],
        ['PLT-09', 'SAGA Back Creasing', 'Cutting Plotter', 'SAGA Back Creasing', '2026-07-23', 2026, 'DIGITAL', null],

        ['FGL-01', 'Daxiang Folder Gluer', 'Folder Gluer', 'Daxiang', '2020-11-04', 2020, 'OFFSET', 9],
        ['FGL-02', 'YDM Folder Gluer', 'Folder Gluer', 'YDM', '2023-08-13', 2023, 'OFFSET', 10],

        ['LAM-01', 'Small Laminator', 'Laminating', null, null, 2009, 'OFFSET', null],
        ['LAM-02', 'Huawei Laminator', 'Laminating', 'Huawei', null, 2019, 'OFFSET', 22],
        ['LAM-03', 'Daxiang Laminator', 'Laminating', 'Daxiang', '2025-11-11', 2025, 'OFFSET', null],
        ['LAM-04', 'Daxiang Auto Laminator', 'Laminating', 'Daxiang (otomatis)', '2025-12-04', 2025, 'OFFSET', null],
        ['UVC-01', 'UV Coating', 'UV Coating', null, null, 2019, 'OFFSET', 41],
        ['WBC-01', 'Daxiang Water-Based Coater', 'Water-Based Coating', 'Daxiang', '2025-09-04', 2025, 'OFFSET', null],
        ['WAX-01', 'Waxing Machine (DIY)', 'Waxing', 'DIY', '2019-04-15', 2019, 'OFFSET', 23],
        ['AGL-01', 'Daxiang Animal Glue Applicator', 'Gluing', 'Daxiang', '2023-12-20', 2023, 'OFFSET', null],

        ['PRS-01', 'Daxiang Press', 'Pressing', 'Daxiang', '2021-11-10', 2021, 'OFFSET', null],
        ['SHR-01', 'Daxiang Shrink Wrapper', 'Shrink Wrapping', 'Daxiang', '2025-03-08', 2025, 'OFFSET', null],
        ['STR-01', 'Daxiang Stripper', 'Stripping', 'Daxiang', '2025-03-08', 2025, 'OFFSET', null],
        ['STR-02', 'Daxiang Blanking Stripper', 'Stripping', 'Daxiang', '2025-10-11', 2025, 'OFFSET', null],
        ['STI-01', 'Daxiang Stitcher #1', 'Stitching', 'Daxiang', '2020-11-25', 2020, 'OFFSET', null],
        ['STI-02', 'Daxiang Book Stitcher', 'Stitching', 'Daxiang', '2024-07-24', 2024, 'OFFSET', null],
        ['STI-03', 'Daxiang Stitcher #2', 'Stitching', 'Daxiang', '2025-10-27', 2025, 'OFFSET', null],
        ['SEA-01', 'Innovatec FR-900 #1', 'Seaming', 'Innovatec FR-900', '2023-10-01', 2023, 'DIGITAL', null],
        ['SEA-02', 'Innovatec FR-900 #2', 'Seaming', 'Innovatec FR-900', '2024-10-09', 2024, 'DIGITAL', null],

        ['CMP-01', 'Daxiang Screw Compressor', 'Support Equipment', 'Daxiang (screw)', '2025-05-31', 2025, 'OFFSET', null],
        ['FKL-01', 'Doosan Forklift 3 Ton', 'Support Equipment', 'Doosan 3 ton', '2021-12-16', 2021, 'OFFSET', null],
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $table) {
            $table->date('acquired_date')->nullable()->after('year_acquired')
                ->comment('Tanggal datang mesin; NULL bila hanya tahun yang diketahui (year_acquired tetap wajib diisi)');
        });

        // 18 jenis English menggantikan 6 jenis Indonesia -- nama terpanjang
        // "Water-Based Coating" (19) masih muat di 20, tapi disisakan ruang.
        DB::connection('tm2mst')->statement('ALTER TABLE prod_machine MODIFY machine_type VARCHAR(30) NULL');

        $mst = DB::connection('tm2mst');
        $divisions = $mst->table('org_division')->pluck('division_id', 'division_code');
        $now = now();
        $keepIds = [];

        // machine_code UNIQUE dan beberapa kode baru (mis. LAM-01) masih dipegang
        // baris lama yang bukan mesinnya: parkir SEMUA kode lama dulu supaya
        // tidak bentrok, kode aslinya disimpan untuk dicatat di notes nanti.
        $legacyCodes = $mst->table('prod_machine')->pluck('machine_code', 'machine_id');
        foreach ($legacyCodes as $id => $code) {
            $mst->table('prod_machine')->where('machine_id', $id)->update(['machine_code' => 'ZZOLD-'.$id]);
        }

        foreach (self::MACHINES as [$code, $name, $type, $brand, $date, $year, $divCode, $reuseId]) {
            $payload = [
                'division_id' => $divisions[$divCode],
                'machine_code' => $code,
                'machine_name' => $name,
                'machine_type' => $type,
                'brand_model' => $brand,
                'acquired_date' => $date,
                'year_acquired' => $year,
                'activate' => 1,
                'data_origin' => 'LIVE',
                'data_ready' => 1,
                'updated_at' => $now,
                'updated_by' => 'system:asset-list-2026',
            ];

            if ($reuseId && $mst->table('prod_machine')->where('machine_id', $reuseId)->exists()) {
                $mst->table('prod_machine')->where('machine_id', $reuseId)->update($payload);
                $keepIds[] = $reuseId;

                continue;
            }

            $payload['created_at'] = $now;
            $payload['created_by'] = 'system:asset-list-2026';
            $keepIds[] = $mst->table('prod_machine')->insertGetId($payload);
        }

        foreach ($legacyCodes as $id => $code) {
            if (in_array($id, $keepIds, true)) {
                continue;
            }

            $mst->table('prod_machine')->where('machine_id', $id)->update([
                'activate' => 0,
                'updated_at' => $now,
                'updated_by' => 'system:asset-list-2026',
                'notes' => trim(($mst->table('prod_machine')->where('machine_id', $id)->value('notes') ?? '')
                    ."\n[2026-09-21] Dinonaktifkan: tidak ada di daftar aset mesin resmi. Kode lama: {$code}."),
            ]);
        }
    }

    // Data lama (nama/kode/jenis hasil ETL tm2old) tidak dipulihkan di sini --
    // rollback data lewat git revert + restore dump, bukan lewat down().
    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $table) {
            $table->dropColumn('acquired_date');
        });

        DB::connection('tm2mst')->statement('ALTER TABLE prod_machine MODIFY machine_type VARCHAR(20) NULL');
    }
};
