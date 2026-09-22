<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Master Proses naik kelas dari "cuma nama + warna gantt" jadi master routing
// sungguhan (permintaan user 2026-09-22). Kolom yang ditambah dipilih supaya 3
// konsumen hilir bisa berhenti menebak:
//   1. ProCost/CostingEngine  -> cost_basis + standard_rate + waste (fixed + pct)
//   2. Scheduling/MPS         -> default_setup_minutes, default_speed_per_hour,
//                                is_bottleneck, allow_parallel, default_manpower
//   3. ProDev/BOP routing     -> routing_seq, process_stage, requires_* tooling
//
// process_code BARU dan awalnya NULL: 24 baris lama dinamai bebas ("QC HITUNG",
// "LAMINATING GLOSSY") jadi kode diisi oleh migration berikutnya yang memang
// memetakan per baris, bukan di-generate buta dari nama. UNIQUE-nya karena itu
// dipasang sebagai unique index yang mengizinkan banyak NULL (perilaku MySQL).
return new class extends Migration
{
    private const STAGE_BY_NAME = [
        'CETAK' => 'PRESS',
        'QC CETAK' => 'PRESS',
        'LAMINASI' => 'POSTPRESS',
        'LAMINATING GLOSSY' => 'POSTPRESS',
        'LAMINATING DOFT' => 'POSTPRESS',
        'UV' => 'POSTPRESS',
        'VARNISH' => 'POSTPRESS',
        'HOT PRINT' => 'POSTPRESS',
        'FOIL STAMPING' => 'POSTPRESS',
        'EMBOSS' => 'POSTPRESS',
        'DEBOSS' => 'POSTPRESS',
        'POTONG' => 'PREPRESS',
        'PLONG' => 'FINISHING',
        'KETREK' => 'FINISHING',
        'KUPAS' => 'FINISHING',
        'KSS' => 'FINISHING',
        'LEM MESIN' => 'FINISHING',
        'STITCHING' => 'FINISHING',
        'JEMUR' => 'FINISHING',
        'HITUNG' => 'PACKING',
        'WRAPPING' => 'PACKING',
        'QC PACK' => 'QC',
        'QC' => 'QC',
        'QC HITUNG' => 'QC',
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_process', function (Blueprint $t) {
            $t->string('process_code', 10)->nullable()->after('id_process');
            $t->unsignedBigInteger('department_id')->nullable()->after('process_name')
                ->comment('FK org_department -- dept pemilik proses');
            $t->string('process_stage', 20)->nullable()->after('department_id')
                ->comment('PREPRESS|PRESS|POSTPRESS|FINISHING|PACKING|QC|LOGISTIC');
            $t->unsignedSmallInteger('routing_seq')->nullable()->after('process_stage')
                ->comment('Urutan default dalam routing; dipakai usulan BOP ProDev');
            $t->string('output_uom', 20)->nullable()->after('routing_seq')
                ->comment('Satuan hasil: LEMBAR|PCS|METER|KG|JAM');

            // Parameter standar -- jadi NILAI DEFAULT saat proses dipasang ke
            // routing/BOP. Override per mesin ada di prod_process_machine.
            $t->unsignedSmallInteger('default_setup_minutes')->nullable()->after('output_uom')
                ->comment('Make-ready standar (menit)');
            $t->decimal('default_speed_per_hour', 12, 2)->nullable()->after('default_setup_minutes')
                ->comment('Kapasitas standar per jam dalam output_uom');
            $t->decimal('default_waste_pct', 5, 2)->nullable()->after('default_speed_per_hour')
                ->comment('Spoilage berjalan (%)');
            $t->unsignedInteger('default_waste_fixed_qty')->nullable()->after('default_waste_pct')
                ->comment('Insheet make-ready TETAP; offset: waste = qty tetap + % berjalan');
            $t->decimal('default_tolerance_pct', 5, 2)->nullable()->after('default_waste_fixed_qty');
            $t->unsignedTinyInteger('default_manpower')->nullable()->after('default_tolerance_pct')
                ->comment('Jumlah kru standar per shift');

            // Ongkos -- basis WAJIB eksplisit; tanpa ini angka rate ambigu
            // (per jam vs per 1000 lembar beda 3 digit).
            $t->string('cost_basis', 20)->nullable()->after('default_manpower')
                ->comment('PER_HOUR|PER_1000|PER_PCS|PER_METER|LUMPSUM');
            $t->decimal('standard_rate', 18, 4)->nullable()->after('cost_basis')
                ->comment('Tarif standar dalam satuan cost_basis (IDR)');

            $t->boolean('requires_plate')->default(false)->after('standard_rate');
            $t->boolean('requires_die')->default(false)->after('requires_plate')
                ->comment('Butuh pisau/plong');
            $t->boolean('requires_tooling')->default(false)->after('requires_die')
                ->comment('Butuh tooling lain (mould, cliche, film)');
            $t->boolean('qc_required')->default(false)->after('requires_tooling')
                ->comment('Wajib lewat QC gate sebelum proses berikutnya');
            $t->boolean('is_bottleneck')->default(false)->after('qc_required')
                ->comment('Kapasitas penentu; diprioritaskan saat penjadwalan');
            $t->boolean('allow_parallel')->default(false)->after('is_bottleneck')
                ->comment('Boleh jalan di >1 mesin bersamaan untuk 1 WO');

            $t->unsignedBigInteger('default_supplier_id')->nullable()->after('allow_parallel')
                ->comment('FK logis sup_supplier; subkon langganan bila inhouse=0');
            $t->unsignedSmallInteger('subcon_lead_days')->nullable()->after('default_supplier_id');

            $t->unsignedInteger('min_lot_qty')->nullable()->after('subcon_lead_days');
            $t->unsignedInteger('max_lot_qty')->nullable()->after('min_lot_qty');
            $t->string('skill_code', 30)->nullable()->after('max_lot_qty')
                ->comment('Sertifikasi kru yang diperlukan');
            $t->text('description')->nullable()->after('skill_code');

            $t->unique('process_code', 'uq_prod_process_code');
            $t->index('department_id', 'ix_prod_process_dept');
            $t->index('process_stage', 'ix_prod_process_stage');
        });

        $this->backfill();
    }

    // Isi nilai awal yang bisa dipastikan dari data yang sudah ada -- sisanya
    // (rate, speed) dibiarkan NULL supaya jelas "belum diisi" dan tidak dipakai
    // engine sebagai angka palsu.
    private function backfill(): void
    {
        foreach (DB::connection('tm2mst')->table('prod_process')->get() as $p) {
            $name = strtoupper(trim((string) $p->process_name));
            DB::connection('tm2mst')->table('prod_process')
                ->where('id_process', $p->id_process)
                ->update([
                    'process_stage' => self::STAGE_BY_NAME[$name] ?? null,
                    'output_uom' => in_array($name, ['HITUNG', 'QC HITUNG', 'WRAPPING', 'QC PACK'], true) ? 'PCS' : 'LEMBAR',
                    'qc_required' => str_starts_with($name, 'QC'),
                    'requires_plate' => $name === 'CETAK',
                    'requires_die' => in_array($name, ['PLONG', 'KETREK'], true),
                    'requires_tooling' => in_array($name, ['EMBOSS', 'DEBOSS', 'HOT PRINT', 'FOIL STAMPING'], true),
                    'updated_by' => 'migration:enrich_process_master',
                ]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_process', function (Blueprint $t) {
            $t->dropUnique('uq_prod_process_code');
            $t->dropIndex('ix_prod_process_dept');
            $t->dropIndex('ix_prod_process_stage');
            $t->dropColumn([
                'process_code', 'department_id', 'process_stage', 'routing_seq', 'output_uom',
                'default_setup_minutes', 'default_speed_per_hour', 'default_waste_pct',
                'default_waste_fixed_qty', 'default_tolerance_pct', 'default_manpower',
                'cost_basis', 'standard_rate', 'requires_plate', 'requires_die',
                'requires_tooling', 'qc_required', 'is_bottleneck', 'allow_parallel',
                'default_supplier_id', 'subcon_lead_days', 'min_lot_qty', 'max_lot_qty',
                'skill_code', 'description',
            ]);
        });
    }
};
