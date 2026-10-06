<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysDataRequirement;
use App\Services\Data\DataOriginService;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

// Setup > Data Origin, tab Legacy Completeness (v2, pengganti chip syarat wajib di layar v1 pages::setup.data-origin).
// Satu baris = satu kolom yang wajib terisi sebelum baris LEGACY boleh jadi LIVE; sesudah Save kelengkapan dihitung ulang.
class DataRequirementLookup extends DbLookup
{
    public function slug(): string
    {
        return 'data-requirements';
    }

    public function title(): string
    {
        return 'Required Fields of Legacy Data';
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
        return 'Settings';
    }

    public function icon(): string
    {
        return 'fa-solid fa-list-check';
    }

    public function guide(): ?string
    {
        return 'A LEGACY row may become real data (LIVE) only when every active required field of its table is filled. '
            .'Tables without a required field count as complete. Saving recounts the completeness.';
    }

    protected function query(): Builder
    {
        return SysDataRequirement::query()->orderBy('db_name')->orderBy('table_name')->orderBy('column_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'db_name', 'label' => 'Database', 'type' => 'select', 'options' => array_combine(DataOriginService::DATABASES, DataOriginService::DATABASES),
                'required' => true, 'lockSaved' => true, 'width' => '110px'],
            ['key' => 'table_name', 'label' => 'Table', 'required' => true, 'lockSaved' => true, 'width' => '180px'],
            ['key' => 'column_name', 'label' => 'Required column', 'required' => true, 'lockSaved' => true, 'width' => '180px'],
            ['key' => 'label', 'label' => 'Requirement name', 'required' => true, 'width' => '220px'],
            ['key' => 'note', 'label' => 'Note', 'width' => '220px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return [
            'db_name' => ['in:'.implode(',', DataOriginService::DATABASES)],
            'table_name' => ['regex:/^\w+$/', 'max:64'],
            'column_name' => ['regex:/^\w+$/', 'max:64'],
            'label' => ['string', 'max:100'],
            'note' => ['string', 'max:255'],
        ];
    }

    protected function prepare(array $in): array
    {
        $in = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $in);
        $in['activate'] ??= true;

        return $in;
    }

    protected function check(array $in, ?int $id): ?string
    {
        if ($id) {
            return null;
        }
        $dup = SysDataRequirement::where('db_name', $in['db_name'] ?? '')->where('table_name', $in['table_name'] ?? '')->where('column_name', $in['column_name'] ?? '')->exists();

        return $dup ? t('This required column is already listed.') : null;
    }

    public function save(array $changed): array
    {
        if (! auth()->user()?->is_admin) {
            throw ValidationException::withMessages(['row' => t('Only an administrator can change data origin settings.')]);
        }
        $rows = parent::save($changed);
        app(DataOriginService::class)->computeReady(false);

        return $rows;
    }
}
