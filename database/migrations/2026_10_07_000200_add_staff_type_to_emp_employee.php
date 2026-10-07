<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tipe pegawai (user 2026-10-07): STAFF / NON_STAFF, terpisah dari metode payroll (payment_frequency WEEKLY|MONTHLY).
// Dasar pembatasan lihat payroll: HR Officer hanya NON_STAFF, Direksi/GM semua (App\Services\Payroll\PayrollAccess).
// Isian awal: 38 pegawai daftar staf (docs/attachments/KEBUTUHAN PAK IWAN.xlsx, kode sama dengan migration lock_real_staff_list)
// = STAFF, sisanya NON_STAFF.
return new class extends Migration
{
    private const STAFF = ['TM-00146', 'TM-00118', 'TM-00139', 'TM-00112', 'TM-00113', 'TM-00114', 'TM-00115', 'TM-00116', 'TM-00121', 'TM-00122',
        'TM-00145', 'TM-00127', 'TM-00129', 'TM-00132', 'TM-00135', 'TM-00137', 'TM-00140', 'TM-00147', 'TM-00142', 'TM-00143', 'TM-00144', 'TM-00149',
        'TM-00150', 'TM-00520', 'TM-00522', 'TM-00523', 'TM-00527', 'TM-00528', 'TM-00529', 'TM-00530', 'TM-00531', 'TM-00533', 'TM-00534', 'TM-00535',
        'TM-00539', 'TM-00540', 'TM-00541', 'TM-00542'];

    public function up(): void
    {
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $table) {
            $table->string('staff_type', 10)->default('NON_STAFF')->after('payment_frequency')
                ->comment('STAFF|NON_STAFF -- tipe pegawai, penentu siapa boleh melihat payroll-nya');
            $table->index('staff_type');
        });

        DB::connection('tm2mst')->table('emp_employee')->whereIn('employee_code', self::STAFF)->update(['staff_type' => 'STAFF']);

        // metode payroll default tiap pegawai: kolom sudah NOT NULL; pastikan isinya hanya WEEKLY|MONTHLY.
        DB::connection('tm2mst')->table('emp_employee')->whereNotIn('payment_frequency', ['WEEKLY', 'MONTHLY'])->update(['payment_frequency' => 'MONTHLY']);
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $table) {
            $table->dropIndex(['staff_type']);
            $table->dropColumn('staff_type');
        });
    }
};
