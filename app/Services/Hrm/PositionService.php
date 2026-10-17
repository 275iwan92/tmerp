<?php

/*
 * REMARK
 * Tujuan       : Master Position dari layar v2 hrm-position-form: simpan jabatan (kode, nama, departemen, level, atasan), job description (hanya HR Officer), aktif / nonaktif, dan KPI jabatan.
 * Sumber hulu  : Aturan sama dengan layar v1 pages::hrm.position (zona beku: kode, nama, departemen, level wajib; tanpa siklus melapor; job description hanya diisi HR Officer, keputusan user 2026-09-13).
 * Input        : Array isian form, jabatan, user yang menyimpan.
 * Output       : org_position dan org_position_kpi berubah; ValidationException bila aturan dilanggar (termasuk total bobot KPI > 100%).
 * Dipakai oleh : routes/v2.php (hrm.position.*), layar hrm-position-form, tes PositionServiceTest.
 * Status       : Aktif. Hasil KPI bulanan dan skor kemampuan menyusul (tahap B); layar ini hanya mendefinisikan KPI.
 */

namespace App\Services\Hrm;

use App\Models\Master\OrgPosition;
use App\Models\Master\OrgPositionKpi;
use App\Models\Sys\SecUser;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PositionService
{
    public function canEditJobDesk(SecUser $user): bool
    {
        return $user->isHrOfficer();
    }

    public function save(?OrgPosition $p, array $in, SecUser $user): OrgPosition
    {
        $d = Validator::make($in, [
            'position_code' => ['required', 'string', 'max:20'],
            'position_name' => ['required', 'string', 'max:150'],
            'department_id' => ['required', 'integer', 'exists:tm2mst.org_department,department_id'],
            'level_id' => ['required', 'integer', 'exists:tm2mst.org_level,level_id'],
            'reports_to_position_id' => ['nullable', 'integer', 'exists:tm2mst.org_position,position_id'],
            'job_description' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        Validator::make($d, ['position_code' => [Rule::unique('tm2mst.org_position', 'position_code')->where('department_id', $d['department_id'])->ignore($p?->position_id, 'position_id')]],
            ['position_code.unique' => 'This code is already used in the department.'])->validate();

        $reports = ($d['reports_to_position_id'] ?? null) ?: null;
        if ($p && $reports && $this->wouldCreateCycle($p->position_id, (int) $reports)) {
            throw ValidationException::withMessages(['reports_to_position_id' => 'Not possible: this position would report to one of its own subordinates (cycle).']);
        }

        $payload = ['position_code' => $d['position_code'], 'position_name' => $d['position_name'], 'department_id' => $d['department_id'], 'level_id' => $d['level_id'],
            'reports_to_position_id' => $reports, 'updated_by' => $user->login];
        // Job description hanya HR Officer (guard di server, bukan sekadar disembunyikan di layar).
        if ($this->canEditJobDesk($user) && array_key_exists('job_description', $in)) {
            $payload['job_description'] = trim((string) ($d['job_description'] ?? '')) ?: null;
            $payload['job_description_updated_by'] = $user->login;
            $payload['job_description_updated_at'] = now();
        }

        if ($p) {
            $p->update($payload);

            return $p;
        }

        return OrgPosition::create($payload + ['activate' => true, 'created_by' => $user->login]);
    }

    public function setActive(OrgPosition $p, bool $active, SecUser $user): void
    {
        $p->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    // KPI: bobot semua KPI aktif satu jabatan tidak boleh melebihi 100%; pas 100% dibutuhkan sebelum hasil bisa dimasukkan.
    public function addKpi(OrgPosition $p, array $in, SecUser $user): OrgPositionKpi
    {
        abort_unless($this->canEditJobDesk($user), 403);
        $in['target_value'] = Locale::input($in['target_value'] ?? null) ?? ($in['target_value'] ?? null);
        $in['weight_pct'] = Locale::input($in['weight_pct'] ?? null) ?? ($in['weight_pct'] ?? null);
        $d = Validator::make($in, [
            'kpi_name' => ['required', 'string', 'max:150'],
            'unit' => ['nullable', 'string', 'max:20'],
            'direction' => ['required', 'in:'.implode(',', array_keys(OrgPositionKpi::DIRECTIONS))],
            'target_value' => ['required', 'numeric', 'gt:0'],
            'weight_pct' => ['required', 'numeric', 'gt:0', 'max:100'],
        ])->validate();

        if ($this->totalWeight($p) + (float) $d['weight_pct'] > 100.0001) {
            throw ValidationException::withMessages(['weight_pct' => 'The total weight of the KPIs cannot exceed 100%.']);
        }

        return OrgPositionKpi::create(['position_id' => $p->position_id, 'kpi_name' => $d['kpi_name'], 'unit' => ($d['unit'] ?? null) ?: null, 'direction' => $d['direction'],
            'target_value' => $d['target_value'], 'weight_pct' => $d['weight_pct'], 'activate' => true, 'created_by' => $user->login, 'updated_by' => $user->login]);
    }

    public function removeKpi(OrgPositionKpi $k, SecUser $user): void
    {
        abort_unless($this->canEditJobDesk($user), 403);
        $k->update(['activate' => false, 'updated_by' => $user->login]);
    }

    public function totalWeight(OrgPosition $p): float
    {
        return (float) OrgPositionKpi::where('position_id', $p->position_id)->where('activate', true)->sum('weight_pct');
    }

    /** @return Collection<int, OrgPositionKpi> */
    public function kpis(OrgPosition $p)
    {
        return OrgPositionKpi::where('position_id', $p->position_id)->where('activate', true)->orderBy('id_position_kpi')->get();
    }

    // Atasan tidak boleh salah satu keturunan jabatan ini (bagan jadi loop tak terhingga).
    private function wouldCreateCycle(int $editingId, int $newReportsTo): bool
    {
        $cursor = $newReportsTo;
        for ($guard = 0; $cursor !== null && $guard < 50; $guard++) {
            if ($cursor === $editingId) {
                return true;
            }
            $cursor = OrgPosition::where('position_id', $cursor)->value('reports_to_position_id');
        }

        return false;
    }
}
