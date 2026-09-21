<?php

use App\Models\Master\MatCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Pengganti layar input doc_rmprefix1..5 yang sebelumnya TIDAK ADA (cuma
// dibaca lewat dropdown berantai di Item Bahan Baku & PR) -- lihat
// mat_category (migration 2026_08_26_000002). Officer sekarang bisa
// tambah/nonaktifkan kategori sendiri dari sini tanpa insert manual ke DB.
// Level & Induk dikunci setelah baris dibuat (pola sama "kategori dikunci"
// di master/raw-material/entry.blade.php) supaya tidak diam-diam
// merusak hirarki yang sudah dipakai material lain.
new #[Title('Master Kategori Bahan Baku — TELASIH MAKMUR ERP')] class extends Component
{
    public array $stockClasses = ['RAW', 'INDIRECT', 'SERVICE'];

    public string $search = '';

    public string $panel = 'grid'; // grid | form

    public string $mode = 'new'; // new | edit

    public ?int $editingId = null;

    public int $level = 1;

    public ?int $parent_id = null;

    public string $category_name = '';

    public string $prefix_char = '';

    public string $note = '';

    public bool $cat_activate = true;

    // khusus level 1
    public string $stock_class = 'RAW';

    public ?int $default_coa_account_id = null;

    public ?int $default_stock_asset_account_id = null;

    public ?int $default_gr_ir_account_id = null;

    // Tree collapsible (2026-08-26, instruksi user setelah lihat 553 baris
    // flat "tidak dimengerti") -- pola chevron expand-row griduistandard.md
    // §9, diperluas jadi rekursif multi-level (bukan cuma 1 tingkat preview
    // spt grid CO). $expanded simpan id_category yang lagi kebuka.
    public array $expanded = [];

    public function toggleExpand(int $id): void
    {
        if (in_array($id, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$id]));

            return;
        }

        $this->expanded[] = $id;
    }

    // Mode cari: flat, abaikan $expanded (biar hasil dalam-dalam tetap
    // ketemu tanpa harus expand manual dulu). Mode normal: hirarki
    // di-flatten server-side sesuai $expanded, dengan info _depth/_hasChildren
    // per baris supaya Blade tinggal render, tanpa perlu partial rekursif.
    #[Computed]
    public function visibleRows()
    {
        if ($this->search !== '') {
            return MatCategory::query()
                ->with('parent')
                ->where(fn ($q) => $q
                    ->where('category_name', 'like', "%{$this->search}%")
                    ->orWhere('prefix_char', 'like', "%{$this->search}%"))
                ->orderBy('level')->orderBy('category_name')
                ->get()
                ->each(fn ($n) => $n->_depth = 0);
        }

        $all = MatCategory::orderBy('category_name')->get();

        $childrenOf = [];
        foreach ($all as $node) {
            $childrenOf[$node->parent_id ?? 0][] = $node;
        }

        $rows = collect();
        $walk = function ($nodes, int $depth) use (&$walk, &$rows, $childrenOf) {
            foreach ($nodes as $node) {
                $node->_depth = $depth;
                $node->_hasChildren = ! empty($childrenOf[$node->id_category]);
                $rows->push($node);

                if ($node->_hasChildren && in_array($node->id_category, $this->expanded, true)) {
                    $walk($childrenOf[$node->id_category], $depth + 1);
                }
            }
        };
        $walk($childrenOf[0] ?? [], 0);

        return $rows;
    }

    #[Computed]
    public function parentOptions()
    {
        if ($this->level <= 1) {
            return collect();
        }

        return MatCategory::level($this->level - 1)->where('activate', true)->orderBy('category_name')->get();
    }

    #[Computed]
    public function coaAccounts()
    {
        return DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->orderBy('account_code')->get(['account_id', 'account_code', 'account_name']);
    }

    public function updatedLevel(): void
    {
        $this->parent_id = null;
        unset($this->parentOptions);
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;

        if ($this->mode === 'edit' && $id) {
            $c = MatCategory::findOrFail($id);
            $this->level = $c->level;
            $this->parent_id = $c->parent_id;
            $this->category_name = $c->category_name;
            $this->prefix_char = $c->prefix_char;
            $this->note = (string) $c->note;
            $this->cat_activate = (bool) $c->activate;
            $this->stock_class = (string) ($c->stock_class ?? 'RAW');
            $this->default_coa_account_id = $c->default_coa_account_id;
            $this->default_stock_asset_account_id = $c->default_stock_asset_account_id;
            $this->default_gr_ir_account_id = $c->default_gr_ir_account_id;
        } else {
            $this->reset([
                'level', 'parent_id', 'category_name', 'prefix_char', 'note',
                'stock_class', 'default_coa_account_id', 'default_stock_asset_account_id',
                'default_gr_ir_account_id',
            ]);
            $this->level = 1;
            $this->stock_class = 'RAW';
            $this->cat_activate = true;
        }

        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function save(): void
    {
        $data = $this->validate([
            'level' => ['required', 'integer', 'min:1', 'max:5'],
            'parent_id' => [$this->level > 1 ? 'required' : 'nullable', 'integer'],
            'category_name' => ['required', 'string', 'max:100'],
            'prefix_char' => [
                'required', 'string', 'max:3',
                function ($attr, $value, $fail) {
                    $exists = MatCategory::where('parent_id', $this->level > 1 ? $this->parent_id : null)
                        ->where('prefix_char', $value)
                        ->when($this->mode === 'edit' && $this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                        ->exists();
                    if ($exists) {
                        $fail('Kode prefix ini sudah dipakai kategori lain dengan induk yang sama.');
                    }
                },
            ],
            'note' => ['nullable', 'string'],
            'stock_class' => ['nullable', 'in:'.implode(',', $this->stockClasses)],
        ]);

        $login = Auth::user()->login;

        $payload = [
            'level' => $data['level'],
            'parent_id' => $data['level'] > 1 ? $data['parent_id'] : null,
            'category_name' => $data['category_name'],
            'prefix_char' => strtoupper(trim($data['prefix_char'])),
            'note' => $data['note'] ?: null,
            'activate' => $this->cat_activate,
            'stock_class' => $data['level'] === 1 ? ($data['stock_class'] ?: 'RAW') : null,
            'default_coa_account_id' => $data['level'] === 1 ? $this->default_coa_account_id : null,
            'default_stock_asset_account_id' => $data['level'] === 1 ? $this->default_stock_asset_account_id : null,
            'default_gr_ir_account_id' => $data['level'] === 1 ? $this->default_gr_ir_account_id : null,
            'updated_by' => $login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            MatCategory::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = $login;
            MatCategory::create($payload);
        }

        unset($this->visibleRows);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $c = MatCategory::findOrFail($id);
        $c->activate = ! $c->activate;
        $c->updated_by = Auth::user()->login;
        $c->save();

        unset($this->visibleRows, $this->statTotal, $this->statActive, $this->statInactive, $this->statLevel1);
    }

    // Componentization grid+form (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).

    #[Computed]
    public function statTotal(): int
    {
        return MatCategory::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return MatCategory::where('activate', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return MatCategory::where('activate', false)->count();
    }

    #[Computed]
    public function statLevel1(): int
    {
        return MatCategory::level(1)->where('activate', true)->count();
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="total" label="Total Kategori" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Aktif" :value="$this->statActive" />
            <x-grid.stat-box tone="extra" label="Kategori Utama (Level 1)" :value="$this->statLevel1" />
            <x-grid.stat-box tone="critical" label="Nonaktif" :value="$this->statInactive" />
        </x-grid.stat-row>

        <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">
            Hirarki 5 level pembentuk nama &amp; kode <b>Item Bahan Baku</b> (Kategori → Jenis → Gramatur/Spec → Ukuran → Merek). Kategori Utama (Level 1) juga jadi fallback akun akuntansi (Persediaan/HPP/GR-IR) kalau item-nya sendiri tidak diisi.
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama / kode prefix…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="openModal('new')"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1] w-[32px]"></th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Kategori</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">Level</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Induk</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Kelas Stok</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->visibleRows as $c)
                            @php
                                $hasChildren = $c->_hasChildren ?? false;
                                $isOpen = in_array($c->id_category, $this->expanded, true);
                            @endphp
                            <tr wire:key="matcat-{{ $c->id_category }}" wire:dblclick="openModal('edit', {{ $c->id_category }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka detail">
                                <td class="px-2 py-2 border-b border-[#eef1f5] text-center">
                                    @if ($hasChildren)
                                        <button type="button" wire:click.stop="toggleExpand({{ $c->id_category }})" title="{{ $isOpen ? 'Tutup' : 'Lihat sub-kategori' }}"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded text-muted hover:bg-[#eef3fb] hover:text-accent cursor-pointer">{{ $isOpen ? '▾' : '▸' }}</button>
                                    @endif
                                </td>
                                {{-- padding-left inline TIMPA px-3 bawaan td (specificity) -- +12px basis biar depth=0 sama visualnya dgn td px-3 asli. --}}
                                <x-grid.identity-cell :id="$c->id_category" :name="$c->category_name" :code="$c->prefix_char" style="padding-left: {{ 12 + $c->_depth * 22 }}px" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block bg-[#eaf2ff] text-[#1d4ed8]">L{{ $c->level }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $c->parent?->category_name ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $c->level === 1 ? ($c->stock_class ?? '—') : '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $c->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $c->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit" action="openModal('edit', {{ $c->id_category }})" />
                                    <x-grid.action-icon :active="$c->activate" :title="$c->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $c->id_category }})"
                                        :confirm="($c->activate ? 'Nonaktifkan' : 'Aktifkan').' kategori '.$c->category_name.'?'"
                                        :variant="$c->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="Tidak ada." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $mode === 'edit' ? 'Edit Kategori' : 'Kategori Baru' }}
                    @if ($mode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">L{{ $level }} — {{ $prefix_char }}</span>@endif
                </div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                <div class="text-xs font-bold text-navy uppercase tracking-wide">Posisi di Hirarki</div>
                <div class="flex gap-2.5">
                    <div class="flex-1">
                        <x-form.select label="Level *" name="level" wire:model.live="level" :disabled="$mode === 'edit'"
                            :placeholder="false" :options="collect([1, 2, 3, 4, 5])->mapWithKeys(fn ($lv) => [$lv => 'Level '.$lv])" :selected="$level" />
                    </div>
                    @if ($level > 1)
                        <div class="flex-[2]">
                            <x-form.select label="Induk (Level {{ $level - 1 }}) *" name="parent_id" wire:model="parent_id" :disabled="$mode === 'edit'"
                                :options="$this->parentOptions->mapWithKeys(fn ($p) => [$p->id_category => $p->category_name.' ('.$p->prefix_char.')'])" :selected="$parent_id" />
                        </div>
                    @endif
                </div>
                @if ($mode === 'edit')
                    <p class="text-[11px] text-muted -mt-1">Level &amp; Induk dikunci setelah dibuat — kalau salah taruh, buat baris baru dan nonaktifkan yang lama.</p>
                @endif

                <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Identitas Kategori</div>
                <div class="flex gap-2.5">
                    <div class="flex-[2]">
                        <x-form.input label="Nama Kategori *" name="category_name" wire:model="category_name" placeholder="mis. Art Carton" />
                    </div>
                    <div class="flex-1">
                        <x-form.input label="Kode Prefix *" name="prefix_char" wire:model="prefix_char" maxlength="3" placeholder="mis. C" class="uppercase font-mono" />
                    </div>
                </div>
                <x-form.textarea label="Catatan" name="note" wire:model="note" rows="2" placeholder="Catatan tambahan (opsional)" />
                <x-form.checkbox label="Aktif" wire:model="cat_activate" />

                @php
                    $coaOptions = $this->coaAccounts->mapWithKeys(fn ($acc) => [$acc->account_id => $acc->account_code.' - '.$acc->account_name]);
                @endphp
                @if ($level === 1)
                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Default Akuntansi <span class="text-muted normal-case font-normal">(fallback kalau item-nya sendiri kosong)</span></div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select label="Kelas Stok" wire:model="stock_class" :placeholder="false"
                                :options="array_combine($stockClasses, $stockClasses)" :selected="$stock_class" />
                        </div>
                        <div class="flex-[2]">
                            <x-form.select label="Akun HPP / Beban" wire:model="default_coa_account_id"
                                placeholder="— tak ada default —" :options="$coaOptions" :selected="$default_coa_account_id" />
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select label="Akun Aset Persediaan" wire:model="default_stock_asset_account_id"
                                placeholder="— tak ada default —" :options="$coaOptions" :selected="$default_stock_asset_account_id" />
                        </div>
                        <div class="flex-1">
                            <x-form.select label="Akun Kliring GR/IR" wire:model="default_gr_ir_account_id"
                                placeholder="— tak ada default —" :options="$coaOptions" :selected="$default_gr_ir_account_id" />
                        </div>
                    </div>
                @endif

                <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                </div>
            </form>
        @endif
    </div>
</div>
