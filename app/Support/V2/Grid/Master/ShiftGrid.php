<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Shift: semua shift dengan departemen dan jumlah pegawai aktif yang memakainya sebagai shift bawaan; Open dan + New Record membuka form v2 md-shift-form.
 * Sumber hulu  : tm2mst.emp_shift + org_department; jumlah pegawai dari tm2mst.emp_employee.default_shift_id (query terpisah).
 * Input        : Pencarian, filter Department.
 * Output       : Baris grid; tombol aktif / nonaktif membalik emp_shift.activate.
 * Dipakai oleh : Menu MD_SHIFT (v2:md-shift), layar md-shift-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::master.shift sudah dipindah ke tmerp_unused (aturan D.25). Kolom Active employees saat ini 0 untuk semua shift karena default_shift_id kosong di data pegawai.
 */
class ShiftGrid extends MasterDataGrid
{
    private array $employees = [];

    public function slug(): string
    {
        return 'md-shift';
    }

    public function title(): string
    {
        return 'Master Shift';
    }

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'md-shift-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'md-shift-form').'?new=1';
    }

    protected function models(): array
    {
        return [EmpShift::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Search shift name, department...';
    }

    protected function query(): Builder
    {
        return EmpShift::query()->with('department')->orderBy('shift_name');
    }

    protected function prefetch(Collection $items): void
    {
        $this->employees = EmpEmployee::query()->where('activate', true)->whereIn('default_shift_id', $items->modelKeys())
            ->groupBy('default_shift_id')->selectRaw('default_shift_id, COUNT(*) n')->pluck('n', 'default_shift_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Shift Name', 'type' => 'identity', 'avatar' => false],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'employees', 'label' => 'Active employees', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->shift_name, 'dept' => $m->department?->department_name ?? t('All departments'),
            'employees' => (int) ($this->employees[$m->getKey()] ?? 0)];
    }

    public function filters(): array
    {
        return ['dept' => 'Department'];
    }
}
