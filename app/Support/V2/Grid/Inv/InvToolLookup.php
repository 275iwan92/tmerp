<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvTool;
use App\Models\Master\WhLoc;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Inventory > Tools > Tool Register (tm2inv.inv_tool), v2.01: daftar die, plat, dan klise dengan status dan lokasi (bin). Master sederhana diisi
// langsung di sel. Pemakaian per pekerjaan (impresi, umur) menyusul; kolom Impressions diisi petugas.
class InvToolLookup extends DbLookup
{
    public function slug(): string
    {
        return 'inv-tools';
    }

    public function title(): string
    {
        return 'Tool Register';
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
        return 'Tools';
    }

    public function icon(): string
    {
        return 'fa-solid fa-ruler-combined';
    }

    public function guide(): ?string
    {
        return 'Dies, plates and blocks the factory owns. Set the status (available, in use, at a vendor, in repair, retired) and the bin where the tool is kept. Impressions is the number of impressions made so far.';
    }

    protected function models(): array
    {
        return [InvTool::class, WhLoc::class];
    }

    protected function query(): Builder
    {
        return InvTool::query()->orderBy('tool_no');
    }

    public function columns(): array
    {
        return [
            ['key' => 'tool_no', 'label' => 'Tool No.', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '140px'],
            ['key' => 'tool_type', 'label' => 'Type', 'type' => 'select', 'options' => array_map('t', InvTool::TYPES), 'required' => true, 'width' => '110px'],
            ['key' => 'description', 'label' => 'Description', 'width' => '300px'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_map('t', InvTool::STATUSES), 'required' => true, 'width' => '130px'],
            ['key' => 'location_id', 'label' => 'Location (Bin)', 'type' => 'select',
                'options' => WhLoc::query()->where('activate', true)->orderBy('location_code')->pluck('location_code', 'location_id')->all(), 'width' => '170px'],
            ['key' => 'impressions', 'label' => 'Impressions', 'type' => 'number', 'decimals' => 0, 'width' => '110px'],
            ['key' => 'notes', 'label' => 'Notes', 'width' => '240px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function prepare(array $in): array
    {
        $in['tool_no'] = strtoupper(trim((string) ($in['tool_no'] ?? '')));
        if (($in['location_id'] ?? '') === '') {
            $in['location_id'] = null;
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['tool_no' => ['max:30'], 'tool_type' => [Rule::in(array_keys(InvTool::TYPES))], 'status' => [Rule::in(array_keys(InvTool::STATUSES))],
            'description' => ['nullable', 'max:255'], 'notes' => ['nullable', 'max:255'], 'impressions' => ['nullable', 'integer', 'min:0']];
    }
}
