<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Maintenance Fase 1 (Master Data) di database sendiri tm2mnt (ddtmnt di situs mockup). Aset = mesin produksi, alat penunjang,
// kendaraan, fasilitas. Relasi ke tm2mst / tm2acc logis (tanpa FK lintas database); FK fisik hanya di dalam tm2mnt.
// Jalankan hanya dengan --env=mockup sampai rilis ke tm2*.
return new class extends Migration
{
    private function audit(Blueprint $t): void
    {
        $t->timestamp('created_at')->nullable();
        $t->string('created_by', 50)->nullable();
        $t->timestamp('updated_at')->nullable();
        $t->string('updated_by', 50)->nullable();
    }

    public function up(): void
    {
        $s = Schema::connection('tm2mnt');

        $s->create('mnt_code', function (Blueprint $t) {
            $t->id('id_code');
            $t->string('code_type', 20);            // COMPONENT | PROBLEM | CAUSE | ACTION
            $t->string('code', 40);
            $t->string('name', 120);
            $t->string('applies_to', 60)->nullable(); // null = semua jenis aset
            $t->integer('sort_order')->default(0);
            $t->boolean('activate')->default(true);
            $this->audit($t);
            $t->unique(['code_type', 'code']);
        });

        $s->create('mnt_asset', function (Blueprint $t) {
            $t->id('id_asset');
            $t->string('asset_code', 40)->unique();
            $t->string('asset_name', 150);
            $t->string('asset_class', 20);          // MACHINE | SUPPORT | VEHICLE | FACILITY
            $t->unsignedBigInteger('machine_id')->nullable()->unique();   // logis tm2mst.prod_machine
            $t->unsignedBigInteger('fixed_asset_id')->nullable()->index(); // logis tm2acc.fin_asset
            $t->string('location', 150)->nullable();
            $t->char('criticality', 1)->nullable();  // A | B | C
            $t->string('meter_type', 20)->default('NONE'); // RUN_HOURS | IMPRESSIONS | ODOMETER_KM | NONE
            $t->text('notes')->nullable();
            $t->boolean('activate')->default(true);
            $this->audit($t);
            $t->index('asset_class');
        });

        $s->create('mnt_watch_point', function (Blueprint $t) {
            $t->id('id_watch_point');
            $t->foreignId('asset_id')->constrained('mnt_asset', 'id_asset')->cascadeOnDelete();
            $t->string('component_code', 40)->nullable();
            $t->string('check_text', 200);
            $t->string('method_text', 150)->nullable();
            $t->decimal('normal_min', 14, 3)->nullable();
            $t->decimal('normal_max', 14, 3)->nullable();
            $t->string('measure_uom', 20)->nullable();
            $t->string('frequency', 10)->default('DAILY');          // SHIFT | DAILY | WEEKLY | MONTHLY
            $t->string('checked_by_role', 12)->default('OPERATOR'); // OPERATOR | TECHNICIAN
            $t->integer('sort_order')->default(0);
            $t->boolean('activate')->default(true);
            $this->audit($t);
        });

        $s->create('mnt_asset_part', function (Blueprint $t) {
            $t->id('id_asset_part');
            $t->foreignId('asset_id')->constrained('mnt_asset', 'id_asset')->cascadeOnDelete();
            $t->unsignedBigInteger('material_id');                  // logis tm2mst.mat_raw
            $t->string('component_code', 40)->nullable();
            $t->decimal('qty_per_use', 14, 3)->default(1);
            $t->decimal('min_stock_suggested', 14, 3)->nullable();
            $t->unsignedInteger('expected_life_days')->nullable();
            $t->boolean('is_critical')->default(false);
            $t->boolean('activate')->default(true);
            $this->audit($t);
            $t->unique(['asset_id', 'material_id']);
        });

        $s->create('mnt_document', function (Blueprint $t) {
            $t->id('id_document');
            $t->string('doc_no', 40)->unique();     // SOP-MNT-OFF-01, WI-MNT-OFF-02
            $t->string('kind', 20);                 // SOP | WI | MANUAL | DRAWING | VENDOR_CONTACT | OTHER
            $t->string('doc_set', 10)->nullable();  // OFF, DIE, ... (kelompok mesin)
            $t->string('title', 200);
            $t->string('revision', 10)->default('00');
            $t->string('status', 10)->default('DRAFT'); // DRAFT | APPROVED
            $t->string('file_location', 255)->nullable();
            $t->string('approved_by', 50)->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->boolean('activate')->default(true);
            $this->audit($t);
            $t->index(['kind', 'doc_set']);
        });

        $s->create('mnt_document_line', function (Blueprint $t) {
            $t->id('id_document_line');
            $t->foreignId('document_id')->constrained('mnt_document', 'id_document')->cascadeOnDelete();
            $t->unsignedInteger('seq_no');
            $t->string('section', 60)->default('Steps');
            $t->text('line_text');
            $this->audit($t);
        });

        $s->create('mnt_asset_document', function (Blueprint $t) {
            $t->id('id_asset_document');
            $t->foreignId('asset_id')->constrained('mnt_asset', 'id_asset')->cascadeOnDelete();
            $t->foreignId('document_id')->constrained('mnt_document', 'id_document')->cascadeOnDelete();
            $this->audit($t);
            $t->unique(['asset_id', 'document_id']);
        });

        $s->create('mnt_log', function (Blueprint $t) {
            $t->id('id_log');
            $t->string('doc_type', 20);
            $t->unsignedBigInteger('doc_id');
            $t->string('action', 40);
            $t->text('note')->nullable();
            $t->string('created_by', 50)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['doc_type', 'doc_id']);
        });

        $this->seedCodes();
    }

    // Katalog kode umum mesin cetak dan kemasan; English = bahasa dasar, tampilan lewat kamus.
    private function seedCodes(): void
    {
        $codes = [
            'COMPONENT' => ['FEEDER' => 'Feeder', 'DELIVERY' => 'Delivery unit', 'PRINT_UNIT' => 'Printing unit', 'INKING' => 'Inking system',
                'DAMPENING' => 'Dampening system', 'GRIPPER' => 'Gripper', 'CYLINDER' => 'Cylinder', 'ROLLER' => 'Roller',
                'VACUUM_PUMP' => 'Vacuum pump', 'AIR_SYSTEM' => 'Compressed air system', 'DRIVE_MOTOR' => 'Drive motor', 'BELT_CHAIN' => 'Belt / chain',
                'BEARING' => 'Bearing', 'BLADE_KNIFE' => 'Blade / knife / die', 'PNEUMATIC' => 'Pneumatic', 'HYDRAULIC' => 'Hydraulic',
                'ELECTRICAL' => 'Electrical', 'SENSOR' => 'Sensor', 'CONTROL_PANEL' => 'Control panel / PLC', 'HEATER_DRYER' => 'Heater / dryer / UV lamp',
                'GLUE_UNIT' => 'Glue unit', 'LUBRICATION' => 'Lubrication system', 'ENGINE' => 'Engine', 'BRAKE' => 'Brake',
                'TIRE_WHEEL' => 'Tire / wheel', 'MAST_FORK' => 'Mast / fork', 'BATTERY' => 'Battery', 'COOLING' => 'Cooling / air conditioning',
                'BODY' => 'Body / chassis', 'OTHER' => 'Other'],
            'PROBLEM' => ['NOT_START' => 'Does not start', 'STOPPED' => 'Stopped while running', 'DOUBLE_SHEET' => 'Double sheet',
                'MISFEED' => 'Misfeed', 'PAPER_JAM' => 'Paper jam', 'REGISTER_OFF' => 'Registration off', 'COLOR_VARIATION' => 'Color variation',
                'NOISE' => 'Abnormal noise', 'VIBRATION' => 'Vibration', 'OVERHEAT' => 'Overheating', 'OIL_LEAK' => 'Oil leak',
                'AIR_LEAK' => 'Air leak', 'LOW_PRESSURE' => 'Low pressure', 'ERROR_CODE' => 'Error code on panel', 'WORN' => 'Worn',
                'BROKEN' => 'Broken', 'DULL' => 'Dull (blade/die)', 'NOT_COOLING' => 'Not cooling', 'OTHER' => 'Other'],
            'CAUSE' => ['WEAR' => 'Normal wear', 'LACK_LUBRICATION' => 'Lack of lubrication', 'CONTAMINATION' => 'Dirt / contamination',
                'MISADJUSTMENT' => 'Misadjustment', 'OPERATOR_ERROR' => 'Operator error', 'ELECTRICAL_FAULT' => 'Electrical fault',
                'MATERIAL_DEFECT' => 'Part / material defect', 'OVERLOAD' => 'Overload', 'AGE_FATIGUE' => 'Age / fatigue',
                'POWER_SUPPLY' => 'Power supply problem', 'UNKNOWN' => 'Unknown', 'OTHER' => 'Other'],
            'ACTION' => ['REPLACE' => 'Replace part', 'REPAIR' => 'Repair', 'ADJUST' => 'Adjust / set', 'CLEAN' => 'Clean',
                'LUBRICATE' => 'Lubricate', 'TIGHTEN' => 'Tighten', 'CALIBRATE' => 'Calibrate', 'RESET' => 'Reset',
                'REWIRE' => 'Rewire', 'SHARPEN' => 'Sharpen', 'TEMP_FIX' => 'Temporary fix', 'OTHER' => 'Other'],
        ];
        $db = Schema::connection('tm2mnt')->getConnection();
        foreach ($codes as $type => $list) {
            $i = 0;
            foreach ($list as $code => $name) {
                $db->table('mnt_code')->insertOrIgnore(['code_type' => $type, 'code' => $code, 'name' => $name, 'sort_order' => $i += 10,
                    'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        $s = Schema::connection('tm2mnt');
        foreach (['mnt_log', 'mnt_asset_document', 'mnt_document_line', 'mnt_document', 'mnt_asset_part', 'mnt_watch_point', 'mnt_asset', 'mnt_code'] as $t) {
            $s->dropIfExists($t);
        }
    }
};
