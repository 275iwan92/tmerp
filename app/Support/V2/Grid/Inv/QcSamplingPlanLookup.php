<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvQcSamplingPlan;
use App\Services\Inventory\QcSamplingService;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Sampling Plan (Q-01): ukuran sampel menurut intensitas dan jumlah lot. Satu baris = "mulai dari jumlah lot ini": sampel baris dengan batas bawah
// terbesar yang masih <= jumlah lot dipakai (batas atas hanya keterangan). Nilai awal ANSI/ASQ Z1.4 level II yang disederhanakan; kepala QC mengubahnya di sini.
class QcSamplingPlanLookup extends DbLookup
{
    public function slug(): string
    {
        return 'qc-sampling-plan';
    }

    public function title(): string
    {
        return 'Sampling Plan';
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
        return 'fa-solid fa-vial';
    }

    public function guide(): ?string
    {
        return 'How many pieces are sampled from a lot. Pick the intensity, the lot quantity the row starts at, and the sample size. The row with the highest start that is not above the lot quantity applies. Skip needs no row: the supplier certificate is enough.';
    }

    protected function query(): Builder
    {
        return InvQcSamplingPlan::query()->orderByRaw("FIELD(intensity, 'TIGHTENED', 'NORMAL', 'REDUCED')")->orderBy('lot_from');
    }

    public function columns(): array
    {
        return [
            ['key' => 'intensity', 'label' => 'Intensity', 'type' => 'select', 'options' => array_map('t', array_diff_key(QcSamplingService::INTENSITIES, ['SKIP' => 1])), 'required' => true, 'width' => '140px'],
            ['key' => 'lot_from', 'label' => 'Lot Quantity From', 'type' => 'number', 'decimals' => 0, 'required' => true, 'width' => '150px'],
            ['key' => 'lot_to', 'label' => 'To (note)', 'type' => 'number', 'decimals' => 0, 'width' => '120px'],
            ['key' => 'sample_size', 'label' => 'Sample Size', 'type' => 'number', 'decimals' => 0, 'required' => true, 'width' => '120px'],
            ['key' => 'note', 'label' => 'Note', 'width' => '260px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['intensity' => [Rule::in(['TIGHTENED', 'NORMAL', 'REDUCED'])], 'lot_from' => ['numeric', 'min:0'], 'sample_size' => ['integer', 'min:1'], 'note' => ['max:255']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $dup = InvQcSamplingPlan::query()->where('intensity', $in['intensity'] ?? '')->where('lot_from', (float) ($in['lot_from'] ?? 0))->when($id, fn ($q) => $q->whereKeyNot($id))->exists();

        return $dup ? t('This intensity already has a row that starts at this lot quantity.') : null;
    }
}
