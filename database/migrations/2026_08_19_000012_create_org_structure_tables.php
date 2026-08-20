<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_HRM.sql Bagian 1 — struktur organisasi org_company -> org_division
// (3 SBU) -> org_department (nullable division_id = shared/lintas-SBU spt
// Security) -> org_position. Master netral lintas modul, tm2mst (CLAUDE.md §2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('org_company', function (Blueprint $table) {
            $table->id('company_id');
            $table->string('company_code', 10)->unique();
            $table->string('company_name', 150);
            $table->string('npwp_number', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('org_level', function (Blueprint $table) {
            $table->id('level_id');
            $table->string('level_code', 20)->unique();
            $table->string('level_name', 100);
            $table->unsignedSmallInteger('level_rank');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('org_division', function (Blueprint $table) {
            $table->id('division_id');
            $table->foreignId('company_id')->constrained('org_company', 'company_id')->restrictOnDelete();
            $table->string('division_code', 20);
            $table->string('division_name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['company_id', 'division_code']);
        });

        Schema::connection('tm2mst')->create('org_department', function (Blueprint $table) {
            $table->id('department_id');
            $table->foreignId('division_id')->nullable()->constrained('org_division', 'division_id')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('org_company', 'company_id')->restrictOnDelete();
            $table->string('department_code', 20);
            $table->string('department_name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['company_id', 'department_code']);
        });

        Schema::connection('tm2mst')->create('org_position', function (Blueprint $table) {
            $table->id('position_id');
            $table->foreignId('department_id')->constrained('org_department', 'department_id')->restrictOnDelete();
            $table->foreignId('level_id')->constrained('org_level', 'level_id')->restrictOnDelete();
            $table->string('position_code', 20);
            $table->string('position_name', 150);
            $table->boolean('salary_hidden_from_hr')->default(false)->comment('1 = gaji posisi ini selalu disembunyikan dari HR Officer, terlepas employment_type');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['department_id', 'position_code']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('org_position');
        Schema::connection('tm2mst')->dropIfExists('org_department');
        Schema::connection('tm2mst')->dropIfExists('org_division');
        Schema::connection('tm2mst')->dropIfExists('org_level');
        Schema::connection('tm2mst')->dropIfExists('org_company');
    }
};
