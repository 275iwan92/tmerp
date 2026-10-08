<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvQcIntensityRule;
use App\Services\Inventory\QcSamplingService;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Sampling Rules (Q-01, Q-10): per kelas risiko A / B / C, intensitas awal, AQL, dan kapan intensitas berpindah menurut riwayat supplier
// (lulus N lot berturut-turut = Reduced; satu reject = Tightened sampai lulus beberapa lot). Tiga baris tetap: tidak ditambah / dihapus.
class QcIntensityRuleLookup extends DbLookup
{
    public function slug(): string
    {
        return 'qc-sampling-rules';
    }

    public function title(): string
    {
        return 'Sampling Rules';
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return 'Batch & Quality › Setup';
    }

    public function icon(): string
    {
        return 'fa-solid fa-scale-unbalanced-flip';
    }

    public function guide(): ?string
    {
        return 'One row per risk class. Start = the intensity for a supplier that is known and clean. A supplier with fewer lots than the setting INV_QC_NEW_SUPPLIER_LOTS is always sampled Tightened. After a reject the supplier stays Tightened until it passes Recover lots in a row; after Reduced-after passed lots in a row the sampling becomes Reduced.';
    }

    protected function query(): Builder
    {
        return InvQcIntensityRule::query()->orderBy('risk_class');
    }

    public function columns(): array
    {
        return [
            ['key' => 'risk_class', 'label' => 'Risk Class', 'type' => 'select', 'options' => array_map('t', QcSamplingService::CLASSES), 'required' => true, 'unique' => true, 'lockSaved' => true, 'width' => '160px'],
            ['key' => 'start_intensity', 'label' => 'Start Intensity', 'type' => 'select', 'options' => array_map('t', QcSamplingService::INTENSITIES), 'required' => true, 'width' => '160px'],
            ['key' => 'aql_pct', 'label' => 'AQL %', 'type' => 'number', 'decimals' => 2, 'width' => '90px'],
            ['key' => 'pass_to_reduced', 'label' => 'Reduced After (lots passed)', 'type' => 'number', 'decimals' => 0, 'required' => true, 'width' => '190px'],
            ['key' => 'recover_pass', 'label' => 'Recover After Reject (lots passed)', 'type' => 'number', 'decimals' => 0, 'required' => true, 'width' => '230px'],
            ['key' => 'note', 'label' => 'Note', 'width' => '260px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['risk_class' => [Rule::in(array_keys(QcSamplingService::CLASSES))], 'start_intensity' => [Rule::in(array_keys(QcSamplingService::INTENSITIES))],
            'aql_pct' => ['numeric', 'min:0', 'max:100'], 'pass_to_reduced' => ['integer', 'min:1'], 'recover_pass' => ['integer', 'min:1'], 'note' => ['max:255']];
    }
}
