<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Master Mesin naik kelas jadi asset + capability register (permintaan user
// 2026-09-22). Tiga hal yang diperbaiki, bukan cuma "nambah kolom":
//
// 1. UKURAN JADI ANGKA. max_paper_size saat ini varchar "103x72" -- tidak bisa
//    dipakai query "mesin mana yang muat plano 79x109". ImpositionEngine &
//    picker mesin butuh angka, jadi ditambah *_sheet_*_mm dan diisi dengan
//    mem-parse string lama (string lama DIBIARKAN sebagai tampilan ringkas).
// 2. AREA CETAK != UKURAN LEMBAR. Gripper margin memakan sisi jepit; tanpa
//    max_print_*_mm layout bisa lolos validasi tapi kena jepitan di mesin.
// 3. STATUS OPERASIONAL != AKTIF/NONAKTIF. activate = mesin masih dimiliki
//    perusahaan (master aktif). machine_status = kondisi hari ini
//    (READY/MAINTENANCE/BREAKDOWN/...). Sebelumnya keduanya dipaksa jadi 1 flag
//    sehingga mesin rusak sehari harus di-nonaktifkan dari master -- itu
//    menghilangkannya dari picker routing & merusak histori.
return new class extends Migration
{
    // Tebakan ukuran hanya untuk mesin yang string-nya "PxL" jelas; sisanya NULL.
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $t) {
            $t->string('machine_status', 20)->default('READY')->after('activate')
                ->comment('READY|RUNNING|SETUP|MAINTENANCE|BREAKDOWN|IDLE|RETIRED -- kondisi hari ini, beda dari activate');
            $t->unsignedBigInteger('department_id')->nullable()->after('division_id')
                ->comment('FK org_department -- dept pengelola; division_id tetap untuk SBU');

            // --- Envelope fisik (angka, mm) ---
            $t->unsignedInteger('max_sheet_length_mm')->nullable()->after('max_paper_size');
            $t->unsignedInteger('max_sheet_width_mm')->nullable()->after('max_sheet_length_mm');
            $t->unsignedInteger('min_sheet_length_mm')->nullable()->after('max_sheet_width_mm');
            $t->unsignedInteger('min_sheet_width_mm')->nullable()->after('min_sheet_length_mm');
            $t->unsignedInteger('max_print_length_mm')->nullable()->after('min_sheet_width_mm')
                ->comment('Area cetak efektif, sudah dikurangi jepitan');
            $t->unsignedInteger('max_print_width_mm')->nullable()->after('max_print_length_mm');
            $t->unsignedSmallInteger('gripper_margin_mm')->nullable()->after('max_print_width_mm');

            // --- Kapabilitas cetak ---
            $t->boolean('perfecting')->default(false)->after('color_count')
                ->comment('Bisa cetak bolak-balik 1 lintasan');
            $t->unsignedTinyInteger('max_colors_per_pass')->nullable()->after('perfecting');
            $t->string('substrate_types', 255)->nullable()->after('max_colors_per_pass')
                ->comment('CSV jenis bahan yang bisa dijalankan, mis. ART,DUPLEX,KRAFT');

            // --- Performa: 3 angka kecepatan yang beda arti ---
            $t->unsignedInteger('speed_target_per_hour')->nullable()->after('max_speed_per_hour')
                ->comment('Target realistis rata-rata; max_speed_per_hour = klaim produsen');
            $t->decimal('oee_target_pct', 5, 2)->nullable()->after('speed_target_per_hour');
            $t->unsignedTinyInteger('shifts_per_day')->nullable()->after('capacity_per_shift');
            $t->decimal('hours_per_shift', 4, 1)->nullable()->after('shifts_per_day');
            $t->unsignedTinyInteger('operator_min')->nullable()->after('hours_per_shift')
                ->comment('Kru minimum agar mesin bisa jalan');

            // --- Biaya & aset ---
            $t->string('cost_center_code', 20)->nullable()->after('operator_min');
            $t->decimal('hourly_rate', 18, 4)->nullable()->after('cost_center_code')
                ->comment('Tarif BOP mesin per jam (IDR) untuk absorpsi HPP');
            $t->decimal('power_kw', 8, 2)->nullable()->after('hourly_rate');
            $t->decimal('acquisition_cost', 18, 2)->nullable()->after('power_kw');
            $t->unsignedTinyInteger('useful_life_years')->nullable()->after('acquisition_cost');
            $t->date('warranty_until')->nullable()->after('useful_life_years');

            // --- Perawatan (dasar; penjadwal preventif menyusul) ---
            $t->unsignedInteger('maintenance_interval_hours')->nullable()->after('warranty_until');
            $t->unsignedSmallInteger('maintenance_interval_days')->nullable()->after('maintenance_interval_hours');
            $t->date('last_maintenance_date')->nullable()->after('maintenance_interval_days');
            $t->date('next_maintenance_date')->nullable()->after('last_maintenance_date');
            $t->decimal('total_run_hours', 14, 2)->nullable()->after('next_maintenance_date');
            $t->unsignedBigInteger('total_impressions')->nullable()->after('total_run_hours');
            $t->string('service_vendor', 150)->nullable()->after('total_impressions');

            $t->char('criticality', 1)->nullable()->after('service_vendor')
                ->comment('A|B|C -- prioritas perawatan & dampak berhenti');
            $t->string('plant_code', 20)->nullable()->after('criticality');
            $t->string('location_note', 150)->nullable()->after('plant_code');
            $t->string('photo_path', 255)->nullable()->after('location_note');

            $t->index('machine_status', 'ix_prod_machine_status');
            $t->index('department_id', 'ix_prod_machine_dept');
            $t->index(['max_sheet_length_mm', 'max_sheet_width_mm'], 'ix_prod_machine_sheet_env');
        });

        $this->backfillSheetSizes();
    }

    // "103x72" / "103 x 72" / "1030x720" -> angka mm. Nilai < 300 dianggap cm
    // (kebiasaan operator menulis ukuran plano dalam cm) lalu dikali 10.
    private function backfillSheetSizes(): void
    {
        foreach (DB::connection('tm2mst')->table('prod_machine')->whereNotNull('max_paper_size')->get() as $m) {
            if (! preg_match('/(\d+(?:[.,]\d+)?)\s*[xX*]\s*(\d+(?:[.,]\d+)?)/', (string) $m->max_paper_size, $hit)) {
                continue;
            }

            $toMm = fn (string $raw): int => (int) round(
                ($v = (float) str_replace(',', '.', $raw)) < 300 ? $v * 10 : $v
            );

            $a = $toMm($hit[1]);
            $b = $toMm($hit[2]);

            DB::connection('tm2mst')->table('prod_machine')
                ->where('machine_id', $m->machine_id)
                ->update([
                    'max_sheet_length_mm' => max($a, $b),
                    'max_sheet_width_mm' => min($a, $b),
                    'machine_status' => $m->activate ? 'READY' : 'RETIRED',
                    'updated_by' => 'migration:enrich_machine_master',
                ]);
        }

        DB::connection('tm2mst')->table('prod_machine')
            ->where('activate', 0)->update(['machine_status' => 'RETIRED']);
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $t) {
            $t->dropIndex('ix_prod_machine_status');
            $t->dropIndex('ix_prod_machine_dept');
            $t->dropIndex('ix_prod_machine_sheet_env');
            $t->dropColumn([
                'machine_status', 'department_id',
                'max_sheet_length_mm', 'max_sheet_width_mm', 'min_sheet_length_mm',
                'min_sheet_width_mm', 'max_print_length_mm', 'max_print_width_mm',
                'gripper_margin_mm', 'perfecting', 'max_colors_per_pass', 'substrate_types',
                'speed_target_per_hour', 'oee_target_pct', 'shifts_per_day', 'hours_per_shift',
                'operator_min', 'cost_center_code', 'hourly_rate', 'power_kw',
                'acquisition_cost', 'useful_life_years', 'warranty_until',
                'maintenance_interval_hours', 'maintenance_interval_days',
                'last_maintenance_date', 'next_maintenance_date', 'total_run_hours',
                'total_impressions', 'service_vendor', 'criticality', 'plant_code',
                'location_note', 'photo_path',
            ]);
        });
    }
};
