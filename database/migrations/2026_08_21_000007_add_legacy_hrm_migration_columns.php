<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Persiapan migrasi tm2old.emp* -> tm2mst (App\Services\Migration\EmployeeMigrationService).
// Data riil (485 emp, 49 emp_position, 20 org_department legacy) TIDAK selengkap
// asumsi skema emp_employee semula:
// - gender & join_date awalnya NOT NULL, tapi >70% baris legacy tidak punya
//   hire_date dan >60% tidak punya gender -- dipaksa isi akan MENGARANG data,
//   jadi kolom ini dilonggarkan jadi nullable (bukan bug, keputusan sadar).
// - marital_status ada di data legacy tapi tak ada kolomnya di emp_employee --
//   ditambahkan.
// legacy_employee_id/legacy_department_id/legacy_position_id: kunci idempotensi
// + jejak asal data, sama pola dengan cust_customer.legacy_vendor_code di
// VendorMigrationService.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        $db->table('emp_employee', function (Blueprint $table) use ($db) {
            if (! $db->hasColumn('emp_employee', 'legacy_employee_id')) {
                $table->unsignedBigInteger('legacy_employee_id')->nullable()->unique()->after('employee_id');
            }
            if (! $db->hasColumn('emp_employee', 'marital_status')) {
                $table->string('marital_status', 20)->nullable()->after('religion');
            }
        });

        // ->change() butuh doctrine/dbal (tak terpasang di project ini) --
        // raw ALTER sama pola dgn migration 2026_08_21_000003 (costing_method).
        $conn = $db->getConnection();
        $conn->statement("ALTER TABLE emp_employee MODIFY COLUMN gender VARCHAR(1) NULL COMMENT 'L|P'");
        $conn->statement('ALTER TABLE emp_employee MODIFY COLUMN join_date DATE NULL');

        $db->table('org_department', function (Blueprint $table) use ($db) {
            if (! $db->hasColumn('org_department', 'legacy_department_id')) {
                $table->unsignedBigInteger('legacy_department_id')->nullable()->unique()->after('department_id');
            }
        });

        $db->table('org_position', function (Blueprint $table) use ($db) {
            if (! $db->hasColumn('org_position', 'legacy_position_id')) {
                $table->unsignedBigInteger('legacy_position_id')->nullable()->after('position_id');
            }
            if (! $db->hasColumn('org_position', 'legacy_skill_rate')) {
                $table->decimal('legacy_skill_rate', 12, 2)->nullable()->comment('skill_rate asli dari emp_position legacy -- referensi saja, belum ada tabel rate payroll di V2')->after('salary_hidden_from_hr');
            }
        });
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');

        $db->table('org_position', function (Blueprint $table) {
            $table->dropColumn(['legacy_position_id', 'legacy_skill_rate']);
        });

        $db->table('org_department', function (Blueprint $table) {
            $table->dropColumn('legacy_department_id');
        });

        $db->table('emp_employee', function (Blueprint $table) {
            $table->dropColumn(['legacy_employee_id', 'marital_status']);
        });
    }
};
