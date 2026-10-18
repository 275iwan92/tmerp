<?php

/*
 * REMARK
 * Tujuan       : Hasil KPI bulanan per pegawai dan persentase kemampuan: simpan hasil (target, bobot, arah dibekukan saat entri) lalu hitung kemampuan per peran dan per mesin dari hasil itu. Persen tidak disimpan.
 * Sumber hulu  : tm2mst.org_position_kpi (definisi KPI jabatan), emp_kpi_result (hasil), emp_employee_position dan emp_employee.position_id (jabatan yang dipegang), emp_work_center (penanda mesin).
 * Input        : Pegawai, id jabatan, bulan (Y-m), work center opsional, nilai aktual per KPI, user HR Officer.
 * Output       : Baris emp_kpi_result; skor KPI = min(aktual / target, 100%) (makin besar baik) atau min(target / aktual, 100%) (makin kecil baik); kemampuan = rata-rata berbobot skor KPI dalam 3 bulan terakhir.
 * Dipakai oleh : routes/v2.php (hrm.employee.kpi-result), layar hrm-employee-form (tab KPI dan Skills), tes KpiResultTest.
 * Status       : Aktif. Hanya HR Officer yang memasukkan hasil (usul awal; atasan langsung menyusul). Tidak mengubah roster atau PPIC (tetap memakai sertifikasi ya / tidak).
 */

