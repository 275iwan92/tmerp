<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Lapis kedua Master Mesin & Master Proses (2026-09-22). Dua sumber:
//
// A. MENUTUP SISA FIELD DI SAMPEL HTML user yang belum ada:
//    mesin  -> plate_size, drying_system, mtbf_hours, helper_min
//    proses -> washup_minutes, work_center_id, qc_blocking
//    Catatan desain: sampel memisah "Required Operator" dan "Required
//    Helpers". Dipertahankan terpisah karena tarif upah keduanya beda dan
//    Payroll butuh pecahannya; total kru = operator_min + helper_min.
//
//    washup_minutes penting khusus offset: cuci blanket/roller setelah job
//    TIDAK boleh dicampur ke setup job berikutnya (setup = sebelum jalan,
//    washup = sesudah). Menggabungkannya membuat jadwal mesin cetak molor
//    sistematis di akhir hari.
//
// B. YANG BELUM ADA DI SAMPEL SAMA SEKALI -- inti "lebih enterprise":
//    1. prod_routing_template + _step: urutan operasi standar per kategori
//       produk. Sidebar sampel menjanjikan "Master Proses & Routing" tapi
//       tidak punya pembangun urutan apa pun. Tanpa ini tiap BOP disusun
//       ulang dari nol per part.
//    2. prod_process_precedence: aturan proses mana wajib/terlarang setelah
//       proses mana (LAMINASI sesudah CETAK, PLONG tidak boleh sebelum
//       CETAK). Jadi validator routing, bukan sekadar catatan.
//    3. prod_machine_changeover: setup nyata tergantung TRANSISI, bukan cuma
//       mesinnya. Ganti 4 warna ke 1 warna + ganti bahan jauh lebih lama
//       daripada job sejenis berturutan. Ini yang membuat jadwal offset
//       realistis.
//    4. prod_machine_downtime: catatan stop mesin (rencana & tak terencana).
//       Sampel hanya punya angka "Target OEE" statis; tanpa log downtime,
//       OEE aktual tidak bisa dihitung dan angka target itu tak terpakai.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $t) {
            $t->string('plate_size', 50)->nullable()->after('gripper_margin_mm')
                ->comment('Ukuran plate terpasang, mis. 790x1030 mm');
            $t->string('drying_system', 30)->nullable()->after('plate_size')
                ->comment('IR|UV|HOT_AIR|NONE');
            $t->unsignedInteger('mtbf_hours')->nullable()->after('oee_target_pct')
                ->comment('Mean Time Between Failures (jam) -- acuan interval perawatan');
            $t->unsignedTinyInteger('helper_min')->nullable()->after('operator_min')
                ->comment('Pembantu; total kru = operator_min + helper_min (upah beda, Payroll butuh pecahannya)');
        });

        Schema::connection('tm2mst')->table('prod_process', function (Blueprint $t) {
            $t->unsignedSmallInteger('washup_minutes')->nullable()->after('default_setup_minutes')
                ->comment('Cuci/bersih SESUDAH job; jangan dicampur ke setup job berikutnya');
            $t->unsignedBigInteger('work_center_id')->nullable()->after('department_id')
                ->comment('FK emp_work_center -- work center default');
            $t->boolean('qc_blocking')->default(false)->after('qc_required')
                ->comment('1 = proses berikutnya DIKUNCI sampai QC lulus');

            $t->index('work_center_id', 'ix_prod_process_wc');
        });

        // ---------- 1. Routing template ----------
        Schema::connection('tm2mst')->create('prod_routing_template', function (Blueprint $t) {
            $t->id('template_id');
            $t->string('template_code', 20)->unique();
            $t->string('template_name', 150);
            $t->string('prod_type_code', 20)->nullable()
                ->comment('FK logis prod_type; NULL = berlaku umum');
            $t->unsignedBigInteger('category_id')->nullable()
                ->comment('FK logis prod_category');
            $t->string('substrate_hint', 100)->nullable()
                ->comment('Bahan yang jadi acuan template, mis. DUPLEX 350gsm');
            $t->boolean('is_default')->default(false)
                ->comment('Dipakai otomatis saat kategori/tipe cocok dan tidak ada pilihan lain');
            $t->text('description')->nullable();
            $t->boolean('activate')->default(true);
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->string('data_origin', 10)->default('LIVE');
            $t->boolean('data_ready')->default(true);
            $t->boolean('data_origin_manual')->default(false);

            $t->index(['prod_type_code', 'activate'], 'ix_routing_tpl_type');
        });

        Schema::connection('tm2mst')->create('prod_routing_step', function (Blueprint $t) {
            $t->id('step_id');
            $t->unsignedBigInteger('template_id');
            $t->unsignedSmallInteger('step_seq')->comment('10, 20, 30 -- disisipi tanpa nomor ulang');
            $t->unsignedBigInteger('process_id');
            $t->unsignedBigInteger('preferred_machine_id')->nullable()
                ->comment('Usulan mesin; harus salah satu dari prod_process_machine');
            $t->boolean('is_optional')->default(false)
                ->comment('Langkah bisa dilewati (mis. laminasi hanya kalau diminta)');
            $t->string('branch_code', 20)->default('MAIN')
                ->comment('MAIN|BODY|LID|L|R -- sejalan dgn prod_part_process.branch_code');
            $t->decimal('qty_factor', 5, 3)->nullable()
                ->comment('Porsi qty yang lewat langkah ini (0.5 = belah plano)');
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->string('data_origin', 10)->default('LIVE');
            $t->boolean('data_ready')->default(true);
            $t->boolean('data_origin_manual')->default(false);

            $t->unique(['template_id', 'branch_code', 'step_seq'], 'uq_routing_step_seq');
            $t->index('process_id', 'ix_routing_step_process');

            $t->foreign('template_id', 'fk_rstep_template')->references('template_id')
                ->on('prod_routing_template')->cascadeOnDelete();
            $t->foreign('process_id', 'fk_rstep_process')->references('id_process')
                ->on('prod_process')->restrictOnDelete();
        });

        // ---------- 2. Aturan urutan proses ----------
        Schema::connection('tm2mst')->create('prod_process_precedence', function (Blueprint $t) {
            $t->id('precedence_id');
            $t->unsignedBigInteger('process_id')->comment('Proses yang diatur');
            $t->unsignedBigInteger('related_process_id')->comment('Proses pembanding');
            $t->string('rule_type', 20)
                ->comment('MUST_FOLLOW|MUST_PRECEDE|FORBIDDEN_AFTER|REQUIRES');
            $t->string('severity', 10)->default('WARN')
                ->comment('BLOCK = routing ditolak, WARN = boleh lanjut dgn peringatan');
            $t->string('reason', 255)->nullable()
                ->comment('Alasan teknis; ditampilkan ke officer saat aturan kena');
            $t->boolean('activate')->default(true);
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();

            $t->unique(['process_id', 'related_process_id', 'rule_type'], 'uq_precedence');

            $t->foreign('process_id', 'fk_prec_process')->references('id_process')
                ->on('prod_process')->cascadeOnDelete();
            $t->foreign('related_process_id', 'fk_prec_related')->references('id_process')
                ->on('prod_process')->cascadeOnDelete();
        });

        // ---------- 3. Matriks changeover ----------
        Schema::connection('tm2mst')->create('prod_machine_changeover', function (Blueprint $t) {
            $t->id('changeover_id');
            $t->unsignedBigInteger('machine_id');
            $t->string('change_kind', 20)
                ->comment('COLOR|SUBSTRATE|PLATE|DIE|SIZE|INK_TYPE -- jenis pergantian');
            $t->string('from_value', 50)->nullable()->comment('NULL = dari kondisi apa pun');
            $t->string('to_value', 50)->nullable()->comment('NULL = ke kondisi apa pun');
            $t->unsignedSmallInteger('extra_minutes')->comment('Tambahan waktu di atas setup normal');
            $t->unsignedInteger('extra_waste_qty')->nullable()
                ->comment('Lembar terbuang tambahan akibat pergantian ini');
            $t->string('note', 255)->nullable();
            $t->boolean('activate')->default(true);
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();

            $t->index(['machine_id', 'change_kind', 'activate'], 'ix_changeover_lookup');

            $t->foreign('machine_id', 'fk_chg_machine')->references('machine_id')
                ->on('prod_machine')->cascadeOnDelete();
        });

        // ---------- 4. Log downtime -> bahan OEE aktual ----------
        Schema::connection('tm2mst')->create('prod_machine_downtime', function (Blueprint $t) {
            $t->id('downtime_id');
            $t->unsignedBigInteger('machine_id');
            $t->string('downtime_kind', 20)
                ->comment('PLANNED_MAINT|BREAKDOWN|SETUP|WAITING_MATERIAL|WAITING_CREW|POWER|OTHER');
            $t->boolean('is_planned')->default(false)
                ->comment('Stop terencana tidak menurunkan Availability OEE');
            $t->dateTime('start_at');
            $t->dateTime('end_at')->nullable()->comment('NULL = masih berlangsung');
            $t->unsignedInteger('duration_minutes')->nullable()
                ->comment('Disimpan hasil hitung supaya laporan tidak perlu TIMESTAMPDIFF tiap query');
            $t->string('root_cause', 255)->nullable();
            $t->string('action_taken', 255)->nullable();
            $t->decimal('repair_cost', 18, 2)->nullable();
            $t->string('reported_by', 100)->nullable();
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();

            $t->index(['machine_id', 'start_at'], 'ix_downtime_machine_period');
            $t->index('downtime_kind', 'ix_downtime_kind');

            $t->foreign('machine_id', 'fk_dt_machine')->references('machine_id')
                ->on('prod_machine')->cascadeOnDelete();
        });

        $this->seedPrecedenceRules();
    }

    // Aturan urutan yang sudah pasti benar di percetakan offset -- sengaja
    // sedikit dan hanya yang tidak bisa dibantah, supaya validator tidak
    // langsung berisik dengan aturan tebakan. Sisanya diisi officer.
    private function seedPrecedenceRules(): void
    {
        $db = DB::connection('tm2mst');
        $id = fn (string $name) => $db->table('prod_process')->where('process_name', $name)->value('id_process');
        $now = now();

        // [proses, aturan, proses pembanding, tingkat, alasan]
        $rules = [
            ['LAMINASI', 'MUST_FOLLOW', 'CETAK', 'BLOCK', 'Laminasi menempel di atas hasil cetak — tidak bisa sebelum tinta ada.'],
            ['LAMINATING GLOSSY', 'MUST_FOLLOW', 'CETAK', 'BLOCK', 'Laminasi menempel di atas hasil cetak.'],
            ['LAMINATING DOFT', 'MUST_FOLLOW', 'CETAK', 'BLOCK', 'Laminasi menempel di atas hasil cetak.'],
            ['UV', 'MUST_FOLLOW', 'CETAK', 'BLOCK', 'Coating UV di atas area cetak.'],
            ['VARNISH', 'MUST_FOLLOW', 'CETAK', 'BLOCK', 'Varnish di atas area cetak.'],
            ['PLONG', 'MUST_FOLLOW', 'CETAK', 'WARN', 'Umumnya plong setelah cetak; plong lebih dulu hanya untuk bahan polos.'],
            ['KUPAS', 'MUST_FOLLOW', 'PLONG', 'BLOCK', 'Kupas membuang sisa pons — tidak ada yang dikupas sebelum diplong.'],
            ['LEM MESIN', 'MUST_FOLLOW', 'PLONG', 'BLOCK', 'Lem butuh blank yang sudah terbentuk dari plong.'],
            ['CETAK', 'MUST_FOLLOW', 'POTONG', 'WARN', 'Plano biasanya dipotong ke ukuran mesin sebelum cetak.'],
            ['CETAK', 'REQUIRES', 'PLATE MAKING', 'WARN', 'Cetak offset butuh plate dari CTP.'],
            ['QC PACK', 'MUST_FOLLOW', 'LEM MESIN', 'WARN', 'QC pack menilai produk yang sudah jadi.'],
            ['WRAPPING', 'MUST_FOLLOW', 'QC PACK', 'WARN', 'Dibungkus setelah lolos QC, bukan sebelum.'],
        ];

        foreach ($rules as [$process, $type, $related, $severity, $reason]) {
            $pid = $id($process);
            $rid = $id($related);

            if (! $pid || ! $rid || $pid === $rid) {
                continue;
            }

            $db->table('prod_process_precedence')->insertOrIgnore([
                'process_id' => $pid,
                'related_process_id' => $rid,
                'rule_type' => $type,
                'severity' => $severity,
                'reason' => $reason,
                'activate' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 'migration:enterprise_extensions',
                'updated_by' => 'migration:enterprise_extensions',
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_machine_downtime');
        Schema::connection('tm2mst')->dropIfExists('prod_machine_changeover');
        Schema::connection('tm2mst')->dropIfExists('prod_process_precedence');
        Schema::connection('tm2mst')->dropIfExists('prod_routing_step');
        Schema::connection('tm2mst')->dropIfExists('prod_routing_template');

        Schema::connection('tm2mst')->table('prod_process', function (Blueprint $t) {
            $t->dropIndex('ix_prod_process_wc');
            $t->dropColumn(['washup_minutes', 'work_center_id', 'qc_blocking']);
        });

        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $t) {
            $t->dropColumn(['plate_size', 'drying_system', 'mtbf_hours', 'helper_min']);
        });
    }
};
