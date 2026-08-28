<?php

namespace App\Services\Scheduling;

use App\Models\Prd\PrdCrewAssignment;
use App\Models\Prd\PrdWoOperation;

/**
 * Deteksi konflik Crew (Operator Utama + Co-Operator) lintas mesin.
 * Sumber: production_scheduling_tier1.md §5A.9, production_scheduling_tier2.md §4.3.
 *
 * Guard ini WAJIB dipanggil server-side saat commit assignment — termasuk saat
 * drag-drop manual PPIC di Scheduling Board — bukan cuma validasi front-end.
 */
class CrewAvailabilityChecker
{
    /**
     * @param  array<int>  $employeeIds  operator utama + co-operator yang diusulkan
     */
    public function isAvailable(PrdWoOperation $task, array $employeeIds): bool
    {
        if (empty($employeeIds) || ! $task->target_start || ! $task->target_finish) {
            return true; // tak ada crew diusulkan / waktu belum ditentukan -> tak ada yang dicek
        }

        $conflict = PrdCrewAssignment::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('operation_id', '!=', $task->operation_id)
            ->whereHas('operation', function ($q) use ($task) {
                $q->whereIn('task_status', ['ASSIGNED', 'RUNNING'])
                    ->where(function ($q2) use ($task) {
                        // overlap klasik: start_a < finish_b AND finish_a > start_b
                        $q2->where('target_start', '<', $task->target_finish)
                            ->where('target_finish', '>', $task->target_start);
                    });
            })
            ->exists();

        return ! $conflict;
    }

    /**
     * Mengembalikan daftar employee_id yang bentrok, untuk ditampilkan sebagai
     * pesan error yang jelas ke PPIC (bukan cuma "gagal", tapi "siapa yang bentrok").
     *
     * @param  array<int>  $employeeIds
     * @return array<int>
     */
    public function conflictingEmployees(PrdWoOperation $task, array $employeeIds): array
    {
        if (empty($employeeIds) || ! $task->target_start || ! $task->target_finish) {
            return [];
        }

        return PrdCrewAssignment::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('operation_id', '!=', $task->operation_id)
            ->whereHas('operation', function ($q) use ($task) {
                $q->whereIn('task_status', ['ASSIGNED', 'RUNNING'])
                    ->where('target_start', '<', $task->target_finish)
                    ->where('target_finish', '>', $task->target_start);
            })
            ->pluck('employee_id')
            ->unique()
            ->values()
            ->all();
    }
}
