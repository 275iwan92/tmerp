<?php

/*
 * REMARK
 * Tujuan       : Membuang topik Help yang tidak relevan bagi pengguna: catatan "Technical Plan" pengembang (Maintenance, Inventory, PPIC), catatan keputusan / checklist terbuka, dan satu topik layar yang sudah tidak ada.
 * Sumber hulu  : Daftar kunci di bawah; isinya sudah diarsipkan di tmerp_unused/help_removed_2026-10-16 dan berkas JSON sumbernya (resources/data/help) sudah dibersihkan supaya help:import tidak memulihkannya.
 * Input        : -
 * Output       : Baris sys_help_topic yang dibuang (sys_help_line ikut terhapus lewat foreign key cascade).
 * Dipakai oleh : Help Center, Asisten AI (keduanya membaca sys_help_topic).
 * Status       : Jalankan di ddt* dan tm2* (aturan paritas Jo 2026-10-16).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEYS = [
        'guide-inv-booking-decisions',
        'guide-inv-missed-checklist',
        'guide-inv-open-decisions',
        'guide-invwh-tech-overview',
        'guide-invwh-tech-menu',
        'guide-invwh-tech-stock-model',
        'guide-invwh-tech-movements',
        'guide-invwh-tech-workflow-inbound',
        'guide-invwh-tech-workflow-outbound',
        'guide-invwh-tech-dataflow',
        'guide-invwh-tech-rules',
        'guide-invwh-tech-database',
        'guide-invwh-tech-previews',
        'guide-invwh-tech-phases',
        'guide-invwh-tech-references',
        'guide-maintenance-tech-overview',
        'guide-maintenance-tech-menu',
        'guide-maintenance-tech-assets',
        'guide-maintenance-tech-features',
        'guide-maintenance-tech-roles',
        'guide-maintenance-tech-workflow',
        'guide-maintenance-tech-kpi',
        'guide-maintenance-tech-flow',
        'guide-maintenance-tech-database',
        'guide-maintenance-tech-rules',
        'guide-maintenance-tech-glossary',
        'guide-maintenance-tech-profile',
        'guide-maintenance-tech-sop-wi',
        'guide-maintenance-tech-phase1',
        'guide-maintenance-tech-improvement',
        'guide-maintenance-tech-phases',
        'guide-maintenance-tech-database-decision',
        'guide-maintenance-tech-decisions',
        'guide-ppic-review-open-decisions',
        'guide-ppic-tech-overview',
        'guide-ppic-tech-menu',
        'guide-ppic-tech-screen',
        'guide-ppic-tech-devlib',
        'guide-ppic-tech-database',
        'guide-ppic-tech-engines',
        'guide-ppic-tech-flow',
        'guide-ppic-tech-rules',
        'guide-ppic-tech-roles',
        'guide-ppic-tech-phases',
        'guide-ppic-tech-decisions',
        'guide-ppic-tech-engine-flow',
        'guide-ppic-tech-operator-queue',
        'guide-ppic-tech-wo-gantt',
        'guide-ppic-tech-wo-knowledge',
        'guide-ppic-build-status',
        'prod-no-output',
    ];

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_help_topic')->whereIn('topic_key', self::KEYS)->delete();
    }

    // Pulihkan dengan php artisan help:import setelah mengembalikan berkas JSON dari arsip.
    public function down(): void {}
};
