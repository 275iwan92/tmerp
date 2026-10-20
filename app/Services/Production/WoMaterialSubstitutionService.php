<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Substitusi bahan pada Bill of Material Work Order: baris BOM (snapshot Inquiry iqp_part_material, TETAP beku) dengan bahan pengganti, pratinjau bebas (UP-count, efisiensi, waste, qty kebutuhan, selisih biaya) sebelum diajukan, lalu Approve / Reject / Undo oleh Manager Operasional ke atas (substitusi dapat menggeser HPP).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::production.wo-entry (billOfMaterialRows, substitutionPreview, submitSubstitution, approve / reject / undo); docs/production_scheduling_wo_generation_delta.md 12; hitung UP memakai ImpositionEngine dan layout Imposition CONFIRMED part itu (flat, gap, gripper, margin tetap; hanya plano diganti).
 * Input        : WO, id baris BOM (id_part_material), id bahan pengganti, panjang x lebar (mm), alasan; id substitusi + alasan tolak; user.
 * Output       : prd_wo_material_substitution (PENDING / APPROVED / REJECTED, satu baris is_active per baris BOM per WO); AuthorizationException bila bukan Manager Operasional ke atas; ValidationException (kunci material / size / reason / document) bila data atau status salah.
 * Dipakai oleh : routes/v2_production.php (v2.production.wo.sub.*), layar production-wo (kartu Material substitution di tab Bill of Material), tes WoExecutionServiceTest.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20). Dikencangkan dari v1: Approve hanya untuk PENDING, Undo hanya untuk yang aktif, dan satu-aktif-per-baris dijaga per WO (v1 ikut menonaktifkan substitusi WO lain pada baris BOM yang sama).
 */

namespace App\Services\Production;

