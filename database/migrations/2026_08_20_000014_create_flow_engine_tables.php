<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Flow Engine generik (TIER_1/2/3_FLOW_ENGINE.md, DDL_FLOW_ENGINE.sql) --
// satu engine approval + state-transition lintas modul (PR/PO/PV/PRT/CO/DO/dst).
// Menggantikan m_authorize lama & doc_state_log. Lokasi: tm2mst (master netral,
// dipakai lintas modul via FK logical module_code+doc_id, BUKAN FK fisik ke
// tiap tabel dokumen -- dokumen tersebar di banyak DB per TIER_0_GLOBAL_ARCHITECTURE).
// FK position_id -> org_position FISIK (sama-sama tm2mst).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        if (! $db->hasTable('flow_config')) {
            $db->create('flow_config', function (Blueprint $table) {
                $table->id('flow_config_id');
                $table->string('module_code', 50)->comment('PR, PO, PV, PRT, CO, DO, dst');
                $table->unsignedBigInteger('division_id')->nullable()->comment('FK org_division. NULL = company-wide');
                $table->unsignedTinyInteger('total_step')->default(0);
                $table->string('notes', 200)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['module_code', 'division_id', 'is_active'], 'uq_flowconfig_module_division_active');
                $table->index('division_id');
                $table->foreign('division_id')->references('division_id')->on('org_division')->nullOnDelete();
            });
        }

        if (! $db->hasTable('flow_config_step')) {
            $db->create('flow_config_step', function (Blueprint $table) {
                $table->id('flow_step_id');
                $table->unsignedBigInteger('flow_config_id');
                $table->unsignedInteger('step_no');
                $table->string('step_type', 20)->default('APPROVAL')->comment('APPROVAL|AUTO|NOTIFY_ONLY');
                $table->string('status_from', 30);
                $table->string('status_to', 30);
                $table->string('status_to_reject', 30)->nullable();
                $table->unsignedBigInteger('position_id')->nullable()->comment('NULL bila step_type=AUTO');
                $table->decimal('min_amount', 18, 2)->nullable();
                $table->decimal('max_amount', 18, 2)->nullable();
                $table->string('label', 100)->nullable()->comment('Membuat, Mengetahui, Menyetujui, dst');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->index('flow_config_id');
                $table->index('position_id');
                $table->index('status_from');
                $table->foreign('flow_config_id')->references('flow_config_id')->on('flow_config')->cascadeOnDelete();
                $table->foreign('position_id')->references('position_id')->on('org_position')->nullOnDelete();
            });
        }

        if (! $db->hasTable('flow_position_acting')) {
            $db->create('flow_position_acting', function (Blueprint $table) {
                $table->id('acting_id');
                $table->unsignedBigInteger('position_id');
                $table->unsignedBigInteger('acting_user_id')->comment('LOGICAL -> tm2sys.sys_user (lintas-DB)');
                $table->date('start_date');
                $table->date('end_date');
                $table->string('reason', 200)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->index(['position_id', 'start_date', 'end_date']);
                $table->index('acting_user_id');
                $table->foreign('position_id')->references('position_id')->on('org_position')->cascadeOnDelete();
            });
        }

        if (! $db->hasTable('flow_log')) {
            $db->create('flow_log', function (Blueprint $table) {
                $table->id('flow_log_id');
                $table->string('module_code', 50);
                $table->unsignedBigInteger('doc_id')->comment('Polymorphic - bukan FK fisik, dokumen tersebar lintas-DB');
                $table->unsignedBigInteger('flow_config_id');
                $table->unsignedBigInteger('flow_step_id')->nullable();
                $table->string('status_from', 30);
                $table->string('status_to', 30);
                $table->unsignedBigInteger('actor_position_id')->nullable()->comment('Snapshot jabatan penanggung jawab step');
                $table->unsignedBigInteger('actual_user_id')->nullable()->comment('LOGICAL -> tm2sys.sys_user');
                $table->boolean('is_acting')->default(false);
                $table->string('action', 20)->comment('SUBMITTED|APPROVED|REJECTED|AUTO_ADVANCED|CANCELLED');
                $table->decimal('amount_snapshot', 18, 2)->nullable();
                $table->string('notes', 200)->nullable();
                $table->timestamp('acted_at')->nullable()->useCurrent();
                $table->string('created_by', 200)->nullable();

                $table->index(['module_code', 'doc_id']);
                $table->index(['flow_config_id', 'flow_step_id']);
                $table->index('acted_at');
                $table->foreign('flow_config_id')->references('flow_config_id')->on('flow_config')->restrictOnDelete();
                $table->foreign('flow_step_id')->references('flow_step_id')->on('flow_config_step')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');
        $db->dropIfExists('flow_log');
        $db->dropIfExists('flow_position_acting');
        $db->dropIfExists('flow_config_step');
        $db->dropIfExists('flow_config');
    }
};
