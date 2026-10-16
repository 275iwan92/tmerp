<?php

/*
 * REMARK
 * Tujuan       : Fondasi perubahan spesifikasi sesudah CO Confirm (Jo 2026-10-16): permintaan perubahan + OTP persetujuan, bahan cadangan, grup GM, dan konstanta.
 * Sumber hulu  : Keputusan Jo 2026-10-16 (kelas 1 spesifikasi customer = Director; kelas 2 substitusi bahan dan kelas 3 routing = OPM / GM / Director lewat OTP satu permintaan satu kode).
 * Input        : -
 * Output       : tm2prd.spc_change_request (permintaan) dan spc_otp (kode sekali pakai, hanya hash); tm2mst.prod_material_alt (bahan cadangan); tm2sys: grup GM (level 5), konstanta SPEC_CHANGE_APPROVER_LEVEL (4) dan SPEC_CHANGE_OTP_MINUTES (10).
 * Dipakai oleh : App\Services\Production\SpecChange\* (SpecChangeService, SpecOtpService, SpecLock).
 * Status       : Jalankan di ddt* dan tm2* (aturan paritas Jo 2026-10-16).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prd = Schema::connection('tm2prd');
        if (! $prd->hasTable('spc_change_request')) {
            $prd->create('spc_change_request', function (Blueprint $t) {
                $t->id('id_request');
                $t->unsignedTinyInteger('change_class');          // 1 spesifikasi customer, 2 substitusi bahan, 3 routing / BOP
                $t->unsignedBigInteger('wo_id');
                $t->unsignedBigInteger('wo_material_id')->nullable(); // prd_wo_material.detail_id (kelas 2)
                $t->unsignedBigInteger('product_id')->nullable();
                $t->json('payload');                              // sebelum / sesudah
                $t->string('reason', 255);
                $t->json('cost_impact')->nullable();              // selisih biaya (dihitung di latar belakang)
                $t->boolean('save_backup')->default(false);
                $t->string('status', 10)->default('PENDING');     // PENDING | APPLIED | REJECTED | CANCELLED
                $t->string('requested_by', 60);
                $t->dateTime('requested_at');
                $t->string('decided_by', 60)->nullable();         // pemberi OTP (penyetuju) atau penolak
                $t->dateTime('decided_at')->nullable();
                $t->string('decision_note', 255)->nullable();
                $t->timestamps();
                $t->index(['wo_id', 'status']);
                $t->index(['wo_material_id', 'status']);
            });
        }
        if (! $prd->hasTable('spc_otp')) {
            $prd->create('spc_otp', function (Blueprint $t) {
                $t->id('id_otp');
                $t->unsignedBigInteger('request_id');
                $t->char('code_hash', 64);                        // HMAC kode + id permintaan; kode asli tidak disimpan
                $t->string('issued_by', 60);
                $t->dateTime('issued_at');
                $t->dateTime('expires_at');
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->string('used_by', 60)->nullable();
                $t->dateTime('used_at')->nullable();
                $t->timestamps();
                $t->index('request_id');
                $t->foreign('request_id')->references('id_request')->on('spc_change_request')->cascadeOnDelete();
            });
        }

        $mst = Schema::connection('tm2mst');
        if (! $mst->hasTable('prod_material_alt')) {
            $mst->create('prod_material_alt', function (Blueprint $t) {
                $t->id('id_alt');
                $t->unsignedBigInteger('material_id');            // bahan asal
                $t->unsignedBigInteger('alt_material_id');        // bahan cadangan (hanya saran pada substitusi berikutnya)
                $t->string('note', 255)->nullable();
                $t->string('approved_by', 60)->nullable();
                $t->dateTime('approved_at')->nullable();
                $t->unsignedBigInteger('source_wo_id')->nullable();
                $t->boolean('activate')->default(true);
                $t->string('created_by', 60)->nullable();
                $t->timestamps();
                $t->unique(['material_id', 'alt_material_id']);
            });
        }

        $sys = DB::connection('tm2sys');
        if (! $sys->table('sec_group')->where('group_code', 'GM')->exists()) {
            $sys->table('sec_group')->insert(['group_code' => 'GM', 'group_name' => 'General Manager', 'mgmt_level' => 5, 'activate' => 1,
                'created_at' => now(), 'updated_at' => now(), 'created_by' => 'system']);
        }
        foreach ([
            ['SPEC_CHANGE_APPROVER_LEVEL', 4, 'Spec change after CO Confirm: the lowest management level that may give an OTP (4 = Operational Manager, GM and Director).'],
            ['SPEC_CHANGE_OTP_MINUTES', 10, 'Spec change after CO Confirm: minutes an approval OTP stays valid (one code per request, single use).'],
        ] as [$key, $value, $desc]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRODUCTION', 'constant_group' => 'Spec Change', 'constant_number' => $value,
                    'activate' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'system', 'description' => $desc]);
            }
        }
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->dropIfExists('spc_otp');
        Schema::connection('tm2prd')->dropIfExists('spc_change_request');
        Schema::connection('tm2mst')->dropIfExists('prod_material_alt');
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['SPEC_CHANGE_APPROVER_LEVEL', 'SPEC_CHANGE_OTP_MINUTES'])->delete();
    }
};