use App\Models\Master\MatRaw;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoMaterialSubstitution;
use App\Models\Sdi\IqpLayout;
use App\Models\Sdi\IqpPartMaterial;
use App\Models\Sys\SecUser;
use App\Services\Imposition\ImpositionEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WoMaterialSubstitutionService
{
    private function manager(SecUser $user, string $what): void
    {
        if (! $user->isOpsManagerOrAbove()) {
            throw new AuthorizationException('Only an Operations Manager or above can '.$what.'.');
        }
    }

    /** @return Collection<int, IqpPartMaterial> baris BOM snapshot Inquiry part WO ini */
    public function lines(PrdWo $wo): Collection
    {
        return $wo->source_iqp_part_id ? IqpPartMaterial::where('part_id', $wo->source_iqp_part_id)->orderBy('layer_seq')->get() : collect();
    }

    public function confirmedLayout(PrdWo $wo): ?IqpLayout
    {
        return $wo->source_iqp_part_id ? IqpLayout::where('part_id', $wo->source_iqp_part_id)->where('stat_confirm', true)->first() : null;
    }

    /** Histori lengkap satu baris BOM (PENDING / APPROVED / REJECTED, aktif atau sudah di-undo), terbaru dulu. */
    public function history(PrdWo $wo, int $partMaterialId): Collection
    {
        return PrdWoMaterialSubstitution::where('wo_id', $wo->wo_id)->where('iqp_part_material_id', $partMaterialId)->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    private function available(int $materialId): float
    {
        return (float) DB::connection('tm2inv')->table('v_rm_available')->where('material_id', $materialId)->sum('qty_available');
    }

    /**
     * Baris BOM dengan Qty Kebutuhan / Tersedia / Harus Dibeli; substitusi APPROVED aktif menimpa TAMPILAN baris saja.
     * Qty kebutuhan = qty WO / UP per lembar (layout CONFIRMED; tanpa layout, UP = 1).
     *
     * @return Collection<int, object>
     */
    public function rows(PrdWo $wo): Collection
    {
        $layout = $this->confirmedLayout($wo);
        $up = $layout && $layout->up_count > 0 ? (int) $layout->up_count : 1;

        return $this->lines($wo)->map(function (IqpPartMaterial $mat) use ($wo, $up) {
            $history = $this->history($wo, $mat->id_part_material);
            $active = $history->firstWhere('is_active', true);
            $pending = $history->firstWhere('approval_status', 'PENDING');
            if ($active) {
                $subUp = $active->computed_up_count > 0 ? $active->computed_up_count : $up;
                $need = ceil((float) $wo->qty / $subUp);
                $have = $this->available((int) $active->substitute_rawmat_id);
                $unit = MatRaw::find($active->substitute_rawmat_id)?->uom_stock ?: 'Pcs';
                $name = (string) $active->substitute_rawmat_name;
                $spec = tf('Substitute for: :name', ['name' => $mat->rawmat_name]);
            } else {
                $need = ceil((float) $wo->qty / $up);
                $have = $mat->rawmat_id ? $this->available((int) $mat->rawmat_id) : 0.0;
                $unit = ($mat->rawmat_id ? MatRaw::find($mat->rawmat_id)?->uom_stock : null) ?: 'Pcs';
                $name = (string) $mat->rawmat_name;
                $spec = trim(collect([$mat->gram_weight ? number_format((float) $mat->gram_weight, 0, ',', '.').' gsm' : null, $mat->flute_type ?: null, $mat->is_primary ? t('Primary') : null])->filter()->implode(' · '));
            }

            return (object) ['id' => (int) $mat->id_part_material, 'name' => $name, 'spec' => $spec, 'unit' => $unit, 'need' => $need, 'have' => $have, 'to_buy' => max(0, $need - $have),
                'substituted' => (bool) $active, 'active' => $active, 'pending' => $pending];
        });
    }

    /** @return Collection<int, MatRaw> pencarian bahan pengganti (min 2 huruf) */
    public function searchMaterial(string $query): Collection
    {
        return mb_strlen(trim($query)) < 2 ? collect() : MatRaw::where('activate', true)
            ->where(fn ($q) => $q->where('material_name', 'like', "%{$query}%")->orWhere('material_code', 'like', "%{$query}%"))->orderBy('material_name')->limit(15)->get();
    }

    /**
     * Pratinjau (tidak menyimpan apa pun). Kembalian berisi 'error' bila belum bisa dihitung.
     *
     * @return array<string, mixed>
     */
    public function preview(PrdWo $wo, int $partMaterialId, int $rawmatId, float $lengthMm, float $widthMm): array
    {
        if ($rawmatId <= 0 || $lengthMm <= 0 || $widthMm <= 0) {
            return ['error' => 'Choose the substitute material and fill in its size first.'];
        }
        $layout = $this->confirmedLayout($wo);
        if (! $layout) {
            return ['error' => 'The Imposition layout of this part is not confirmed yet: the UP count cannot be calculated.'];
        }
        $result = app(ImpositionEngine::class)->run($lengthMm, $widthMm, (float) $layout->flat_w_mm, (float) $layout->flat_h_mm, (float) $layout->gap_mm, (float) $layout->gripper_mm, (float) $layout->margin_mm);
        if ($result['up'] <= 0) {
            return ['error' => 'The substitute material is too small: the part does not fit at all at this size.'];
        }

        $mat = $this->lines($wo)->firstWhere('id_part_material', $partMaterialId);
        $originalUp = $layout->up_count > 0 ? (int) $layout->up_count : 1;
        $original = $mat?->rawmat_id ? MatRaw::find($mat->rawmat_id) : null;
        $substitute = MatRaw::find($rawmatId);
        $needNew = ceil((float) $wo->qty / $result['up']);
        $needOld = ceil((float) $wo->qty / $originalUp);
        $have = $this->available($rawmatId);
        $oldCost = $original ? (float) ($original->avg_unitprice ?: $original->last_unitprice ?: 0) : null;
        $newCost = $substitute ? (float) ($substitute->avg_unitprice ?: $substitute->last_unitprice ?: 0) : null;

        return ['up_count' => $result['up'], 'efficiency_pct' => $result['efficiency_pct'], 'waste_pct' => $result['waste_pct'], 'need_new' => $needNew, 'have' => $have,
            'output_pcs' => floor($have * $result['up']), 'original_unit_cost' => $oldCost, 'substitute_unit_cost' => $newCost,
            'cost_delta' => ($oldCost !== null && $newCost !== null) ? ($needNew * $newCost) - ($needOld * $oldCost) : null];
    }

    public function submit(PrdWo $wo, int $partMaterialId, int $rawmatId, float $lengthMm, float $widthMm, ?string $reason, SecUser $user): PrdWoMaterialSubstitution
    {
        if (! $this->lines($wo)->contains('id_part_material', $partMaterialId)) {
            throw ValidationException::withMessages(['document' => 'This material line does not belong to the Work Order.']);
        }
        if ($rawmatId <= 0 || $lengthMm <= 0 || $widthMm <= 0) {
            throw ValidationException::withMessages(['material' => 'Choose the substitute material and fill in its size first.']);
        }
        if ($this->history($wo, $partMaterialId)->firstWhere('approval_status', 'PENDING')) {
            throw ValidationException::withMessages(['document' => 'Another substitution for this line is still waiting for approval.']);
        }
        $p = $this->preview($wo, $partMaterialId, $rawmatId, $lengthMm, $widthMm);
        if (isset($p['error'])) {
            throw ValidationException::withMessages(['size' => $p['error']]);
        }
        $mat = $this->lines($wo)->firstWhere('id_part_material', $partMaterialId);

        return PrdWoMaterialSubstitution::create(['wo_id' => $wo->wo_id, 'iqp_part_material_id' => $partMaterialId, 'original_rawmat_id' => $mat?->rawmat_id,
            'substitute_rawmat_id' => $rawmatId, 'substitute_rawmat_name' => (string) MatRaw::find($rawmatId)?->material_name, 'substitute_length_mm' => $lengthMm, 'substitute_width_mm' => $widthMm,
            'computed_up_count' => $p['up_count'], 'computed_efficiency_pct' => $p['efficiency_pct'], 'computed_waste_pct' => $p['waste_pct'], 'computed_qty_output_pcs' => $p['output_pcs'],
            'original_unit_cost' => $p['original_unit_cost'], 'substitute_unit_cost' => $p['substitute_unit_cost'], 'total_cost_delta' => $p['cost_delta'],
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null, 'approval_status' => 'PENDING', 'is_active' => false, 'created_by' => $user->login]);
    }

    public function approve(int $id, SecUser $user): PrdWoMaterialSubstitution
    {
        $this->manager($user, 'approve a material substitution');

        return DB::connection('tm2prd')->transaction(function () use ($id, $user) {
            $sub = PrdWoMaterialSubstitution::lockForUpdate()->findOrFail($id);
            if ($sub->approval_status !== 'PENDING') {
                throw ValidationException::withMessages(['document' => 'Only a pending substitution can be approved.']);
            }
            // satu aktif per baris BOM per WO
            PrdWoMaterialSubstitution::where('wo_id', $sub->wo_id)->where('iqp_part_material_id', $sub->iqp_part_material_id)->where('is_active', true)
                ->update(['is_active' => false, 'updated_by' => $user->login]);
            $sub->update(['approval_status' => 'APPROVED', 'approved_by' => $user->login, 'approved_at' => now(), 'is_active' => true]);

            return $sub;
        });
    }

    public function reject(int $id, string $reason, SecUser $user): PrdWoMaterialSubstitution
    {
        $this->manager($user, 'reject a material substitution');
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'The rejection reason must be at least 5 characters.']);
        }

        return DB::connection('tm2prd')->transaction(function () use ($id, $reason, $user) {
            $sub = PrdWoMaterialSubstitution::lockForUpdate()->findOrFail($id);
            if ($sub->approval_status !== 'PENDING') {
                throw ValidationException::withMessages(['document' => 'Only a pending substitution can be rejected.']);
            }
            $sub->update(['approval_status' => 'REJECTED', 'approved_by' => $user->login, 'approved_at' => now(), 'rejection_reason' => $reason]);

            return $sub;
        });
    }

    /** Undo bukan pengajuan baru: menonaktifkan substitusi aktif dan mengembalikan tampilan BOM ke bahan asli. */
    public function undo(int $id, SecUser $user): PrdWoMaterialSubstitution
    {
        $this->manager($user, 'cancel an active substitution');

        return DB::connection('tm2prd')->transaction(function () use ($id, $user) {
            $sub = PrdWoMaterialSubstitution::lockForUpdate()->findOrFail($id);
            if (! $sub->is_active) {
                throw ValidationException::withMessages(['document' => 'Only an active substitution can be cancelled.']);
            }
            $sub->update(['is_active' => false, 'undone_by' => $user->login, 'undone_at' => now()]);

            return $sub;
        });
    }
}
