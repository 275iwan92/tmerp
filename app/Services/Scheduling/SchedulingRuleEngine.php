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
    /**
     * FIX 2026-09-03 (docs/BACKLOG.md §6 "applySequencing masih 1-rule-only"):
     * dulu cuma `$rules->first()` -- rule SEQUENCING ke-2/ke-3 dst utk resource
     * yang sama diam-diam diabaikan. `prd_scheduling_rule` TAK PUNYA kolom
     * priority/sort_order eksplisit, jadi urutan aplikasi rule dipakai
     * `rule_id` ASC (rule yang dibuat lebih dulu = kriteria UTAMA, rule
     * berikutnya = tie-breaker) -- dijalankan MUNDUR (tie-breaker duluan,
     * utama terakhir) supaya multi-key sort benar via stable sort PHP 8+
     * (usort/uasort terjamin stabil sejak PHP 8.0, dipakai Collection::sortBy).
     * 1 rule = perilaku identik sebelumnya (backward-compatible).
     */
    public function applySequencing(Collection $sortedByPriority, int $machineId, string $processName): Collection
    {
        $rules = $this->activeRulesFor($machineId, $processName, 'SEQUENCING')->sortBy('rule_id');

        if ($rules->isEmpty() || $sortedByPriority->count() < 2) {
            return $sortedByPriority;
        }

        $result = $sortedByPriority;

        foreach ($rules->reverse() as $rule) {
            $field = $rule->condition_field;
            $direction = $rule->condition_operator === 'SORT_DESC' ? 'desc' : 'asc';

            $result = $direction === 'asc'
                ? $result->sortBy(fn (PrdWoOperation $t) => data_get($t, $field))->values()
                : $result->sortByDesc(fn (PrdWoOperation $t) => data_get($t, $field))->values();
        }

        return $result;
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
