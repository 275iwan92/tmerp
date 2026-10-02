<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgPosition;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Struktur Organisasi -- org chart interaktif, instruksi user 2026-08-29
// (referensi screenshot produk org-chart SaaS). Scope v1 disepakati: chart
// pohon rapi + zoom in/out + search/filter + cetak, TANPA minimap/pan-kanvas-
// bebas/version-history/edit-di-kanvas (itu jauh lebih besar, ditunda).
// Hierarki 100% dari org_position.reports_to_position_id (diisi manual lewat
// Master Posisi, position.blade.php) -- layar ini MURNI visualisasi read-only.
//
// Seluruh pohon di-load 1x jadi struktur bersarang di PHP (bukan query per
// node) supaya komponen rekursif <x-org-chart.node> tinggal render, tanpa N+1.
new #[Title('Struktur Organisasi — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    public ?int $filterDepartmentId = null;

    public float $zoom = 1.0;

    private const BRANCH_COLORS = ['#f59e0b', '#7c3aed', '#db2777', '#2563eb', '#16a34a', '#0891b2', '#dc2626', '#65a30d'];

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id');
    }

    // Bangun seluruh pohon dari org_position + occupant aktif (emp_employee),
    // grouped manual (bukan Collection::groupBy -- null key jadi '' bukan
    // null, gotcha nyata kalau dipakai apa adanya utk root).
    #[Computed]
    public function roots(): array
    {
        $positions = OrgPosition::with(['department'])->where('activate', true)->orderBy('position_name')->get();

        // Grup PENUH (bukan ->first()) -- satu jabatan bisa ditempati beberapa
        // orang sekaligus (mis. mockup_org-chart menulis 1 kotak "Tia/Suro/
        // Fardan" utk 1 posisi PPIC Planner). Ditemukan 2026-09-13 saat
        // occupant simulasi dari screenshot struktur nyata diisi -- versi
        // lama cuma menampilkan orang pertama, menyembunyikan sisanya.
        $occupants = EmpEmployee::whereIn('position_id', $positions->pluck('position_id'))
            ->where('activate', true)->where('work_status', 'WORKING')
            ->orderBy('full_name')
            ->get()->groupBy('position_id');

        $childrenByParent = [];
        foreach ($positions as $p) {
            $key = $p->reports_to_position_id ?? 'ROOT';
            $childrenByParent[$key][] = $p;
        }

        $build = function ($parentKey, int $depth) use (&$build, $childrenByParent, $occupants) {
            if ($depth > 12 || empty($childrenByParent[$parentKey])) {
                return collect();
            }

            return collect($childrenByParent[$parentKey])->map(fn ($pos) => (object) [
                'position' => $pos,
                'occupants' => $occupants->get($pos->position_id, collect()),
                'children' => $build($pos->position_id, $depth + 1),
            ])->values();
        };

        return $build('ROOT', 0)->all();
    }

    // Position_id yang cocok search (nama kandidat/posisi) atau filter dept
    // -- dipakai node.blade.php utk highlight, BUKAN utk hide/hapus dari
    // pohon (supaya struktur garis tetap utuh).
    #[Computed]
    public function highlightIds(): array
    {
        if (trim($this->search) === '' && ! $this->filterDepartmentId) {
            return [];
        }

        $q = OrgPosition::query()->where('activate', true);

        if ($this->filterDepartmentId) {
            $q->where('department_id', $this->filterDepartmentId);
        }

        if (trim($this->search) !== '') {
            $needle = $this->search;
            $q->where(function ($q2) use ($needle) {
                $q2->where('position_name', 'like', "%{$needle}%")
                    ->orWhereIn('position_id', EmpEmployee::where('full_name', 'like', "%{$needle}%")->pluck('position_id'));
            });
        }

        return $q->pluck('position_id')->all();
    }

    public function zoomIn(): void
    {
        $this->zoom = min(1.6, round($this->zoom + 0.1, 2));
    }

    public function zoomOut(): void
    {
        $this->zoom = max(0.4, round($this->zoom - 0.1, 2));
    }

    public function zoomReset(): void
    {
        $this->zoom = 1.0;
    }

    public function branchColor(int $index): string
    {
        return self::BRANCH_COLORS[$index % count(self::BRANCH_COLORS)];
    }
};
?>

