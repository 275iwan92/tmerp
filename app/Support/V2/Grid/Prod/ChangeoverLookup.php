<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineChangeover;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Setup and changeover table per machine: extra minutes and extra waste when the machine changes colour, ink type, substrate, size, plate or die.
// Rows here override the start values in config/ppic.php. Status Assumed is corrected by the learning engine inside its limits; Frozen stops it.
class ChangeoverLookup extends DbLookup
{
    public const STATUSES = ['ASSUMED' => 'Assumed', 'LEARNING' => 'Learning', 'CALIBRATED' => 'Calibrated'];

    public const KINDS = ['COLOR' => 'Colour change', 'INK_TYPE' => 'Ink type change', 'SUBSTRATE' => 'Substrate change', 'SIZE' => 'Size change', 'PLATE' => 'Plate change', 'DIE' => 'Die change'];

    public function slug(): string
    {
        return 'prod-changeover';
    }

    public function title(): string
    {
        return t('Setup and Changeover');
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
        return 'Extra minutes and extra waste sheets when a machine changes from one condition to another. Scheduling adds them to the base setup between two jobs. '
            .'Values start as Assumed; the learning engine adjusts them from real jobs (minimum samples, small steps) and logs every change. Tick Frozen to stop it.';
    }

    protected function query(): Builder
    {
        return ProdMachineChangeover::query()->orderBy('machine_id')->orderBy('change_kind');
    }

    public function columns(): array
    {
        return [
            ['key' => 'machine_id', 'label' => 'Machine', 'type' => 'select', 'required' => true, 'width' => '260px',
                'options' => ProdMachine::query()->where('activate', 1)->orderBy('machine_code')->get()->mapWithKeys(fn ($m) => [$m->machine_id => $m->machine_code.' · '.$m->machine_name])->all()],
            ['key' => 'change_kind', 'label' => 'Change', 'type' => 'select', 'options' => array_map('t', self::KINDS), 'required' => true, 'width' => '150px'],
            ['key' => 'from_value', 'label' => 'From', 'width' => '130px'],
            ['key' => 'to_value', 'label' => 'To', 'width' => '130px'],
            ['key' => 'extra_minutes', 'label' => 'Extra Minutes', 'type' => 'number', 'decimals' => 0, 'required' => true, 'width' => '110px'],
            ['key' => 'extra_waste_qty', 'label' => 'Extra Waste (sheets)', 'type' => 'number', 'decimals' => 0, 'width' => '130px'],
            ['key' => 'value_status', 'label' => 'Value Status', 'type' => 'select', 'options' => array_map('t', self::STATUSES), 'width' => '120px'],
            ['key' => 'sample_count', 'label' => 'Samples', 'type' => 'number', 'decimals' => 0, 'readonly' => true, 'width' => '80px'],
            ['key' => 'frozen', 'label' => 'Frozen', 'type' => 'check', 'width' => '70px'],
            ['key' => 'note', 'label' => 'Note', 'width' => '200px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (($in['value_status'] ?? '') === '') {
            $in['value_status'] = 'ASSUMED';
        }
        if (($in['extra_waste_qty'] ?? '') === '') {
            $in['extra_waste_qty'] = 0;
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['change_kind' => [Rule::in(array_keys(self::KINDS))], 'value_status' => [Rule::in(array_keys(self::STATUSES))],
            'extra_minutes' => ['integer', 'min:0', 'max:65535'], 'extra_waste_qty' => ['integer', 'min:0']];
    }
}
