<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysLanguage;
use App\Support\Localization\Dictionary;
use App\Support\V2\AppMenu;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Setup > Languages (user 2026-10-03): user biasa hanya menyalakan / mematikan bahasa (saklar Active) -- bahasa aktif muncul
// sebagai bendera di layar dan sebagai kolom di Translation Dictionary. Developer (Administrator / grup DEVELOPER) boleh
// memeriksa dan mengubah kode, nama, locale Intl dan pemisah angka. English = bahasa dasar database, tidak bisa dimatikan.
class LanguageLookup extends DbLookup
{
    public function slug(): string
    {
        return 'languages';
    }

    public function title(): string
    {
        return 'Languages';
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
        return 'fa-solid fa-globe';
    }

    public function guide(): ?string
    {
        return $this->developer()
            ? 'Developer: all columns can be edited. Active = shown as a flag and as a column in the Translation Dictionary. English is the base language of the database and stays active.'
            : 'Switch a language on with Active: it appears as a flag on every screen and as a column in the Translation Dictionary. Texts without a translation are shown in English.';
    }

    private function developer(): bool
    {
        $u = auth()->user();

        return $u ? AppMenu::user($u)['developer'] : false;
    }

    protected function query(): Builder
    {
        return SysLanguage::query()->orderBy('sort_order')->orderBy('code');
    }

    public function columns(): array
    {
        $ro = ! $this->developer();

        return [
            ['key' => 'code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'readonly' => $ro, 'width' => '80px', 'case' => 'lowercase'],
            ['key' => 'name', 'label' => 'Language', 'required' => true, 'readonly' => $ro, 'width' => '180px'],
            ['key' => 'native_name', 'label' => 'Native name', 'readonly' => $ro, 'width' => '180px'],
            ['key' => 'intl_locale', 'label' => 'Intl locale', 'readonly' => $ro, 'width' => '110px'],
            ['key' => 'decimal_separator', 'label' => 'Decimal', 'readonly' => $ro, 'width' => '80px'],
            ['key' => 'thousand_separator', 'label' => 'Thousands', 'readonly' => $ro, 'width' => '90px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => '70px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['code' => ['regex:/^[a-z]{2,5}$/'], 'decimal_separator' => ['nullable', 'size:1'], 'thousand_separator' => ['nullable', 'size:1']];
    }

    protected function prepare(array $in): array
    {
        $in['code'] = strtolower(trim((string) ($in['code'] ?? '')));
        $in['decimal_separator'] = ($in['decimal_separator'] ?? '') === '' ? '.' : $in['decimal_separator'];
        $in['thousand_separator'] = ($in['thousand_separator'] ?? '') === '' ? ',' : $in['thousand_separator'];

        return $in;
    }

    protected function check(array $in, ?int $id): ?string
    {
        $code = $id ? SysLanguage::query()->whereKey($id)->value('code') : $in['code'];
        if ($code === 'en' && ! filter_var($in['activate'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return t('English is the base language of the database and cannot be switched off.');
        }
        if (($in['decimal_separator'] ?? '.') === ($in['thousand_separator'] ?? ',')) {
            return t('Decimal and thousands separators must differ.');
        }

        return null;
    }

    protected function saved(Model $row): void
    {
        Dictionary::flush();
    }
}
