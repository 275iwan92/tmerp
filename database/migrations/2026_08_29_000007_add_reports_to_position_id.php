<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom hierarki "melapor ke" -- org_position.reports_to_position_id, self-FK
// dalam DB yang sama (tm2mst), jadi FK FISIK aman (globalarchitecture_tier0.md).
// Dipakai layar Struktur Organisasi (org chart) + Master Posisi -- instruksi
// user 2026-08-29 ("structure UI UX seperti ini bisakah kamu membuat nya").
// Nullable = posisi puncak (CEO/Direksi) tidak melapor ke siapa pun.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('org_position', function (Blueprint $table) {
            $table->unsignedBigInteger('reports_to_position_id')->nullable()->after('department_id');
            $table->foreign('reports_to_position_id')->references('position_id')->on('org_position')->onDelete('set null');
            $table->index('reports_to_position_id', 'idx_position_reports_to');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('org_position', function (Blueprint $table) {
            $table->dropForeign(['reports_to_position_id']);
            $table->dropColumn('reports_to_position_id');
        });
    }
};
