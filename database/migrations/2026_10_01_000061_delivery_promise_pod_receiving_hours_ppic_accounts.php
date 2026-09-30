<?php

use App\Models\Master\EmpEmployee;
use App\Services\Migration\EmployeeLoginProvisionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// KPI janji kirim (user 2026-10-01, KPI SEMUA DEPARTEMEN): yang dihitung = jam TIBA di customer + barang DITERIMA atau tidak
// (customer tutup / tolak). Slot 10:00 tiba 14:00 = meleset.
// 1. tm2sdi.do_delivery_pod: satu baris = satu kali datang ke customer (percobaan ulang = baris baru). Diisi manual di web oleh
//    officer pengiriman (Ms. Winda); versi HP + foto GPS menyusul.
// 2. tm2mst.cust_receiving_hour: jam terima barang customer per hari. customer_id NULL = jam default semua customer (jam kantor
//    normal Indonesia, 5 hari kerja UU Cipta Kerja: Senin-Jumat 08:00-17:00, istirahat 12:00-13:00, Jumat 11:30-13:00 salat
//    Jumat). Customer yang berbeda cukup punya baris sendiri.
// 3. Grup akses (area kerja, bukan jabatan): PROD_PLANNING (PPIC: Tia, Fardan; Soleh belum ada di master pegawai) + DELIVERY
//    (Winda). Akun login dibuat lewat EmployeeLoginProvisionService (password awal terenkripsi, wajib ganti saat login pertama).
return new class extends Migration
{
    private const DEFAULT_HOURS = [
        // [ISO day, open, start, end, break_start, break_end]
        [1, true, '08:00', '17:00', '12:00', '13:00'],
        [2, true, '08:00', '17:00', '12:00', '13:00'],
        [3, true, '08:00', '17:00', '12:00', '13:00'],
        [4, true, '08:00', '17:00', '12:00', '13:00'],
        [5, true, '08:00', '17:00', '11:30', '13:00'],
        [6, false, null, null, null, null],
        [7, false, null, null, null, null],
    ];

    private const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function up(): void
    {
        if (! Schema::connection('tm2sdi')->hasTable('do_delivery_pod')) {
            Schema::connection('tm2sdi')->create('do_delivery_pod', function (Blueprint $t) {
                $t->id('id_pod');
                $t->unsignedBigInteger('delivery_id');
                $t->unsignedSmallInteger('attempt_no')->default(1);
                $t->date('promise_date')->nullable()->comment('snapshot slot kirim yang dijanjikan ke customer');
                $t->time('promise_time')->nullable();
                $t->dateTime('arrived_at')->nullable()->comment('jam tiba di customer');
                $t->string('result', 20)->comment('RECEIVED | PARTIAL | CUSTOMER_CLOSED | REJECTED | NOT_SHIPPED');
                $t->string('received_by', 100)->nullable();
                $t->string('miss_cause', 30)->nullable()->comment('PRODUCTION_NOT_READY | TRUCK_LATE | SLOT_OUTSIDE_CUSTOMER_HOURS | MATERIAL_LATE | QUALITY | CUSTOMER | OTHER');
                $t->boolean('promise_kept')->default(false);
                $t->string('photo_path', 255)->nullable();
                $t->decimal('gps_lat', 10, 7)->nullable();
                $t->decimal('gps_lng', 10, 7)->nullable();
                $t->string('notes', 500)->nullable();
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->unique(['delivery_id', 'attempt_no']);
                $t->index('arrived_at');
                $t->foreign('delivery_id')->references('id_delivery')->on('do_delivery');
            });
        }

        if (! Schema::connection('tm2mst')->hasTable('cust_receiving_hour')) {
            Schema::connection('tm2mst')->create('cust_receiving_hour', function (Blueprint $t) {
                $t->id('id_receiving_hour');
                $t->unsignedBigInteger('customer_id')->nullable()->comment('NULL = default for all customers');
                $t->unsignedTinyInteger('day_of_week')->comment('ISO: 1 = Monday .. 7 = Sunday');
                $t->boolean('is_open')->default(true);
                $t->time('open_time')->nullable();
                $t->time('close_time')->nullable();
                $t->time('break_start')->nullable();
                $t->time('break_end')->nullable();
                $t->string('notes', 255)->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index(['customer_id', 'day_of_week']);
                $t->foreign('customer_id')->references('id_customer')->on('cust_customer');
            });
        }
        $mst = DB::connection('tm2mst');
        foreach (self::DEFAULT_HOURS as [$dow, $open, $start, $end, $b1, $b2]) {
            $mst->table('cust_receiving_hour')->updateOrInsert(['customer_id' => null, 'day_of_week' => $dow], ['is_open' => $open, 'open_time' => $start,
                'close_time' => $end, 'break_start' => $b1, 'break_end' => $b2, 'activate' => 1, 'created_at' => now(), 'created_by' => 'claude_code',
                'notes' => $dow === 5 ? 'Friday prayer break' : null]);
        }

        $this->accessGroups();
        $this->accounts();
        $this->menu();
    }

    private function accessGroups(): void
    {
        $sys = DB::connection('tm2sys');
        $modules = $sys->table('sec_module')->pluck('id_module', 'module_code');
        $groups = [
            'PROD_PLANNING' => ['Production Planning', ['PROD' => [1, 1, 0, 1], 'SALES' => [1, 0, 0, 1], 'MAINT' => [1, 0, 0, 1], 'INV' => [1, 0, 0, 1]]],
            'DELIVERY' => ['Delivery', ['SALES' => [1, 1, 0, 1], 'INV' => [1, 0, 0, 1]]],
        ];
        foreach ($groups as $code => [$name, $perms]) {
            $sys->table('sec_group')->updateOrInsert(['group_code' => $code], ['group_name' => $name, 'mgmt_level' => 1, 'activate' => 1,
                'created_at' => now(), 'created_by' => 'claude_code']);
            $gid = $sys->table('sec_group')->where('group_code', $code)->value('id_group');
            foreach ($perms as $module => [$access, $input, $approve, $export]) {
                if (isset($modules[$module])) {
                    $sys->table('sec_group_permission')->updateOrInsert(['group_id' => $gid, 'module_id' => $modules[$module]], ['can_access' => $access,
                        'can_input' => $input, 'can_approve' => $approve, 'can_export' => $export, 'created_at' => now(), 'created_by' => 'claude_code']);
                }
            }
        }
    }

    // employee_code -> [login, grup]. Soleh: tidak ada di emp_employee (2026-10-01) -> menunggu data dari user.
    private function accounts(): void
    {
        $sys = DB::connection('tm2sys');
        $people = ['TM-00523' => ['tia', 'PROD_PLANNING'], 'TM-00535' => ['fardan', 'PROD_PLANNING'], 'TM-00118' => [null, 'DELIVERY']];
        foreach ($people as $code => [$login, $group]) {
            $emp = EmpEmployee::query()->where('employee_code', $code)->where('activate', true)->first();
            if (! $emp) {
                continue;
            }
            app(EmployeeLoginProvisionService::class)->provisionForEmployee($emp, 'claude_code');
            $user = $sys->table('sec_user')->where('employee_id', $emp->employee_id)->first();
            if ($login && $user->login !== $login && ! $sys->table('sec_user')->where('login', $login)->exists()) {
                $sys->table('sec_user')->where('id_user', $user->id_user)->update(['login' => $login, 'email' => $login.'@telasih.co.id']);
            }
            $gid = $sys->table('sec_group')->where('group_code', $group)->value('id_group');
            $sys->table('sec_user_group')->insertOrIgnore(['user_id' => $user->id_user, 'group_id' => $gid, 'created_at' => now()]);
        }
    }

    private function menu(): void
    {
        $sys = DB::connection('tm2sys');
        $items = [
            ['SALES_POD', 'SALES_DLVGRP', 'Proof of Delivery', 'v2:proof-of-delivery', 30, 'form'],
            ['MD_CUST_RECV', 'MDATA', 'Customer Receiving Hours', 'v2:customer-receiving-hours', 11, 'grid'],
        ];
        foreach ($items as [$code, $parent, $label, $target, $sort, $type]) {
            $pid = $sys->table('sys_app_menu')->where('code', $parent)->value('id');
            if ($pid && ! $sys->table('sys_app_menu')->where('code', $code)->exists()) {
                $sys->table('sys_app_menu')->insert(['parent_id' => $pid, 'code' => $code, 'label' => $label, 'menu_type' => 'item', 'object_type' => $type,
                    'target' => $target, 'target_kind' => 'app', 'sort_order' => $sort, 'activate' => 1, 'created_at' => now(), 'status_code' => 'READY_FOR_QC',
                    'status_note' => 'v2 delivery promise KPI', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['SALES_POD', 'MD_CUST_RECV'])->delete();
        Schema::connection('tm2sdi')->dropIfExists('do_delivery_pod');
        Schema::connection('tm2mst')->dropIfExists('cust_receiving_hour');
    }
};
