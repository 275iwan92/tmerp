<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Struktur pimpinan (user 2026-09-28, docs/attachments/struktur_pimpinan_2026-09-28.webp) + jabatan baru:
//   Sania Maura  -> Human Resource Officer (di bawah Human Capital Dept.)
//   Ringga       -> Product Development Officer (di bawah Product Costing: ProDev+ProCost satu rantai rahasia)
//   Yuna Puji, Purna -> Senior Staff Sales & Marketing (di bawah Sales & Marketing Manager)
// dan aktifkan pimpinan & orang-orang tsb. Pegawai dicari lewat employee_code (tetap), bukan nama.
return new class extends Migration
{
    private const POSITIONS = [
        // code => [nama, induk (position_code), level, department dari induk]
        'HROFFICER' => ['Human Resource Officer', 'HUMANCAPITALDEP', 1],
        'PRODEVOFFICER' => ['Product Development Officer', 'PRODUCTCOSTING', 1],
        'SRSTAFFSALESMKT' => ['Senior Staff Sales & Marketing', 'SALESMARKETINGM', 1],
    ];

    // employee_code => position_code baru (null = jabatan tetap, hanya diaktifkan)
    private const PEOPLE = [
        'TM-00491' => null,              // Carrij Soehendra — Presiden Direktur
        'TM-00492' => null,              // Puspita Dewi — Direktur F&A
        'TM-00150' => 'HROFFICER',       // Sania Maura Fitriani
        'TM-00146' => 'PRODEVOFFICER',   // Ringga Gita Lesmana (sebelumnya Pre Press SPV)
        'TM-00121' => 'SRSTAFFSALESMKT', // Yuna Puji Astuti (sebelumnya B2B Sales)
        'TM-00502' => 'SRSTAFFSALESMKT', // Purna (sebelumnya B2B Sales)
    ];

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();

        foreach (self::POSITIONS as $code => [$name, $parentCode, $level]) {
            if ($db->table('org_position')->where('position_code', $code)->exists()) {
                continue;
            }
            $parent = $db->table('org_position')->where('position_code', $parentCode)->first();
            $db->table('org_position')->insert([
                'position_code' => $code, 'position_name' => $name, 'reports_to_position_id' => $parent?->position_id,
                'department_id' => $parent?->department_id, 'level_id' => $level, 'activate' => 1,
                'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system', 'updated_by' => 'system',
            ]);
        }

        $posId = $db->table('org_position')->whereIn('position_code', array_keys(self::POSITIONS))->pluck('position_id', 'position_code');
        foreach (self::PEOPLE as $empCode => $newPos) {
            $db->table('emp_employee')->where('employee_code', $empCode)->update(array_filter([
                'activate' => 1, 'position_id' => $newPos ? $posId[$newPos] : null, 'updated_at' => $now, 'updated_by' => 'system',
            ], fn ($v) => $v !== null));
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis: jabatan & status pegawai adalah data nyata.
    }
};
