<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysDictionary;
use App\Models\Sys\SysDictionaryGroup;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Setup > Message Groups: kelompok pesan = pohon kiri Translation Dictionary (ala ScriptCase, user 2026-10-03).
// Kode dikunci setelah dipakai baris kamus; kelompok yang tidak dipakai lagi cukup dinonaktifkan.
class DictionaryGroupLookup extends DbLookup
{
    public function slug(): string
    {
        return 'message-groups';
    }

    public function title(): string
    {
        return 'Message Groups';
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
        return 'Language and Localization';
    }

    public function icon(): string
    {
        return 'fa-solid fa-folder-tree';
    }

    public function guide(): ?string
    {
        return 'Groups of the Translation Dictionary tree. New texts are placed by the object that shows them (button, grid, form, menu, '
            .'message); move a text to another group with the Group column of the dictionary.';
    }

    protected function query(): Builder
    {
        return SysDictionaryGroup::query()->orderBy('sort_order')->orderBy('code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'width' => '140px', 'case' => 'lowercase'],
            ['key' => 'label', 'label' => 'Group', 'required' => true, 'width' => '260px'],
            ['key' => 'icon', 'label' => 'Icon (Font Awesome)', 'width' => '180px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function prepare(array $in): array
    {
        $in['code'] = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim((string) ($in['code'] ?? ''))));

        return $in;
    }

    protected function check(array $in, ?int $id): ?string
    {
        if ($id && ! filter_var($in['activate'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $code = SysDictionaryGroup::query()->whereKey($id)->value('code');
            if ($code === 'general' || SysDictionary::query()->where('group_code', $code)->exists()) {
                return t('A group that still holds texts (or General) cannot be deactivated. Move its texts first.');
            }
        }

        return null;
    }
}
