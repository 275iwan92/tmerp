<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinAccount;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master of Accounts (COA): daftar akun GL berhierarki; Open dan + New Record membuka form v2 acc-coa-form.
 * Sumber hulu  : tm2acc.fin_account (+ view pohon untuk level dan kode cost center).
 * Input        : Pencarian dan filter Type / Level / Postable.
 * Output       : Baris grid; tombol aktif / nonaktif membalik fin_account.activate.
 * Dipakai oleh : Menu ACC_COA (v2:acc-coa), layar acc-coa-form (kembali ke daftar).
 * Status       : Aktif. Form v1 pages::finance.coa sudah dipindah ke tmerp_unused (aturan D.25).
 */
class CoaGrid extends DbGrid
{
    public function slug(): string
    {
        return 'acc-coa';
    }

    public function title(): string
    {
        return 'Master of Accounts';
    }

    public function module(): string
    {
        return 'FINACC';
    }

    public function moduleLabel(): string
    {
        return 'Finance & Accounting';
    }

    public function family(): string
    {
        return self::MASTER;
    }

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'acc-coa-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'acc-coa-form').'?new=1';
    }

    public function search(): string
    {
        return 'Search code, account name...';
    }

    protected function query(): Builder
    {
        return FinAccount::query()->with('parent')->orderBy('account_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Account Name', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'normal', 'label' => 'Normal Balance'],
            ['key' => 'level', 'label' => 'Level', 'type' => 'number'],
            ['key' => 'parent', 'label' => 'Parent'],
            ['key' => 'postable', 'label' => 'Postable', 'type' => 'badge', 'tones' => [t('Yes') => 'success', t('Header') => 'neutral']],
            ['key' => 'cc', 'label' => 'Default Cost Center'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->account_name, 'code' => $m->account_code, 'type' => $m->account_type,
            'normal' => $m->normal_balance, 'level' => (int) $m->tree_level, 'parent' => $m->parent?->account_name,
            'postable' => $m->is_postable ? t('Yes') : t('Header'), 'cc' => $m->default_cc_code];
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'level' => 'Level', 'postable' => 'Postable'];
    }
}
