<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Master\SalesPerson;
use App\Models\Sys\SysKpiTarget;
use App\Support\V2\Grid\DbLookup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

// Setup > KPI Targets: target per bulan yang dibandingkan widget opening screen. Sales person kosong = target perusahaan.
// Tanggal apa pun di bulan itu disimpan sebagai tanggal 1 (satu target per bulan).
class KpiTargetLookup extends DbLookup
{
    private const COMPANY = 0;

    public function slug(): string
    {
        return 'setup-kpi-targets';
    }

    public function title(): string
    {
        return 'KPI Targets';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function group(): string
    {
        return 'Dashboard and Widgets';
    }

    public function icon(): string
    {
        return 'fa-solid fa-bullseye';
    }

    public function guide(): ?string
    {
        return 'Monthly targets shown on the opening board. Leave Sales Person at Company for a company target; choose a person for a personal order-value target. '
            .'Any date of the month is stored as the first day of that month.';
    }

    protected function query(): Builder
    {
        return SysKpiTarget::query()->orderByDesc('period_month')->orderBy('target_key')->orderBy('sales_person_id');
    }

    public function columns(): array
    {
        $people = [self::COMPANY => t('Company')] + SalesPerson::query()->orderBy('sales_name')->pluck('sales_name', 'id_sales_person')->all();

        return [
            ['key' => 'target_key', 'label' => 'Target', 'type' => 'select', 'options' => array_map('t', SysKpiTarget::KEYS), 'required' => true, 'width' => '330px'],
            ['key' => 'period_month', 'label' => 'Month', 'type' => 'date', 'required' => true, 'width' => '140px'],
            ['key' => 'sales_person_id', 'label' => 'Sales Person', 'type' => 'select', 'options' => $people, 'required' => true, 'width' => '240px'],
            ['key' => 'target_value', 'label' => 'Target Value', 'type' => 'number', 'decimals' => 2, 'required' => true, 'width' => '170px'],
            ['key' => 'notes', 'label' => 'Notes', 'width' => '300px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['period_month' => $m->period_month?->toDateString(), 'sales_person_id' => (int) $m->sales_person_id] + parent::row($m);
    }

    protected function prepare(array $in): array
    {
        if (! empty($in['period_month'])) {
            try {
                $in['period_month'] = CarbonImmutable::parse((string) $in['period_month'])->startOfMonth()->toDateString();
            } catch (\Throwable) {
                // dibiarkan: aturan 'date' di rules() yang menolaknya
            }
        }
        $in['sales_person_id'] = ($in['sales_person_id'] ?? '') === '' || $in['sales_person_id'] === null ? self::COMPANY : $in['sales_person_id'];

        return $in;
    }

    protected function rules(): array
    {
        return ['target_key' => [Rule::in(array_keys(SysKpiTarget::KEYS))], 'period_month' => ['date'], 'target_value' => ['numeric', 'min:0'],
            'sales_person_id' => ['integer', 'min:0'], 'notes' => ['nullable', 'string', 'max:255']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $person = (int) ($in['sales_person_id'] ?? self::COMPANY);
        if ($person !== self::COMPANY && ($in['target_key'] ?? '') !== 'REVENUE') {
            return t('A personal target is only used for Order value. Choose Company for the other targets.');
        }
        if ($person !== self::COMPANY && ! SalesPerson::query()->whereKey($person)->exists()) {
            return t('This sales person is not in Master Sales Person.');
        }
        $twice = SysKpiTarget::query()->where('target_key', $in['target_key'] ?? null)->whereDate('period_month', $in['period_month'] ?? null)
            ->where('sales_person_id', $person)->when($id, fn (Builder $q) => $q->whereKeyNot($id))->exists();

        return $twice ? t('This target already exists for that month.') : null;
    }
}