namespace App\Services\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpKpiResult;
use App\Models\Master\EmpWorkCenter;
use App\Models\Master\OrgPositionKpi;
use App\Models\Sys\SecUser;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class KpiResultService
{
    public const WINDOW_MONTHS = 3;

    // Skor satu KPI satu bulan, 0..1 (tidak pernah di atas 100%).
    public static function score(string $direction, float $target, float $actual): float
    {
        if ($direction === 'LOWER') {
            return $actual <= 0 ? 1.0 : min($target / $actual, 1.0);
        }

        return $target <= 0 ? 1.0 : min($actual / $target, 1.0);
    }

    public function canEnter(SecUser $user): bool
    {
        return $user->isHrOfficer();
    }

    /** @return list<string> bulan jendela dihitung, lama -> baru, format Y-m (bulan berjalan terakhir) */
    public static function window(?CarbonImmutable $now = null): array
    {
        $start = ($now ?? CarbonImmutable::now())->startOfMonth();

        return array_map(fn (int $i) => $start->subMonths(self::WINDOW_MONTHS - 1 - $i)->format('Y-m'), range(0, self::WINDOW_MONTHS - 1));
    }

    /**
     * Simpan hasil satu bulan untuk satu jabatan (dan mesin opsional). Mengisi ulang bulan dan mesin yang sama menggantikan nilai lama; snapshot pertama dipertahankan.
     *
     * @param  array<int, mixed>  $actuals  id_position_kpi => nilai
     */
    public function save(EmpEmployee $e, int $positionId, string $month, ?int $workCenterId, array $actuals, ?string $note, SecUser $user): int
    {
        abort_unless($this->canEnter($user), 403);
        if (! app(EmployeeRoleService::class)->held($e)->contains(fn ($h) => $h['position']->position_id === $positionId)) {
            throw ValidationException::withMessages(['position_id' => 'The employee does not hold this position.']);
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > now()->format('Y-m')) {
            throw ValidationException::withMessages(['period_month' => 'Choose a month that is not in the future.']);
        }
        if ($workCenterId && ! EmpWorkCenter::where('work_center_id', $workCenterId)->exists()) {
            throw ValidationException::withMessages(['work_center_id' => 'Unknown machine / work center.']);
        }

        $kpis = OrgPositionKpi::where('position_id', $positionId)->where('activate', true)->get()->keyBy('id_position_kpi');
        if ($kpis->isEmpty() || abs((float) $kpis->sum('weight_pct') - 100.0) > 0.01) {
            throw ValidationException::withMessages(['position_id' => 'The KPI weights of this position must add up to 100% before results can be entered.']);
        }

        $values = [];
        foreach ($actuals as $kpiId => $raw) {
            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }
            $v = Locale::input($raw) ?? (string) $raw;
            if (! $kpis->has((int) $kpiId) || ! is_numeric($v) || (float) $v < 0) {
                throw ValidationException::withMessages(['actual' => 'Enter a number of zero or more for each KPI you fill in.']);
            }
            $values[(int) $kpiId] = (float) $v;
        }
        if (! $values) {
            throw ValidationException::withMessages(['actual' => 'Enter the result of at least one KPI.']);
        }

        $period = $month.'-01';

        return DB::connection('tm2mst')->transaction(function () use ($e, $kpis, $values, $period, $workCenterId, $note, $user) {
            foreach ($values as $kpiId => $actual) {
                $k = $kpis[$kpiId];
                $row = EmpKpiResult::where('employee_id', $e->employee_id)->where('position_kpi_id', $kpiId)->where('period_month', $period)
                    ->where('activate', true)->when($workCenterId, fn ($q) => $q->where('work_center_id', $workCenterId), fn ($q) => $q->whereNull('work_center_id'))->first();
                if ($row) {
                    $row->update(['actual_value' => $actual, 'note' => $note ?: $row->note, 'updated_by' => $user->login]);

                    continue;
                }
                EmpKpiResult::create(['employee_id' => $e->employee_id, 'position_kpi_id' => $kpiId, 'work_center_id' => $workCenterId ?: null, 'period_month' => $period, 'actual_value' => $actual,
                    'target_snapshot' => $k->target_value, 'weight_snapshot' => $k->weight_pct, 'direction_snapshot' => $k->direction, 'note' => $note ?: null, 'activate' => true,
                    'created_by' => $user->login, 'updated_by' => $user->login]);
            }

            return count($values);
        });
    }

    /** @return Collection<int, EmpKpiResult> hasil aktif pegawai dalam jendela, untuk KPI aktif dari jabatan terpilih (null = semua jabatan) */
    private function rows(EmpEmployee $e, ?int $positionId, ?int $workCenterId): Collection
    {
        $first = CarbonImmutable::createFromFormat('Y-m-d', self::window()[0].'-01')->startOfDay();
        $kpiIds = OrgPositionKpi::where('activate', true)->when($positionId, fn ($q) => $q->where('position_id', $positionId))->pluck('id_position_kpi');

        return EmpKpiResult::where('employee_id', $e->employee_id)->where('activate', true)->where('period_month', '>=', $first->toDateString())
            ->whereIn('position_kpi_id', $kpiIds)->when($workCenterId, fn ($q) => $q->where('work_center_id', $workCenterId))->orderBy('period_month')->get();
    }

    /**
     * Skor per KPI = rata-rata skor bulanan; kemampuan = rata-rata berbobot (bobot beku dari hasil terbaru) atas KPI yang punya hasil.
     *
     * @return array{pct: ?float, scores: array<int, float>}
     */
    public function aggregate(Collection $rows): array
    {
        $scores = [];
        $weights = [];
        foreach ($rows->groupBy('position_kpi_id') as $kpiId => $byKpi) {
            $monthly = $byKpi->groupBy(fn ($r) => $r->period_month->format('Y-m'))
                ->map(fn ($m) => $m->avg(fn ($r) => self::score($r->direction_snapshot, $r->target_snapshot, $r->actual_value)));
            $scores[$kpiId] = (float) $monthly->avg();
            $weights[$kpiId] = (float) $byKpi->sortByDesc('period_month')->first()->weight_snapshot;
        }
        $w = array_sum($weights);
        $pct = $w > 0 ? array_sum(array_map(fn ($id) => $weights[$id] * $scores[$id], array_keys($scores))) / $w : null;

        return ['pct' => $pct, 'scores' => $scores];
    }

    /** @return array{pct: ?float, scores: array<int, float>} kemampuan di satu jabatan (semua hasilnya) */
    public function forPosition(EmpEmployee $e, int $positionId): array
    {
        return $this->aggregate($this->rows($e, $positionId, null));
    }

    // Kemampuan di satu mesin / work center: hanya hasil yang ditandai mesin itu (dari jabatan mana pun); bobot dihitung ulang atas KPI yang punya hasil.
    public function forWorkCenter(EmpEmployee $e, int $workCenterId): ?float
    {
        return $this->aggregate($this->rows($e, null, $workCenterId))['pct'];
    }

    /** @return array<int, array<string, float>> id KPI => [Y-m => rata-rata aktual] untuk matriks tampilan */
    public function matrix(EmpEmployee $e, int $positionId): array
    {
        $out = [];
        foreach ($this->rows($e, $positionId, null)->groupBy('position_kpi_id') as $kpiId => $byKpi) {
            $out[$kpiId] = $byKpi->groupBy(fn ($r) => $r->period_month->format('Y-m'))->map(fn ($m) => (float) $m->avg('actual_value'))->all();
        }

        return $out;
    }
}
