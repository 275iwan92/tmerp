<?php

/*
 * REMARK
 * Tujuan       : Gabungkan beberapa baris emp_employee yang ternyata satu orang ke satu baris survivor, semua rujukan employee_id ikut dipindah.
 * Sumber hulu  : tm2mst.emp_employee dan daftar REFERENCES (tabel, kolom).
 * Input        : Id survivor dan id yang digabung.
 * Output       : Rujukan dipindah, duplikat unik dihapus, dalam satu transaksi.
 * Dipakai oleh : Layar koreksi data pegawai dan perintah penggabungan.
 * Status       : Aktif. Rujukan prod_crew dihapus 2026-10-18 (tabel dihapus).
 */

namespace App\Services\Hrm;

use App\Support\DbName;
use Illuminate\Support\Facades\DB;

// Menggabungkan beberapa baris emp_employee yang ternyata 1 orang ke 1 baris
// "survivor". Semua DB tm2* ada di 1 server MySQL, jadi 1 transaksi di koneksi
// tm2mst + nama tabel ber-qualifier sudah atomik lintas-DB (tidak ada FK fisik
// lintas-DB yang bisa dipakai ON UPDATE).
class EmployeeMergeService
{
    // Posisi placeholder hasil ETL legacy ("Karyawan Legacy - Belum Dipetakan"):
    // dianggap kosong, jadi posisi nyata dari baris yang digabung boleh menggantikannya.
    public const PLACEHOLDER_POSITION_ID = 9;

    // [tabel, kolom]. Tabel ber-unique-index yang memuat employee_id dipindah
    // pakai UPDATE IGNORE; baris yang bentrok (hari/periode sama sudah ada di
    // survivor) = duplikat, dihapus.
    public const REFERENCES = [
        ['tm2mst.att_daily_status', 'employee_id'],
        ['tm2mst.att_finger_raw', 'employee_id'],
        ['tm2mst.att_mobile_gps_log', 'employee_id'],
        ['tm2mst.emp_badge_photo_request', 'employee_id'],
        ['tm2mst.emp_bank_account', 'employee_id'],
        ['tm2mst.emp_bpjs_kesehatan', 'employee_id'],
        ['tm2mst.emp_bpjs_ketenagakerjaan', 'employee_id'],
        ['tm2mst.emp_contract', 'employee_id'],
        ['tm2mst.emp_correction_request', 'employee_id'],
        ['tm2mst.emp_disciplinary', 'employee_id'],
        ['tm2mst.emp_document', 'employee_id'],
        ['tm2mst.emp_education', 'employee_id'],
        ['tm2mst.emp_employee_work_center_skill', 'employee_id'],
        ['tm2mst.emp_family', 'employee_id'],
        ['tm2mst.emp_family_request', 'employee_id'],
        ['tm2mst.emp_leave_balance', 'employee_id'],
        ['tm2mst.emp_leave_request', 'employee_id'],
        ['tm2mst.emp_out_permit_request', 'employee_id'],
        ['tm2mst.emp_overtime_request', 'employee_id'],
        ['tm2mst.emp_resign_request', 'employee_id'],
        ['tm2mst.emp_shift_roster', 'employee_id'],
        ['tm2mst.emp_shift_swap_request', 'requester_employee_id'],
        ['tm2mst.emp_shift_swap_request', 'target_employee_id'],
        ['tm2mst.prod_part_process_employee_rate', 'employee_id'],
        ['tm2mst.sales_person', 'employee_id'],
        ['tm2prd.prd_crew_assignment', 'employee_id'],
        ['tm2pyr.pyr_employee_salary', 'employee_id'],
        ['tm2pyr.pyr_payslip', 'employee_id'],
        ['tm2sys.sec_user', 'employee_id'],
        ['tm2hrr.app_application', 'provisioned_employee_id'],
    ];

