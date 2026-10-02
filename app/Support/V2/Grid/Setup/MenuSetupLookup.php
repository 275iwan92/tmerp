<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysAppMenu;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Setup > Menu Setup (backlog UI-21, aturan "semua data wajib punya inputan"): label, label pendek, ikon, urutan, dan aktif /
// nonaktif setiap baris sys_app_menu diubah langsung di sel -- dulu hanya bisa lewat migration. Baris baru, pindah induk, alamat
// layar, hak akses, dan status A / R TIDAK diubah di sini (struktur menu tetap lewat migration; LOCKED hanya oleh user).
class MenuSetupLookup extends DbLookup
{
    // id => ['path' => 'Sales › Sales Order', 'order' => kunci urut pohon]
    private array $tree = [];

    public function slug(): string
    {
        return 'menu-setup';
    }

    public function title(): string
    {
        return 'Menu Setup';
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
        return 'fa-solid fa-bars-staggered';
    }

    public function guide(): ?string
    {
        return 'Change the label, icon, order and active flag of every menu row here. Icon = Font Awesome class (fa-solid fa-truck); '
            .'each item in one list needs its own icon. Switching a row off hides it and closes its screen. Reload the page to see the menu change.';
    }

    protected function query(): Builder
    {
        return SysAppMenu::query()->orderBy('sort_order')->orderBy('id');
    }

    protected function prefetch(Collection $items): void
    {
        $by = $items->keyBy('id');
        foreach ($items as $n) {
            $path = [];
            $order = [];
            // Batas 10 tingkat: pengaman bila ada induk yang saling menunjuk.
            for ($p = $n, $i = 0; $p && $i < 10; $p = $by->get($p->parent_id), $i++) {
                array_unshift($order, str_pad((string) $p->sort_order, 6, '0', STR_PAD_LEFT).'-'.str_pad((string) $p->id, 8, '0', STR_PAD_LEFT));
                if ($p !== $n) {
                    array_unshift($path, $p->label);
                }
            }
            $this->tree[$n->id] = ['path' => implode(' › ', $path), 'order' => implode('/', $order)];
        }
    }

    public function rows(): array
    {
        $rows = parent::rows();
        usort($rows, fn ($a, $b) => strcmp($this->tree[$a['id']]['order'], $this->tree[$b['id']]['order']));

        return $rows;
    }

    public function columns(): array
    {
        return [
            ['key' => 'path', 'label' => 'Under', 'readonly' => true, 'width' => '260px'],
            ['key' => 'menu_type', 'label' => 'Type', 'readonly' => true, 'width' => '80px'],
            ['key' => 'label', 'label' => 'Label', 'required' => true, 'width' => '260px'],
            ['key' => 'short_label', 'label' => 'Short Label', 'width' => '110px'],
            ['key' => 'icon', 'label' => 'Icon', 'width' => '220px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'decimals' => 0, 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
            ['key' => 'code', 'label' => 'Code', 'readonly' => true, 'width' => '170px'],
            ['key' => 'target', 'label' => 'Screen', 'readonly' => true, 'width' => '240px'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['path' => $this->tree[$m->id]['path'] ?? ''] + parent::row($m);
    }

    // Baris yang tidak boleh dimatikan dari sini: layar ini sendiri beserta induknya (kalau mati, layar ini ikut tertutup).
    private function ownChain(): array
    {
        $by = SysAppMenu::query()->get(['id', 'parent_id', 'target'])->keyBy('id');
        $ids = [];
        foreach ($by->where('target', 'v2:'.$this->slug()) as $n) {
            for ($p = $n, $i = 0; $p && $i < 10; $p = $by->get($p->parent_id), $i++) {
                $ids[$p->id] = true;
            }
        }

        return $ids;
    }

    public function save(array $changed): array
    {
        $own = $this->ownChain();
        SysAppMenu::query()->getConnection()->transaction(function () use ($changed, $own) {
            foreach ($changed as $i => $in) {
                $row = is_numeric($in['id'] ?? null) ? SysAppMenu::query()->find((int) $in['id']) : null;
                if (! $row) {
                    throw ValidationException::withMessages(['row' => str_replace(':n', (string) ($i + 1), t('Row :n:')).' '.t('New menu rows are added by migration, not here. Use Undo to remove the row.')]);
                }
                $v = Validator::make($in, [
                    'label' => ['required', 'string', 'max:100'], 'short_label' => ['nullable', 'string', 'max:20'],
                    'icon' => ['nullable', 'string', 'max:50', 'regex:/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/'],
                    'sort_order' => ['nullable', 'integer', 'between:0,99999'], 'activate' => ['boolean'],
                ], ['icon.regex' => 'Icon must be a Font Awesome class, for example: fa-solid fa-truck.'], ['label' => 'Label', 'short_label' => 'Short Label', 'icon' => 'Icon', 'sort_order' => 'Order']);
                if ($v->fails()) {
                    throw ValidationException::withMessages(['row' => str_replace(':n', (string) ($i + 1), t('Row :n:')).' '.$v->errors()->first()]);
                }
                if (empty($in['activate']) && isset($own[$row->id])) {
                    throw ValidationException::withMessages(['row' => str_replace(':n', (string) ($i + 1), t('Row :n:')).' '.str_replace(':label', (string) $row->label, t('":label" holds this Menu Setup screen and cannot be switched off here.'))]);
                }
                $row->forceFill([
                    'label' => trim((string) $in['label']), 'short_label' => ($in['short_label'] ?? '') === '' ? null : $in['short_label'],
                    'icon' => ($in['icon'] ?? '') === '' ? null : $in['icon'], 'sort_order' => (int) ($in['sort_order'] ?? 0), 'activate' => ! empty($in['activate']),
                ])->save();
            }
        });
        $this->tree = [];

        return $this->rows();
    }
}
