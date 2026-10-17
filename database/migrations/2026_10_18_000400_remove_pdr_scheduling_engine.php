<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * REMARK
 * Tujuan       : Hapus mesin penjadwal ganda. Penjadwal yang berlaku = Schedule Solver + AutoPlanRunner (PPIC Workspace). Kelompok PDR
 *                (PdrEngine, SchedulingRuleEngine, InterruptionOptionGenerator, papan v1 scheduling-board, layar Rule Library) hanya dipakai
 *                papan v1; tabel yatimnya dihapus di sini.
 * Sumber hulu  : audit modul ganda 2026-10-18, disetujui Jo (A4).
 * Input        : tm2prd.prd_scheduling_rule, tm2prd.prd_decision_feedback_log (0 baris), sys_app_menu (target v2:prod-rule-library).
 * Output       : dua tabel dan baris menu Rule Library dihapus; down() membuat ulang struktur kosong.
 * Dipakai oleh : php artisan migrate --env=mockup lalu migrate biasa (tmerp).
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const TABLES = ['prd_scheduling_rule', 'prd_decision_feedback_log'];

    public function up(): void
    {
        $prd = Schema::connection('tm2prd');
        foreach (self::TABLES as $table) {
            if ($prd->hasTable($table) && ($n = DB::connection('tm2prd')->table($table)->count()) > 0) {
                throw new RuntimeException("{$table} masih berisi {$n} baris; arsipkan dulu sebelum dihapus.");
            }
        }
        foreach (self::TABLES as $table) {
            $prd->dropIfExists($table);
        }
        DB::connection('tm2sys')->table('sys_app_menu')->where('target', 'v2:prod-rule-library')->delete();
    }

    public function down(): void
    {
        $prd = Schema::connection('tm2prd');
        if (! $prd->hasTable('prd_scheduling_rule')) {
            $prd->create('prd_scheduling_rule', function (Blueprint $t) {
                $t->id('rule_id');
                $t->string('rule_type', 20);
                $t->string('rule_name', 150);
                $t->string('rule_status', 10)->default('DRAFT');
                $t->timestamps();
            });
        }
        if (! $prd->hasTable('prd_decision_feedback_log')) {
            $prd->create('prd_decision_feedback_log', function (Blueprint $t) {
                $t->id('log_id');
                $t->timestamps();
            });
        }
    }
};
