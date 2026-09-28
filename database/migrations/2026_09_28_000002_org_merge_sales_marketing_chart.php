<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Gabungan 2 bagan struktur dari user (2026-09-28): bagan pimpinan + bagan Sales & Marketing
// (bagan kedua ditempel di chat; isi dicatat di memori project_leadership_structure). Bagan = landasan, jadi:
//   Ringga -> NPD (Marketing Div.), bukan Product Development Officer di bawah Product Costing;
//   Yuna Puji & Purna -> B2B Sales (Offset Div.), bukan jabatan baru Senior Staff Sales & Marketing.
// Jabatan PRODEVOFFICER & SRSTAFFSALESMKT (dibuat 2026_09_28_000001) jadi kosong -> nonaktif, tidak dihapus.
return new class extends Migration
{
    private const PEOPLE = [
        'TM-00146' => 'NPD',       // Ringga Gita Lesmana
        'TM-00121' => 'B2BSALES',  // Yuna Puji Astuti
        'TM-00502' => 'B2BSALES',  // Purna
    ];

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();
        $pos = $db->table('org_position')->whereIn('position_code', array_values(self::PEOPLE))->pluck('position_id', 'position_code');

        foreach (self::PEOPLE as $emp => $code) {
            if (isset($pos[$code])) {
                $db->table('emp_employee')->where('employee_code', $emp)->update(['position_id' => $pos[$code], 'activate' => 1, 'updated_at' => $now, 'updated_by' => 'system']);
            }
        }

        foreach (['PRODEVOFFICER', 'SRSTAFFSALESMKT'] as $code) {
            $id = $db->table('org_position')->where('position_code', $code)->value('position_id');
            if ($id && ! $db->table('emp_employee')->where('position_id', $id)->exists()) {
                $db->table('org_position')->where('position_id', $id)->update(['activate' => 0, 'updated_at' => $now, 'updated_by' => 'system']);
            }
        }
    }

    public function down(): void
    {
        // Data struktur nyata: tidak dikembalikan otomatis.
    }
};
