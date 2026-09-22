<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// MATRIKS KAPABILITAS PROSES x MESIN -- permintaan inti user 2026-09-22:
// "every machine have related with process. Every process pickup will show
// which machines would be operate."
//
// Kenapa tabel sendiri, bukan kolom machine_type di prod_process:
//   - Relasi ini many-to-many nyata. 1 proses (PLONG) bisa di 4 Die Cutter
//     + 9 Cutting Plotter; 1 mesin (DIE-04) bisa PLONG dan KETREK.
//   - Parameter produksi BEDA per pasangan, bukan per proses. Plong di
//     DIE-04 (otomatis) jauh lebih cepat & waste lebih kecil daripada di
//     DIE-01 manual. Tanpa baris per pasangan, scheduling & HPP salah begitu
//     officer memilih mesin lain dari usulan engine.
//   - Envelope fisik juga per pasangan: mesin yang sama bisa punya batas
//     ukuran berbeda untuk proses berbeda (plong butuh area pisau, cetak
//     butuh area gripper).
//
// Semua kolom parameter NULLABLE dengan sengaja: NULL = "pakai default proses"
// (prod_process.default_*), terisi = override khusus mesin ini. Itu yang bikin
// matriks bisa dipakai bertahap tanpa harus mengisi 200 sel sekaligus.
//
// prod_part_process_machine_rate (per product+part) TIDAK diganti -- itu lapis
// paling spesifik. Urutan resolusi rate jadi 3 lapis:
//   product+part+process+machine  ->  process+machine (tabel ini)  ->  process
return new class extends Migration
{
    // jenis mesin yang sah untuk tiap nama proses (data nyata 2026-09-22:
    // 24 proses x 18 jenis mesin). Sumber: daftar aset mesin resmi + praktik
    // lantai produksi. Pasangan yang tidak disebut sengaja dibiarkan kosong --
    // lebih baik officer menambah sendiri daripada matriks berisi tebakan.
    private const CAPABILITY = [
        'CETAK' => ['Offset Printing', 'Digital Printing'],
        'QC CETAK' => ['Offset Printing', 'Digital Printing'],
        'POTONG' => ['Paper Cutting'],
        'PLONG' => ['Die Cutting', 'Cutting Plotter'],
        'KETREK' => ['Die Cutting'],
        'LAMINASI' => ['Laminating'],
        'LAMINATING GLOSSY' => ['Laminating'],
        'LAMINATING DOFT' => ['Laminating'],
        'UV' => ['UV Coating'],
        'VARNISH' => ['Water-Based Coating'],
        'LEM MESIN' => ['Folder Gluer', 'Gluing'],
        'KUPAS' => ['Stripping'],
        'KSS' => ['Stripping'],
        'STITCHING' => ['Stitching'],
        'WRAPPING' => ['Shrink Wrapping'],
        'EMBOSS' => ['Pressing'],
        'DEBOSS' => ['Pressing'],
        'HOT PRINT' => ['Pressing'],
        'FOIL STAMPING' => ['Pressing'],
    ];

    // Mesin yang jelas lebih unggul untuk proses tertentu -> preference_rank 1.
    // Sisanya rank 2 (urut kode mesin). Rank dipakai picker mesin sebagai
    // usulan, bukan paksaan ("engine suggests, officer confirms").
    private const PREFERRED = [
        'CETAK' => ['OFF-02', 'DIG-02'],
        'PLONG' => ['DIE-04'],
        'POTONG' => ['CUT-03'],
        'LEM MESIN' => ['FGL-04'],
        'LAMINASI' => ['LAM-04'],
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_process_machine', function (Blueprint $t) {
            $t->id('capability_id');
            $t->unsignedBigInteger('process_id')->comment('FK prod_process');
            $t->unsignedBigInteger('machine_id')->comment('FK prod_machine');

            $t->unsignedTinyInteger('preference_rank')->default(2)
                ->comment('1 = mesin utama untuk proses ini; dipakai mengurutkan usulan');
            $t->boolean('is_certified')->default(false)
                ->comment('Pasangan sudah terbukti produksi (bukan cuma teoretis)');

            // Override parameter; NULL = pakai prod_process.default_*
            $t->unsignedSmallInteger('setup_minutes')->nullable();
            $t->decimal('speed_per_hour', 12, 2)->nullable();
            $t->decimal('waste_pct', 5, 2)->nullable();
            $t->unsignedInteger('waste_fixed_qty')->nullable();
            $t->decimal('hourly_rate', 18, 4)->nullable()
                ->comment('Tarif ongkos pasangan ini; NULL = pakai prod_machine.hourly_rate');
            $t->unsignedTinyInteger('manpower')->nullable();

            // Envelope khusus pasangan -- NULL = pakai batas mesin.
            $t->unsignedInteger('min_sheet_length_mm')->nullable();
            $t->unsignedInteger('min_sheet_width_mm')->nullable();
            $t->unsignedInteger('max_sheet_length_mm')->nullable();
            $t->unsignedInteger('max_sheet_width_mm')->nullable();
            $t->unsignedSmallInteger('gsm_min')->nullable();
            $t->unsignedSmallInteger('gsm_max')->nullable();

            $t->string('note', 255)->nullable();
            $t->boolean('activate')->default(true);
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->string('data_origin', 10)->default('LIVE')->comment('LIVE | DUMMY | LEGACY');
            $t->boolean('data_ready')->default(true);
            $t->boolean('data_origin_manual')->default(false);

            $t->unique(['process_id', 'machine_id'], 'uq_process_machine');
            $t->index(['machine_id', 'activate'], 'ix_ppm_machine');
            $t->index(['process_id', 'preference_rank'], 'ix_ppm_process_rank');

            // FK fisik: kedua induk ada di tm2mst (CLAUDE.md -- FK dalam-DB boleh).
            $t->foreign('process_id', 'fk_ppm_process')->references('id_process')
                ->on('prod_process')->cascadeOnDelete();
            $t->foreign('machine_id', 'fk_ppm_machine')->references('machine_id')
                ->on('prod_machine')->cascadeOnDelete();
        });

        $this->seedFromMachineTypes();
    }

    private function seedFromMachineTypes(): void
    {
        $now = now();
        $processes = DB::connection('tm2mst')->table('prod_process')->get()
            ->keyBy(fn ($p) => strtoupper(trim((string) $p->process_name)));
        $machines = DB::connection('tm2mst')->table('prod_machine')
            ->where('activate', 1)->orderBy('machine_code')->get();

        $rows = [];
        foreach (self::CAPABILITY as $processName => $types) {
            $process = $processes->get($processName);
            if (! $process) {
                continue;
            }

            foreach ($machines->whereIn('machine_type', $types) as $m) {
                $rows[] = [
                    'process_id' => $process->id_process,
                    'machine_id' => $m->machine_id,
                    'preference_rank' => in_array($m->machine_code, self::PREFERRED[$processName] ?? [], true) ? 1 : 2,
                    'is_certified' => true,
                    // Kecepatan mesin dipakai sebagai titik awal pasangan ini;
                    // officer menyesuaikan per proses nanti.
                    'speed_per_hour' => $m->max_speed_per_hour ?: null,
                    'activate' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:process_machine_capability',
                    'updated_by' => 'migration:process_machine_capability',
                    'data_origin' => 'LIVE',
                    'data_ready' => true,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::connection('tm2mst')->table('prod_process_machine')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_process_machine');
    }
};