    private const FILLABLE_FROM_MERGED = [
        'device_pin', 'nickname', 'gender', 'birth_place', 'birth_date', 'nik_ktp', 'no_kk',
        'npwp_number', 'religion', 'marital_status', 'phone', 'email_personal',
        'village_id_ktp', 'address_detail_ktp', 'village_id_domisili', 'address_detail_domisili',
        'join_date', 'resign_date', 'resign_reason', 'photo_path', 'emergency_contact_name',
        'emergency_contact_relation', 'emergency_contact_phone', 'default_machine_id',
        'default_shift_id', 'payment_frequency',
    ];

    /**
     * @param  int[]  $mergedIds
     * @return array<int, array<string, int>> jumlah baris referensi yang dipindah, per merged id
     */
    public function merge(int $survivorId, array $mergedIds, string $actor, ?string $reason = null, ?string $fullName = null): array
    {
        $db = DB::connection('tm2mst');
        $mergedIds = array_values(array_diff(array_unique($mergedIds), [$survivorId]));

        return $db->transaction(function () use ($db, $survivorId, $mergedIds, $actor, $reason, $fullName) {
            $survivor = (array) $db->table('emp_employee')->where('employee_id', $survivorId)->lockForUpdate()->first();
            if (! $survivor) {
                throw new \RuntimeException("Survivor employee_id={$survivorId} tidak ada.");
            }

            $patch = [];
            $moved = [];
            foreach ($mergedIds as $id) {
                $row = (array) $db->table('emp_employee')->where('employee_id', $id)->lockForUpdate()->first();
                if (! $row) {
                    throw new \RuntimeException("Employee_id={$id} tidak ada.");
                }

                $moved[$id] = $this->moveReferences($db, $id, $survivorId);

                foreach (self::FILLABLE_FROM_MERGED as $col) {
                    if (($survivor[$col] ?? null) === null && ! isset($patch[$col]) && $row[$col] !== null) {
                        $patch[$col] = $row[$col];
                    }
                }
                if ((int) $survivor['position_id'] === self::PLACEHOLDER_POSITION_ID
                    && ! isset($patch['position_id'])
                    && (int) $row['position_id'] !== self::PLACEHOLDER_POSITION_ID) {
                    $patch['position_id'] = $row['position_id'];
                }
                if ($survivor['legacy_employee_id'] === null && ! isset($patch['legacy_employee_id']) && $row['legacy_employee_id'] !== null) {
                    $patch['legacy_employee_id'] = $row['legacy_employee_id'];
                }
                if ($row['activate'] && ! $survivor['activate']) {
                    $patch['activate'] = 1;
                }

                $db->table('emp_employee_merge')->insert([
                    'survivor_employee_id' => $survivorId,
                    'merged_employee_id' => $id,
                    'merged_legacy_employee_id' => $row['legacy_employee_id'],
                    'merged_full_name' => $row['full_name'],
                    'snapshot' => json_encode($row),
                    'moved_rows' => json_encode(array_filter($moved[$id])),
                    'reason' => $reason,
                    'created_at' => now(),
                    'created_by' => $actor,
                ]);

                // Dihapus sebelum patch survivor: legacy_employee_id & device_pin
                // yang diwarisi bisa bentrok unique index kalau baris asal masih ada.
                $db->table('emp_employee')->where('employee_id', $id)->delete();
            }

            if ($fullName !== null && $fullName !== $survivor['full_name']) {
                $patch['full_name'] = $fullName;
            }
            if ($patch) {
                $db->table('emp_employee')->where('employee_id', $survivorId)
                    ->update($patch + ['updated_at' => now(), 'updated_by' => $actor]);
            }

            return $moved;
        });
    }

    /** @return array<string, int> */
    private function moveReferences($db, int $fromId, int $toId): array
    {
        $counts = [];
        foreach (self::REFERENCES as [$ref, $col]) {
            $table = DbName::table($ref);
            $n = $db->update("UPDATE IGNORE {$table} SET {$col} = ? WHERE {$col} = ?", [$toId, $fromId]);
            $dupes = $db->delete("DELETE FROM {$table} WHERE {$col} = ?", [$fromId]);
            $counts["{$ref}.{$col}"] = $n;
            if ($dupes) {
                $counts["{$ref}.{$col}:duplikat_dihapus"] = $dupes;
            }
        }

        return $counts;
    }
}
