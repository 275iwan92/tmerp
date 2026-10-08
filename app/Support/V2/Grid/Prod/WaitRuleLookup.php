<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdWaitRule;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Wait rules: minutes the next process must wait after a process (ink drying, coating curing). PlanLoader matches the process name by
// its first letters; a rule here wins over the start values in config/ppic.php.
class WaitRuleLookup extends DbLookup
{
    public function slug(): string
    {
        return 'prod-wait-rule';
    }

    public function title(): string
    {
        return t('Wait Rules');
    }

    public function module(): string
    {
        return 'PROD';
    }

    public function moduleLabel(): string
    {
        return 'Production';
    }

    public function guide(): ?string
    {
        return 'After this process the next one waits at least the minutes given (drying, curing). Type the start of the process name, for example CETAK or VARNISH. '
            .'Scheduling adds the wait between the two steps of a Work Order; the machine is free during the wait.';
    }

    protected function query(): Builder
    {
        return ProdWaitRule::query()->orderBy('after_process_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'after_process_name', 'label' => 'After Process (name starts with)', 'required' => true, 'case' => 'uppercase', 'width' => '240px'],
            ['key' => 'kind', 'label' => 'Reason', 'width' => '220px'],
            ['key' => 'wait_minutes', 'label' => 'Wait (min)', 'type' => 'number', 'decimals' => 0, 'required' => true, 'width' => '110px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (isset($in['after_process_name']) && is_string($in['after_process_name'])) {
            $in['after_process_name'] = strtoupper(trim($in['after_process_name']));
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['after_process_name' => ['string', 'max:60'], 'kind' => ['string', 'max:40'], 'wait_minutes' => ['integer', 'min:0', 'max:100000']];
    }
}
