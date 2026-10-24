<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hak menu terbatas (Jo 2026-10-24, mockup): ringga hanya Pro-Dev, wiwik hanya Pro-Cost, sales (ehaningtias/eni, yuna, widita) hanya
// Inquiry, Quotation dan menu penjualan. Hak modul tak cukup halus, jadi AppMenu menyaring per kode menu lewat App\Services\Security\MenuScope
// (kode grup => cabang menu). Grup di sini: PRODEV_STAFF (hak modul + cakupan), MENU_PROCOST dan MENU_SALES_INQ (cakupan saja, ditambahkan di
// samping grup peran lama supaya cek peran seperti harga jual tidak berubah). Bisa diulang. Login yang tak ada dilewati.
return new class extends Migration
{
    private const GROUPS = [
        // kode => [nama, level, [modul => [input, approve, export]]]
        'PRODEV_STAFF' => ['Staf Product Development', 2, ['SALES' => [0, 0, 0], 'PROD' => [1, 0, 0]]],
        'MENU_PROCOST' => ['Cakupan Menu: Pro-Cost', 1, []],
        'MENU_SALES_INQ' => ['Cakupan Menu: Inquiry dan Penjualan', 1, []],
    ];

    // login => [grup yang ditambah, grup yang dilepas]. 'eni' = nama login lama ehaningtias.
    private const USERS = [
        'ringga' => ['PRODEV_STAFF', 'COSTING_STAFF'],
        'wiwik' => ['MENU_PROCOST', null],
        'ehaningtias' => ['MENU_SALES_INQ', null],
        'eni' => ['MENU_SALES_INQ', null],
        'yuna' => ['MENU_SALES_INQ', null],
        'widita' => ['MENU_SALES_INQ', null],
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $gid = fn (string $code) => $db->table('sec_group')->where('group_code', $code)->value('id_group');

        foreach (self::GROUPS as $code => [$name, $level, $modules]) {
            if (! $gid($code)) {
                $db->table('sec_group')->insert(['group_code' => $code, 'group_name' => $name, 'mgmt_level' => $level, 'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            }
            foreach ($modules as $module => [$input, $approve, $export]) {
                $moduleId = $db->table('sec_module')->where('module_code', $module)->value('id_module');
                if ($moduleId) {
                    $db->table('sec_group_permission')->updateOrInsert(['group_id' => $gid($code), 'module_id' => $moduleId],
                        ['can_access' => 1, 'can_input' => $input, 'can_approve' => $approve, 'can_export' => $export, 'created_by' => 'migration']);
                }
            }
        }

        foreach (self::USERS as $login => [$add, $drop]) {
            $userId = $db->table('sec_user')->where('login', $login)->value('id_user');
            if (! $userId) {
                continue;
            }
            $db->table('sec_user_group')->insertOrIgnore(['user_id' => $userId, 'group_id' => $gid($add), 'created_at' => now()]);
            if ($drop) {
                $db->table('sec_user_group')->where('user_id', $userId)->where('group_id', $gid($drop))->delete();
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        $ringga = $db->table('sec_user')->where('login', 'ringga')->value('id_user');
        if ($ringga) {
            $db->table('sec_user_group')->insertOrIgnore(['user_id' => $ringga, 'group_id' => $db->table('sec_group')->where('group_code', 'COSTING_STAFF')->value('id_group'), 'created_at' => now()]);
        }
        $ids = $db->table('sec_group')->whereIn('group_code', array_keys(self::GROUPS))->pluck('id_group');
        $db->table('sec_user_group')->whereIn('group_id', $ids)->delete();
        $db->table('sec_group_permission')->whereIn('group_id', $ids)->delete();
        $db->table('sec_group')->whereIn('id_group', $ids)->delete();
    }
};
