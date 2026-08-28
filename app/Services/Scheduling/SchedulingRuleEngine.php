<?php

namespace App\Services\Scheduling;

use App\Models\Prd\PrdSchedulingRule;
use App\Models\Prd\PrdWoOperation;
use Illuminate\Support\Collection;

/**
 * Constraint Rule Library — Batching & Sequencing sebagai DATA, bukan kode.
 * Sumber: production_scheduling_tier1.md §5A.7, production_scheduling_tier2.md §4.2.
 *
 * Batching: gabung beberapa Task berbeda WO jadi satu unit terjadwal (mis. dieline sama).
 * Sequencing: penghalus urutan LOKAL di antara task berprioritas setara — TIDAK BOLEH
 * mengubah hasil Composite Priority Score Lapis 0-2 (hot job/CR/EDD tetap batas atas).
 */
class SchedulingRuleEngine
{
    /**
     * @param  Collection<int, PrdWoOperation>  $candidates
     * @return Collection<int, Collection<int, PrdWoOperation>> daftar "grup" — grup isi 1
     *         task berarti tak ada batching yang cocok untuknya
     */
    public function applyBatching(Collection $candidates, int $machineId, string $processName): Collection
    {
        $rules = $this->activeRulesFor($machineId, $processName, 'BATCHING');

        if ($rules->isEmpty()) {
            return $candidates->map(fn (PrdWoOperation $t) => collect([$t]));
        }

        $grouped = collect();
        $consumed = collect();

        foreach ($rules as $rule) {
            $field = $rule->condition_field; // mis. 'dieline_ref'

            $byValue = $candidates
                ->reject(fn (PrdWoOperation $t) => $consumed->contains($t->operation_id))
                ->groupBy(fn (PrdWoOperation $t) => data_get($t, $field));

            foreach ($byValue as $value => $group) {
                if ($value === null || $group->count() < 2) {
                    continue; // batching cuma relevan kalau memang ada >=2 task senilai sama
                }
                $grouped->push($group->values());
                $consumed = $consumed->merge($group->pluck('operation_id'));
            }
        }

        // Sisanya (tak kena rule apa pun / cuma sendirian) tetap jadi grup isi 1
        $remaining = $candidates->reject(fn (PrdWoOperation $t) => $consumed->contains($t->operation_id));
        foreach ($remaining as $t) {
            $grouped->push(collect([$t]));
        }

        return $grouped;
    }

    /**
     * Penghalus urutan lokal — dipanggil SETELAH Composite Priority Score mengurutkan.
     * v1: satu rule aktif diterapkan (Tier1 §5A.7 catatan) — cukup untuk kebutuhan awal,
     * perlu diperluas kalau >1 rule sequencing aktif bersamaan untuk resource yang sama.
     *
     * @param  Collection<int, PrdWoOperation>  $sortedByPriority  sudah terurut dari rank()
     */
    public function applySequencing(Collection $sortedByPriority, int $machineId, string $processName): Collection
    {
        $rules = $this->activeRulesFor($machineId, $processName, 'SEQUENCING');

        if ($rules->isEmpty() || $sortedByPriority->count() < 2) {
            return $sortedByPriority;
        }

        $rule = $rules->first();
        $field = $rule->condition_field;
        $direction = $rule->condition_operator === 'SORT_DESC' ? 'desc' : 'asc';

        return $direction === 'asc'
            ? $sortedByPriority->sortBy(fn (PrdWoOperation $t) => data_get($t, $field))->values()
            : $sortedByPriority->sortByDesc(fn (PrdWoOperation $t) => data_get($t, $field))->values();
    }

    private function activeRulesFor(int $machineId, string $processName, string $type): Collection
    {
        return PrdSchedulingRule::query()
            ->active()
            ->forResource($machineId, $processName)
            ->where('rule_type', $type)
            ->get();
    }
}
