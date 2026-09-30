<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Katalog model mesin yang ada di pasar (user 2026-10-01: "masukkan sebagai collection master mesin-mesin yang ada di
// market, nanti biar user yang pilih mesin mana yang saat ini digunakan"). Isi awal = riset online pond/plong China
// seri PYQ (nama lama) / ML (nama baru) + pond otomatis kelas 1050 — spek umum pabrikan, BUKAN spek mesin pabrik.
// prod_machine.machine_model_id = pilihan user; prod_machine.nickname = nama sehari-hari ("Plong 2", "Daxiang").
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_machine_model', function (Blueprint $t) {
            $t->id('id_machine_model');
            $t->string('model_code', 40)->unique()->comment('nama model di pasar, mis. ML-750');
            $t->string('alias_codes', 100)->nullable()->comment('nama lain/lama, mis. PYQ-401C');
            $t->string('machine_type', 50)->comment('jenis mesin, sama dengan prod_machine.machine_type');
            $t->string('drive', 10)->default('MANUAL')->comment('MANUAL | AUTO');
            $t->string('maker_hint', 150)->nullable()->comment('pabrikan/asal yang umum membuat model ini');
            $t->unsignedInteger('work_length_mm')->nullable()->comment('ukuran kerja (inner chase / lembar maks) panjang');
            $t->unsignedInteger('work_width_mm')->nullable()->comment('ukuran kerja lebar');
            $t->unsignedInteger('max_cut_length_mm')->nullable()->comment('ukuran pisau/potong maks panjang');
            $t->unsignedInteger('max_cut_width_mm')->nullable();
            $t->unsignedInteger('speed_per_hour')->nullable()->comment('kecepatan tipikal (tekan atau lembar per jam)');
            $t->decimal('max_rule_length_m', 6, 1)->nullable()->comment('panjang pisau maks (m)');
            $t->decimal('motor_kw', 6, 1)->nullable();
            $t->unsignedInteger('weight_kg')->nullable();
            $t->unsignedInteger('pressure_ton')->nullable();
            $t->string('source_note', 255)->nullable()->comment('sumber spek (riset online)');
            $t->boolean('activate')->default(true);
            $t->timestamps();
            $t->string('created_by', 50)->nullable();
            $t->string('updated_by', 50)->nullable();
        });

        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $t) {
            $t->string('nickname', 60)->nullable()->after('machine_name')->comment('nama yang dipakai sehari-hari di pabrik, mis. Plong 2, Daxiang');
            $t->unsignedBigInteger('machine_model_id')->nullable()->after('brand_model')->comment('model di pasar yang dipilih user');
            $t->foreign('machine_model_id')->references('id_machine_model')->on('prod_machine_model')->nullOnDelete();
        });

        $src = 'Riset online 2026-10-01 (spek umum pabrikan; Dayuan, Shenda, Exexport, Lidograf, Universal)';
        $row = fn (string $code, ?string $alias, string $drive, array $v) => [
            'model_code' => $code, 'alias_codes' => $alias, 'machine_type' => 'Die Cutting', 'drive' => $drive,
            'maker_hint' => $drive === 'AUTO' ? 'Daxiang / Dayuan / Masterwork (China)' : 'Ruian / Wenzhou / Shanghai (China)',
            'work_length_mm' => $v[0], 'work_width_mm' => $v[1], 'max_cut_length_mm' => $v[2], 'max_cut_width_mm' => $v[3],
            'speed_per_hour' => $v[4], 'max_rule_length_m' => $v[5], 'motor_kw' => $v[6], 'weight_kg' => $v[7], 'pressure_ton' => $v[8],
            'source_note' => $src, 'activate' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'system',
        ];
        DB::connection('tm2mst')->table('prod_machine_model')->insert([
            $row('ML-750', 'PYQ-401C', 'MANUAL', [750, 520, null, null, 1500, 15, 2.2, 2000, null]),
            $row('ML-930', 'PYQ-202C, PYQ-203C/203A', 'MANUAL', [930, 670, null, null, 1400, 25, 4, 3000, null]),
            $row('ML-1040', null, 'MANUAL', [1040, 720, 1000, 700, 1400, 28, 4, 3500, null]),
            $row('ML-1100', null, 'MANUAL', [1100, 800, null, null, 1200, 30, 4, 4500, null]),
            $row('ML-1200', null, 'MANUAL', [1200, 820, null, null, 1200, 32, 4, 5000, null]),
            $row('ML-1300', null, 'MANUAL', [1300, 920, null, null, 1000, 40, 5.5, null, null]),
            $row('AUTO-1050', 'MHK-1050, MK-1050', 'AUTO', [1050, 750, 1040, 740, 7000, null, 11, 17000, 300]),
        ]);

        // Pilihan SEMENTARA (user 2026-10-01: "untuk sementara pilihkan terbaik dalam master mesin untuk mesin plong yang
        // digunakan, nanti biar diganti sendiri oleh user"). "Plong 2" dipasangkan ke DIE-02 mengikuti nomor mesin; pisau
        // Master Card ALSAMSI 83,4 x 70,1 cm di PLONG 2 hanya muat di kelas ML-1100. Pond otomatis disebut "Daxiang".
        $models = DB::connection('tm2mst')->table('prod_machine_model')->get()->keyBy('model_code');
        $note = 'SEMENTARA 2026-10-01: model dipilihkan sistem dari katalog (spek umum pabrikan) — ganti di Master Mesin bila berbeda dengan mesin sungguhan.';
        foreach (['DIE-01' => ['Plong 1', 'ML-750'], 'DIE-02' => ['Plong 2', 'ML-1100'], 'DIE-03' => ['Plong 3', 'ML-930'], 'DIE-04' => ['Daxiang', 'AUTO-1050']] as $code => [$nick, $model]) {
            $m = $models[$model];
            DB::connection('tm2mst')->table('prod_machine')->where('machine_code', $code)->update([
                'nickname' => $nick, 'machine_model_id' => $m->id_machine_model,
                'max_sheet_length_mm' => $m->work_length_mm, 'max_sheet_width_mm' => $m->work_width_mm,
                'max_speed_per_hour' => $m->speed_per_hour, 'power_kw' => $m->motor_kw,
                'notes' => $note, 'updated_by' => 'system', 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $t) {
            $t->dropForeign(['machine_model_id']);
            $t->dropColumn(['nickname', 'machine_model_id']);
        });
        Schema::connection('tm2mst')->dropIfExists('prod_machine_model');
    }
};
