<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\InvQcIntensityRule;
use App\Models\Inventory\InvQcRiskClass;
use App\Models\Inventory\InvQcSamplingPlan;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Sys\SysConstant;

// Rencana sampling QC (Q-01, Q-10): kelas risiko item -> intensitas menurut riwayat lulus-gagal supplier -> ukuran sampel menurut jumlah lot.
//   Kelas risiko : item (inv_qc_risk_class ITEM) lebih khusus daripada kategori; kategori terdalam menang. Tanpa data = B (konservatif).
//   Intensitas   : awal menurut kelas (inv_qc_intensity_rule). Supplier baru (kurang dari INV_QC_NEW_SUPPLIER_LOTS lot diputuskan) = TIGHTENED.
//                  Ada reject dan belum lulus recover_pass lot berturut-turut sesudahnya = TIGHTENED. Lulus pass_to_reduced lot berturut-turut = REDUCED.
//   Ukuran sampel: inv_qc_sampling_plan menurut intensitas dan rentang lot; SKIP = 0 (cukup sertifikat). Sampel tidak melebihi jumlah lot.
// Riwayat supplier dibaca dari keputusan SAMPLING_DOCK (inv_qc_decision); kejadian kurang kirim belum ikut (Q-10, P2).
final class QcSamplingService
{
    public const DEFAULT_CLASS = 'B';

    public const CLASSES = ['A' => 'A: high risk', 'B' => 'B: standard', 'C' => 'C: low risk'];

    public const INTENSITIES = ['TIGHTENED' => 'Tightened', 'NORMAL' => 'Normal', 'REDUCED' => 'Reduced', 'SKIP' => 'Skip (certificate)'];

    /**
     * @return array{risk_class: string, intensity: string, reason: string, sample_size: int, aql_pct: float|null, lots_decided: int, passes_in_row: int}
     */
    public static function planFor(int $itemId, ?int $supplierId, float $lotQty): array
    {
        $class = self::riskClass($itemId);
        $rule = InvQcIntensityRule::query()->where('risk_class', $class)->where('activate', true)->first();
        $history = self::history($supplierId);
        [$intensity, $reason] = self::intensity($rule, $history);

        return ['risk_class' => $class, 'intensity' => $intensity, 'reason' => $reason, 'sample_size' => self::sampleSize($intensity, $lotQty),
            'aql_pct' => $rule?->aql_pct !== null ? (float) $rule->aql_pct : null, 'lots_decided' => $history['lots'], 'passes_in_row' => $history['passes_in_row']];
    }

    public static function riskClass(int $itemId): string
    {
        $item = InvQcRiskClass::query()->where('scope_key', 'I:'.$itemId)->where('activate', true)->value('risk_class');
        if ($item) {
            return (string) $item;
        }
        $categoryIds = self::categoryChain($itemId);
        if ($categoryIds === []) {
            return self::DEFAULT_CLASS;
        }
        $byCategory = InvQcRiskClass::query()->where('scope_type', 'CATEGORY')->where('activate', true)->whereIn('category_id', $categoryIds)->pluck('risk_class', 'category_id');
        foreach (array_reverse($categoryIds) as $id) {
            if (isset($byCategory[$id])) {
                return (string) $byCategory[$id];
            }
        }

        return self::DEFAULT_CLASS;
    }

    /** @return list<int> id kategori level 1..5 milik item, dari yang paling atas */
    private static function categoryChain(int $itemId): array
    {
        $material = MatRaw::query()->where('material_id', $itemId)->first();
        if (! $material) {
            return [];
        }
        $ids = [];
        $parent = null;
        for ($level = 1; $level <= 5; $level++) {
            $name = trim((string) $material->getAttribute('category'.$level));
            if ($name === '') {
                break;
            }
            $found = MatCategory::query()->where('level', $level)->where('category_name', $name)->when($parent, fn ($q) => $q->where('parent_id', $parent))->value('id_category');
            if (! $found) {
                break;
            }
            $ids[] = $parent = (int) $found;
        }

        return $ids;
    }

    /** @return array{lots: int, passes_in_row: int, has_reject: bool} dari keputusan sampling di dok supplier ini, terbaru dulu */
    public static function history(?int $supplierId): array
    {
        if (! $supplierId) {
            return ['lots' => 0, 'passes_in_row' => 0, 'has_reject' => false];
        }
        $decisions = InvQcDecision::query()->where('supplier_id', $supplierId)->where('decision_type', 'SAMPLING_DOCK')->orderByDesc('id_decision')->pluck('decision');
        $row = 0;
        foreach ($decisions as $decision) {
            if ($decision !== 'RELEASED') {
                break;
            }
            $row++;
        }

        return ['lots' => $decisions->count(), 'passes_in_row' => $row, 'has_reject' => $decisions->contains('REJECTED')];
    }

    /** @param array{lots: int, passes_in_row: int, has_reject: bool} $history @return array{0: string, 1: string} */
    private static function intensity(?InvQcIntensityRule $rule, array $history): array
    {
        if ($history['lots'] < (int) SysConstant::number('INV_QC_NEW_SUPPLIER_LOTS', 5)) {
            return ['TIGHTENED', 'NEW_SUPPLIER'];
        }
        $start = $rule?->start_intensity ?? 'NORMAL';
        if ($history['has_reject'] && $history['passes_in_row'] < (int) ($rule?->recover_pass ?? 5)) {
            return ['TIGHTENED', 'RECOVERING'];
        }
        if ($start !== 'SKIP' && $history['passes_in_row'] >= (int) ($rule?->pass_to_reduced ?? 10)) {
            return ['REDUCED', 'CONSISTENT_SUPPLIER'];
        }

        return [$start, 'RISK_CLASS'];
    }

    public static function sampleSize(string $intensity, float $lotQty): int
    {
        if ($intensity === 'SKIP' || $lotQty <= 0) {
            return 0;
        }
        // Baris dengan batas bawah terbesar yang masih <= jumlah lot; jumlah lot pecahan di antara dua rentang ikut rentang di bawahnya.
        $size = InvQcSamplingPlan::query()->where('intensity', $intensity)->where('activate', true)->where('lot_from', '<=', $lotQty)->orderByDesc('lot_from')->value('sample_size')
            ?? InvQcSamplingPlan::query()->where('intensity', $intensity)->where('activate', true)->orderBy('lot_from')->value('sample_size');

        return (int) min((int) $size, (int) ceil($lotQty));
    }
}
