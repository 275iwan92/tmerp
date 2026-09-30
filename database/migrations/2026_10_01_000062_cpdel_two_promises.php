<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CPDEL = buku janji (user 2026-10-01): satu baris jadwal kirim memegang DUA janji yang harus sama --
// janji ke customer (slot kirim = ship_date + ship_time, dipegang Sales) dan janji produksi (ready_at, dipegang PPIC).
// Janji awal (original_date) tidak pernah berubah = patokan KPI; setiap perubahan = negosiasi tercatat
// (co_delivery_negotiation), bukan edit diam-diam. Customer dinego lewat WhatsApp / telepon. Split = salah satu solusi.
// Master alasan terlambat (co_delay_reason) menunjuk departemen penanggung jawab.
return new class extends Migration
{
    private const REASONS = [
        // [code, English label, Indonesian, department]
        ['MATERIAL_LATE', 'Material late from supplier', 'Bahan terlambat dari supplier', 'Purchasing'],
        ['MACHINE_DOWN', 'Machine breakdown', 'Mesin rusak', 'Maintenance'],
        ['CAPACITY', 'Machine / manpower capacity full', 'Kapasitas mesin / tenaga penuh', 'Production'],
        ['REWORK', 'Rework / quality problem', 'Pengerjaan ulang / masalah mutu', 'Production'],
        ['ARTWORK_LATE', 'Artwork or proof approval late', 'Artwork / ACC proof terlambat', 'Sales'],
        ['PLANNING', 'Planning / schedule conflict', 'Bentrok jadwal / perencanaan', 'PPIC'],
        ['TRANSPORT', 'Truck / transport not available', 'Truk / transport tidak tersedia', 'Delivery'],
        ['CUSTOMER_REQUEST', 'Customer asked to change', 'Permintaan customer', 'Customer'],
        ['OTHER', 'Other', 'Lainnya', 'Other'],
    ];

    public function up(): void
    {
        $sdi = Schema::connection('tm2sdi');
        $sdi->table('co_order_delivery', function (Blueprint $t) use ($sdi) {
            if (! $sdi->hasColumn('co_order_delivery', 'original_date')) {
                $t->date('original_date')->nullable()->after('ship_date')->comment('first promise to customer, never changes (KPI baseline)');
                $t->time('ship_time')->nullable()->after('original_date')->comment('agreed slot time at the customer');
                $t->dateTime('ready_at')->nullable()->after('ship_time')->comment('production promise: goods ready (PPIC)');
                $t->string('ready_source', 10)->nullable()->after('ready_at')->comment('MANUAL | SCHEDULER');
                $t->string('ready_by', 50)->nullable()->after('ready_source');
                $t->dateTime('escalated_at')->nullable()->after('ready_by')->comment('customer rejected -> Operational Manager decides');
            }
        });
        DB::connection('tm2sdi')->table('co_order_delivery')->whereNull('original_date')->update(['original_date' => DB::raw('ship_date')]);

        if (! $sdi->hasTable('co_delay_reason')) {
            $sdi->create('co_delay_reason', function (Blueprint $t) {
                $t->id('id_delay_reason');
                $t->string('reason_code', 30)->unique();
                $t->string('reason_name', 100);
                $t->string('department', 30)->comment('responsible department (KPI)');
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
            });
        }
        foreach (self::REASONS as [$code, $en, , $dept]) {
            DB::connection('tm2sdi')->table('co_delay_reason')->updateOrInsert(['reason_code' => $code], ['reason_name' => $en, 'department' => $dept,
                'activate' => 1, 'created_at' => now(), 'created_by' => 'claude_code']);
        }

        if (! $sdi->hasTable('co_delivery_negotiation')) {
            $sdi->create('co_delivery_negotiation', function (Blueprint $t) {
                $t->id('id_negotiation');
                $t->unsignedBigInteger('delivery_id');
                $t->string('event', 30)->comment('READY_COMMITTED | RESCHEDULED | SPLIT | MERGED | CUSTOMER_REJECTED | OPS_DECISION');
                $t->string('actor_role', 20)->comment('PPIC | SALES | OPS');
                $t->date('old_date')->nullable();
                $t->time('old_time')->nullable();
                $t->date('new_date')->nullable();
                $t->time('new_time')->nullable();
                $t->decimal('old_qty', 14, 2)->nullable();
                $t->decimal('new_qty', 14, 2)->nullable();
                $t->dateTime('ready_at')->nullable();
                $t->string('reason_code', 30)->nullable();
                $t->string('channel', 20)->nullable()->comment('WHATSAPP | CALL | EMAIL | MEETING');
                $t->string('customer_contact', 100)->nullable();
                $t->string('decision', 20)->nullable()->comment('OPS: OVERTIME | PRIORITY | SUBCON | NEW_OFFER');
                $t->string('note', 500)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->index(['delivery_id', 'created_at']);
                $t->foreign('delivery_id')->references('id_delivery')->on('co_order_delivery')->cascadeOnDelete();
            });
        }

        $sys = DB::connection('tm2sys');
        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            foreach (self::REASONS as [, $en, $id]) {
                $sys->table('sys_dictionary')->insertOrIgnore(['context' => 'tm2sdi.co_delay_reason.reason_name', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'claude_code']);
                $dictId = $sys->table('sys_dictionary')->where('context', 'tm2sdi.co_delay_reason.reason_name')->where('source_en', $en)->value('id_dictionary');
                $sys->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $dictId, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        $pid = $sys->table('sys_app_menu')->where('code', 'MDATA')->value('id');
        if ($pid && ! $sys->table('sys_app_menu')->where('code', 'MD_DELAY_REASON')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $pid, 'code' => 'MD_DELAY_REASON', 'label' => 'Delivery Delay Reasons', 'menu_type' => 'item',
                'object_type' => 'grid', 'target' => 'v2:delivery-delay-reasons', 'target_kind' => 'app', 'sort_order' => 12, 'activate' => 1, 'created_at' => now(),
                'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 CPDEL two promises', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_DELAY_REASON')->delete();
        Schema::connection('tm2sdi')->dropIfExists('co_delivery_negotiation');
        Schema::connection('tm2sdi')->dropIfExists('co_delay_reason');
        Schema::connection('tm2sdi')->table('co_order_delivery', fn (Blueprint $t) => $t->dropColumn(['original_date', 'ship_time', 'ready_at', 'ready_source', 'ready_by', 'escalated_at']));
    }
};
