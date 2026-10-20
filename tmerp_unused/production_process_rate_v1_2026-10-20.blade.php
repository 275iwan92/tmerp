<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPartProcessEmployeeRate;
use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProduct;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Layar kelola target rate default per Produk+Part+Proses+Mesin/Pegawai --
// docs/production_scheduling_wo_generation_delta.md §4.2/4.3 sudah punya
// skema (prod_part_process_machine_rate/_employee_rate) sejak WO Generation
// dibangun, tapi cuma bisa diisi lewat tinker sampai sekarang. Diminta user
// 2026-08-28 (tombol "Link & Act" di Bill of Process WO Detail) supaya PPIC
// bisa kelola sendiri, bukan lewat WoGenerationService::refillTargetDefault()
// doang. Tab Mesin/Pegawai -- 2 tabel terpisah (keputusan user awal sesi WO
// Generation: target beda sumber daya beda tabel), 1 layar krn bentuknya
// identik (pola sama scrap-type.blade.php).
new #[Title('Target Rate Proses × Mesin/Pegawai — TELASIH MAKMUR ERP')] class extends Component
{
    public string $resource = 'MACHINE'; // MACHINE|MANUAL -- tab aktif

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $product_id = null;

    public string $part_code = '';

    public ?int $process_id = null;

    public ?int $machine_id = null;

    public ?int $employee_id = null;

    public float $target_qty_per_hour = 0;

    #[Computed]
    public function rates()
    {
        if ($this->resource === 'MACHINE') {
            return ProdPartProcessMachineRate::with(['product', 'process', 'machine'])
                ->orderByDesc('rate_id')->get();
        }

        return ProdPartProcessEmployeeRate::with(['product', 'process', 'employee'])
            ->orderByDesc('rate_id')->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->resource === 'MACHINE' ? ProdPartProcessMachineRate::count() : ProdPartProcessEmployeeRate::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return $this->resource === 'MACHINE' ? ProdPartProcessMachineRate::active()->count() : ProdPartProcessEmployeeRate::active()->count();
    }

    #[Computed]
    public function productOptions()
    {
        return ProdProduct::where('activate', true)->orderBy('product_name')->get();
    }

    #[Computed]
    public function processOptions()
    {
        return ProdProcess::where('activate', true)->orderBy('process_name')->get();
    }

    #[Computed]
    public function machineOptions()
    {
        return ProdMachine::where('activate', true)->orderBy('machine_name')->get();
    }

    #[Computed]
    public function employeeOptions()
    {
        return EmpEmployee::where('activate', true)->orderBy('full_name')->limit(200)->get();
    }

    public function switchResource(string $resource): void
    {
        $this->resource = $resource;
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'product_id', 'part_code', 'process_id', 'machine_id', 'employee_id', 'target_qty_per_hour']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $rate = $this->resource === 'MACHINE'
            ? ProdPartProcessMachineRate::findOrFail($id)
            : ProdPartProcessEmployeeRate::findOrFail($id);

        $this->editingId = $rate->rate_id;
        $this->product_id = $rate->product_id;
        $this->part_code = $rate->part_code;
        $this->process_id = $rate->process_id;
        $this->machine_id = $rate->machine_id ?? null;
        $this->employee_id = $rate->employee_id ?? null;
        $this->target_qty_per_hour = (float) $rate->target_qty_per_hour;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $this->validate([
            'product_id' => ['required', 'integer'],
            'part_code' => ['required', 'string', 'max:50'],
            'process_id' => ['required', 'integer'],
            'target_qty_per_hour' => ['required', 'numeric', 'min:0.01'],
        ]);

        $data = [
            'product_id' => $this->product_id,
            'part_code' => $this->part_code,
            'process_id' => $this->process_id,
            'target_qty_per_hour' => $this->target_qty_per_hour,
            'updated_by' => Auth::user()->login,
        ];

        if ($this->resource === 'MACHINE') {
            $this->validate(['machine_id' => ['required', 'integer']]);
            $data['machine_id'] = $this->machine_id;
            if ($this->editingId) {
                ProdPartProcessMachineRate::whereKey($this->editingId)->update($data);
            } else {
                ProdPartProcessMachineRate::create($data + ['activate' => true, 'created_by' => Auth::user()->login]);
            }
        } else {
            $this->validate(['employee_id' => ['required', 'integer']]);
            $data['employee_id'] = $this->employee_id;
            if ($this->editingId) {
                ProdPartProcessEmployeeRate::whereKey($this->editingId)->update($data);
            } else {
                ProdPartProcessEmployeeRate::create($data + ['activate' => true, 'created_by' => Auth::user()->login]);
            }
        }

        $this->showModal = false;
    }

    public function toggleActive(int $id): void
    {
        if ($this->resource === 'MACHINE') {
            $rate = ProdPartProcessMachineRate::findOrFail($id);
        } else {
            $rate = ProdPartProcessEmployeeRate::findOrFail($id);
        }
        $rate->update(['activate' => ! $rate->activate, 'updated_by' => Auth::user()->login]);
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="2">
            <x-grid.stat-box tone="total" label="Total Rate" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Aktif" :value="$this->statActive" />
        </x-grid.stat-row>

        <div class="flex gap-1.5 mb-3">
            <button type="button" wire:click="switchResource('MACHINE')" class="px-3.5 py-1.5 rounded text-xs font-semibold cursor-pointer {{ $resource === 'MACHINE' ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">⚙ Mesin</button>
            <button type="button" wire:click="switchResource('MANUAL')" class="px-3.5 py-1.5 rounded text-xs font-semibold cursor-pointer {{ $resource === 'MANUAL' ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">👤 Pegawai (Manual)</button>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <span class="text-[11px] text-muted italic">Target default per Produk+Part+Proses+{{ $resource === 'MACHINE' ? 'Mesin' : 'Pegawai' }} — dipakai WO Generation &amp; tombol "↻" di Bill of Process WO Detail, selalu bisa di-override manual per WO.</span>
                <x-slot:newRecord>
                    <button type="button" wire:click="openNew" class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Produk / Part</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Proses</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">{{ $resource === 'MACHINE' ? 'Mesin' : 'Pegawai' }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Target/Jam</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rates as $r)
                            <tr wire:key="rate-{{ $resource }}-{{ $r->rate_id }}">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="font-semibold">{{ $r->product?->product_name ?? '—' }}</div>
                                    <div class="text-[10.5px] text-muted font-mono">{{ $r->part_code }}</div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->process?->process_name ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $resource === 'MACHINE' ? ($r->machine?->machine_name ?? '—') : ($r->employee?->full_name ?? '—') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $r->target_qty_per_hour, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->activate ? 'bg-[#dcfce7] text-[#15803d]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $r->activate ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit" action="openEdit({{ $r->rate_id }})" />
                                    <button type="button" wire:click="toggleActive({{ $r->rate_id }})" class="w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer" title="{{ $r->activate ? 'Nonaktifkan' : 'Aktifkan' }}">⏻</button>
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="Belum ada rate {{ $resource === 'MACHINE' ? 'mesin' : 'pegawai' }} tersimpan." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeModal">
            <div class="bg-white rounded-xl w-[440px] max-w-[95vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>{{ $editingId ? 'Edit' : 'Tambah' }} Rate {{ $resource === 'MACHINE' ? 'Mesin' : 'Pegawai' }}</span>
                    <button type="button" wire:click="closeModal" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Produk</label>
                        <select wire:model="product_id" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                            <option value="">-- pilih produk --</option>
                            @foreach ($this->productOptions as $p)
                                <option value="{{ $p->id_product }}">{{ $p->product_name }}</option>
                            @endforeach
                        </select>
                        @error('product_id') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                    </div>
                    <x-form.input label="Part Code" name="part_code" wire:model="part_code" placeholder="mis. BODY" class="font-mono" />
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Proses</label>
                        <select wire:model="process_id" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                            <option value="">-- pilih proses --</option>
                            @foreach ($this->processOptions as $proc)
                                <option value="{{ $proc->id_process }}">{{ $proc->process_name }}</option>
                            @endforeach
                        </select>
                        @error('process_id') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                    </div>
                    @if ($resource === 'MACHINE')
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Mesin</label>
                            <select wire:model="machine_id" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                <option value="">-- pilih mesin --</option>
                                @foreach ($this->machineOptions as $m)
                                    <option value="{{ $m->machine_id }}">{{ $m->machine_name }}</option>
                                @endforeach
                            </select>
                            @error('machine_id') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Pegawai</label>
                            <select wire:model="employee_id" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                <option value="">-- pilih pegawai --</option>
                                @foreach ($this->employeeOptions as $e)
                                    <option value="{{ $e->employee_id }}">{{ $e->full_name }}</option>
                                @endforeach
                            </select>
                            @error('employee_id') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Target Qty / Jam</label>
                        <input type="number" wire:model="target_qty_per_hour" min="0.01" step="0.01" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                        @error('target_qty_per_hour') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-3 mt-2 border-t border-[#eef1f5]">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="button" wire:click="save" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
