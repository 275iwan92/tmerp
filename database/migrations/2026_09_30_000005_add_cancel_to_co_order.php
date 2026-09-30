<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cancel Customer Order (user 2026-09-30: fasilitas saat user baru sadar ada kesalahan setelah Confirm).
// CONFIRMED -> doc_status CANCELLED + stat_cancel + cancel_reason (pelaku = updated_by); jadwal kirimnya ikut CANCELLED.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('co_order', function (Blueprint $table) {
            $table->boolean('stat_cancel')->default(false)->after('doc_status');
            $table->string('cancel_reason', 255)->nullable()->after('stat_cancel');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('co_order', function (Blueprint $table) {
            $table->dropColumn(['stat_cancel', 'cancel_reason']);
        });
    }
};