<style>
    .org-chart-children { display: flex; position: relative; padding-top: 24px; }
    .org-chart-children::before {
        content: ''; position: absolute; top: 0; left: 50%; width: 0; height: 24px;
        border-left: 2px solid var(--line-color, #94a3b8);
    }
    .org-chart-children > li { position: relative; padding-top: 24px; }
    .org-chart-children > li::before,
    .org-chart-children > li::after {
        content: ''; position: absolute; top: 0; width: 50%; height: 24px;
        border-top: 2px solid var(--line-color, #94a3b8);
    }
    .org-chart-children > li::before { right: 50%; }
    .org-chart-children > li::after { left: 50%; border-left: 2px solid var(--line-color, #94a3b8); }
    .org-chart-children > li:first-child::before { border-top-color: transparent; }
    .org-chart-children > li:last-child::after { border-top-color: transparent; }
    .org-chart-children > li:only-child::before,
    .org-chart-children > li:only-child::after { display: none; }

    @media print {
        .org-chart-noprint { display: none !important; }
        .org-chart-canvas { transform: none !important; overflow: visible !important; }
    }
</style>

<div class="max-w-full mx-auto my-5 px-4">
    <x-grid.toolbar class="org-chart-noprint mb-3 flex-wrap rounded-md border border-[#d7dbe2]">
        <a href="{{ route('hrm.position') }}" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-2.5 py-1.5 rounded no-underline">← {{ t('Position Master') }}</a>
        <div class="flex items-center gap-1.5 shrink-0">
            <span class="text-sm">🔍</span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search employee name / position…') }}" class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
        </div>
        <x-form.select wire:model.live="filterDepartmentId" :options="$this->departments" :placeholder="'▾ '.t('All Departments')" class="shrink-0" />
        <x-slot:tools>
            <div class="flex items-center gap-1 bg-[#eef1f5] rounded-lg p-1">
                <button type="button" wire:click="zoomOut" class="w-7 h-7 text-sm font-bold rounded-md hover:bg-white">−</button>
                <span class="text-[10.5px] font-bold text-muted w-10 text-center">{{ number_format($zoom * 100, 0) }}%</span>
                <button type="button" wire:click="zoomIn" class="w-7 h-7 text-sm font-bold rounded-md hover:bg-white">+</button>
                <button type="button" wire:click="zoomReset" class="text-[10.5px] font-bold px-2 py-1 rounded-md hover:bg-white">{{ t('Reset') }}</button>
            </div>
            <button type="button" onclick="window.print()" class="shrink-0 text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">🖨 {{ t('Print') }}</button>
        </x-slot:tools>
    </x-grid.toolbar>

    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-auto" style="max-height: 78vh;">
        <div class="org-chart-canvas p-8 origin-top-left" style="transform: scale({{ $zoom }}); width: max-content;">
            @if (empty($this->roots))
                <p class="text-xs text-muted">{{ t('No active positions yet.') }}</p>
            @else
                <ul class="flex gap-10 flex-wrap justify-center">
                    @foreach ($this->roots as $i => $root)
                        <x-org-chart.node :node="$root" :depth="0" :color="$this->branchColor($i)" :highlight-ids="$this->highlightIds" />
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if (empty($this->roots) === false && collect($this->roots)->every(fn ($r) => $r->children->isEmpty()))
        <p class="text-[11px] text-muted mt-2">💡 {{ t('All positions still stand alone (none has "Reports To" set yet). Set the superior-subordinate relations in') }} <a href="{{ route('hrm.position') }}" class="text-accent underline">{{ t('Position Master') }}</a> {{ t('so the organization tree takes shape.') }}</p>
    @endif
</div>
