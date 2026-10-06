<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysDataOriginRule;
use App\Services\Data\DataOriginService;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

// Setup > Data Origin, tab Classification Rules (v2, pengganti tabel aturan di layar v1 pages::setup.data-origin).
// Mengubah aturan belum menandai data apa pun: penandaan baru terjadi lewat Preview + Apply di layar Data Origin.
class DataOriginRuleLookup extends DbLookup
{
    public function slug(): string
    {
        return 'data-origin-rules';
    }

    public function title(): string
    {
        return 'Data Origin Rules';
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
        return 'fa-solid fa-filter';
    }

    public function guide(): ?string
    {
        return 'Rules only move LIVE rows to DUMMY or LEGACY; child rows of a DUMMY row follow it. Empty database or table = all. '
            .'Pattern uses LIKE (% = anything). Saving a rule marks nothing yet: use Preview and Apply on the Data Origin screen.';
    }

    protected function query(): Builder
    {
        return SysDataOriginRule::query()->orderBy('priority')->orderBy('id_rule');
    }

    public function columns(): array
    {
        $dbs = array_combine(DataOriginService::DATABASES, DataOriginService::DATABASES);

        return [
            ['key' => 'priority', 'label' => 'Priority', 'type' => 'number', 'required' => true, 'width' => '80px'],
            ['key' => 'match_type', 'label' => 'Type', 'type' => 'select', 'options' => array_map('t', SysDataOriginRule::TYPES), 'required' => true, 'width' => '210px'],
            ['key' => 'db_name', 'label' => 'Database', 'type' => 'select', 'options' => $dbs, 'width' => '110px'],
            ['key' => 'table_name', 'label' => 'Table', 'width' => '160px'],
            ['key' => 'column_name', 'label' => 'Column', 'width' => '150px'],
            ['key' => 'match_value', 'label' => 'Pattern (LIKE)', 'width' => '150px'],
            ['key' => 'origin', 'label' => 'Mark as', 'type' => 'select', 'options' => ['DUMMY' => 'DUMMY', 'LEGACY' => 'LEGACY'], 'required' => true, 'width' => '100px'],
            ['key' => 'note', 'label' => 'Note', 'width' => '220px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return [
            'match_type' => ['in:'.implode(',', array_keys(SysDataOriginRule::TYPES))],
            'db_name' => ['in:'.implode(',', DataOriginService::DATABASES)],
            'table_name' => ['regex:/^\w+$/', 'max:64'],
            'column_name' => ['regex:/^\w+$/', 'max:64'],
            'match_value' => ['string', 'max:100'],
            'origin' => ['in:DUMMY,LEGACY'],
            'priority' => ['integer', 'min:0', 'max:9999'],
            'note' => ['string', 'max:255'],
        ];
    }

    // Kolom yang tidak dipakai jenis aturannya dikosongkan (sama dengan layar v1).
    protected function prepare(array $in): array
    {
        $in = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $in);
        if (($in['match_type'] ?? null) === 'CREATED_BY') {
            $in['column_name'] = '';
        }
        if (($in['match_type'] ?? null) === 'COLUMN_FILLED') {
            $in['match_value'] = '';
        }
        $in['activate'] ??= true;

        return $in;
    }

    protected function check(array $in, ?int $id): ?string
    {
        if (($in['match_type'] ?? '') !== 'CREATED_BY' && ($in['column_name'] ?? '') === '') {
            return t('Column is required for this rule type.');
        }
        if (($in['match_type'] ?? '') !== 'COLUMN_FILLED' && ($in['match_value'] ?? '') === '') {
            return t('Pattern is required for this rule type.');
        }

        return null;
    }

    public function save(array $changed): array
    {
        if (! auth()->user()?->is_admin) {
            throw ValidationException::withMessages(['row' => t('Only an administrator can change data origin settings.')]);
        }

        return parent::save($changed);
    }
}
