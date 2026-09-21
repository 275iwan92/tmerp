<?php

use App\Models\Master\ProdProcess;
use App\Services\ProDev\UsageRateEngine;
use App\Models\Master\RefFinishingType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Setting Aturan Bill of Process (prod_bop_rule) -- sumber engine BOP/BOM di
// ProDev (App\Services\ProDev\SpecEngine). User 2026-09-17: urutan finishing
// "ke depan bisa diubah oleh setting admin", Emboss "tidak selalu Plong, buat
// fleksibel" -> urutan (step_seq) dan penggabungan (merge_group) diatur di sini.
// Hanya admin (sec_user.is_admin) yang boleh mengubah; lainnya baca saja.
new #[Title('Aturan Bill of Process — TELASIH MAKMUR ERP')] class extends Component
{
    private const TABLE = 'prod_bop_rule';

    public string $panel = 'grid';

    public ?int $editingId = null;

    public string $rule_scope = 'STANDARD';

    public $finishing_type_id = '';

    public string $part_type = '';

    public $step_seq = 10;

    public $process_id = '';

    public string $step_label = '';

    public string $merge_group = '';

    public string $merge_label = '';

    public string $machine_role = '';

    public string $condition_code = '';

    public string $material_pattern = '';

    public string $material_rule = '';

    public string $material_role = '';

    public string $usage_basis = '';

    public $usage_rate = '';

    public $waste_pct = '';

    public $tolerance_pct = '';

    public string $note = '';

    public string $flash = '';

    public bool $rule_activate = true;

    public const CONDITIONS = [
        'PLANO_NEEDS_CUT' => 'Plano beda ukuran dgn lembar cetak',
        'MULTI_LAYER' => 'Bahan berlapis (lebih dari 1 bahan / DC+SF / DC+SHEET)',
        'NEEDS_GLUE' => 'Struktur perlu lem (glue / lock bottom / crash)',
    ];

    public const ROLES = ['PRINT' => 'Mesin cetak pilihan ProDev', 'DIECUT' => 'Mesin pond pilihan ProDev'];

    public const MATERIAL_RULES = [
        'ROLL_WIDTH_GE_SHEET' => 'Roll terkecil yang lebarnya >= sisi panjang lembar',
        'MATCH_ATTRIBUTE' => 'Nama bahan paling cocok dengan atribut finishing',
        'BEST_STOCK' => 'Bahan yang cocok dengan stok gudang terbanyak',
        'INK_BY_NAME' => 'Tinta sesuai nama tiap unit ({ink} di pola)',
    ];

    public const USAGE = [
        'SHEET_LENGTH' => 'Meter per lembar (sisi pendek) x takaran',
        'SHEET_AREA' => 'Gram per m² lembar cetak',
        'INK_COVERAGE' => 'Gram per m² area tertutup tinta',
        'PER_PCS' => 'Gram per pcs produk',
        'PER_PACK' => 'Meter per pack',
        'AREA_UNKNOWN' => 'Luas pakai belum bisa dihitung (isi manual)',
    ];

    public function canEdit(): bool
    {
        return (bool) Auth::user()?->is_admin;
    }

    #[Computed]
    public function rules()
    {
        return DB::connection('tm2mst')->table(self::TABLE.' as r')
            ->leftJoin('prod_process as p', 'p.id_process', '=', 'r.process_id')
            ->leftJoin('ref_finishing_type as f', 'f.id_finishing_type', '=', 'r.finishing_type_id')
            ->orderBy('r.step_seq')->orderBy('r.id_bop_rule')
            ->get(['r.*', 'p.process_name', 'f.finishing_name']);
    }

    #[Computed]
    public function processOptions()
    {
        return ProdProcess::where('activate', true)->orderBy('process_name')->pluck('process_name', 'id_process');
    }

    #[Computed]
    public function finishingOptions()
    {
        return RefFinishingType::where('activate', true)->orderBy('finishing_name')->pluck('finishing_name', 'id_finishing_type');
    }

    public function openForm(?int $id = null): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $this->resetErrorBag();
        $this->editingId = $id;
        $r = $id ? DB::connection('tm2mst')->table(self::TABLE)->where('id_bop_rule', $id)->first() : null;

        $this->rule_scope = $r->rule_scope ?? 'STANDARD';
        $this->finishing_type_id = (string) ($r->finishing_type_id ?? '');
        $this->part_type = $r->part_type ?? '';
        $this->step_seq = $r->step_seq ?? ((int) $this->rules->max('step_seq') + 10);
        $this->process_id = (string) ($r->process_id ?? '');
        $this->step_label = $r->step_label ?? '';
        $this->merge_group = $r->merge_group ?? '';
        $this->merge_label = $r->merge_label ?? '';
        $this->machine_role = $r->machine_role ?? '';
        $this->condition_code = $r->condition_code ?? '';
        $this->material_pattern = $r->material_pattern ?? '';
        $this->material_rule = $r->material_rule ?? '';
        $this->material_role = $r->material_role ?? '';
        $this->usage_basis = $r->usage_basis ?? '';
        $this->usage_rate = $r->usage_rate ?? '';
        $this->waste_pct = $r->waste_pct ?? '';
        $this->tolerance_pct = $r->tolerance_pct ?? '';
        $this->note = $r->note ?? '';
        $this->rule_activate = $r ? (bool) $r->activate : true;
        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function save(): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $data = $this->validate([
            'rule_scope' => ['required', 'in:STANDARD,FINISHING'],
            'finishing_type_id' => ['required_if:rule_scope,FINISHING', 'nullable', 'exists:tm2mst.ref_finishing_type,id_finishing_type'],
            'part_type' => ['nullable', 'in:DC,DC+SHEET,DC+SF,SHEET,KRAFT'],
            'step_seq' => ['required', 'integer', 'min:0', 'max:9999'],
            // Baris tanpa proses = aturan BAHAN saja (mis. lem laminasi).
            'process_id' => ['nullable', 'required_without:material_pattern', 'exists:tm2mst.prod_process,id_process'],
            'step_label' => ['nullable', 'required_with:process_id', 'string', 'max:120'],
            'merge_group' => ['nullable', 'string', 'max:20'],
            'merge_label' => ['nullable', 'string', 'max:60'],
            'machine_role' => ['nullable', 'in:'.implode(',', array_keys(self::ROLES))],
            'condition_code' => ['nullable', 'in:'.implode(',', array_keys(self::CONDITIONS))],
            'material_pattern' => ['nullable', 'string', 'max:150'],
            'material_rule' => ['nullable', 'in:'.implode(',', array_keys(self::MATERIAL_RULES))],
            'material_role' => ['nullable', 'required_with:material_pattern', 'string', 'max:30'],
            'usage_basis' => ['nullable', 'in:'.implode(',', array_keys(self::USAGE))],
            'usage_rate' => ['nullable', 'numeric', 'min:0'],
            'waste_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'rule_activate' => ['boolean'],
        ]);

        $blank = fn ($v) => $v === '' ? null : $v;
        $payload = [
            'rule_scope' => $data['rule_scope'],
            'finishing_type_id' => $data['rule_scope'] === 'FINISHING' ? (int) $data['finishing_type_id'] : null,
            'part_type' => $blank($data['part_type']),
            'step_seq' => (int) $data['step_seq'],
            'process_id' => $data['process_id'] ? (int) $data['process_id'] : null,
            'step_label' => $blank(trim((string) $data['step_label'])),
            'merge_group' => $blank(strtoupper(trim((string) $data['merge_group']))),
            'merge_label' => $blank(trim((string) $data['merge_label'])),
            'machine_role' => $blank($data['machine_role']),
            'condition_code' => $data['rule_scope'] === 'STANDARD' ? $blank($data['condition_code']) : null,
            'material_pattern' => $blank(trim((string) $data['material_pattern'])),
            'material_rule' => $blank($data['material_rule']),
            'material_role' => $blank(strtoupper(trim((string) $data['material_role']))),
            'usage_basis' => $blank($data['usage_basis']),
            'usage_rate' => $blank((string) $data['usage_rate']),
            'waste_pct' => $blank((string) $data['waste_pct']),
            'tolerance_pct' => $blank((string) $data['tolerance_pct']),
            'note' => $blank(trim((string) $data['note'])),
            'activate' => $data['rule_activate'] ? 1 : 0,
            'updated_at' => now(),
            'updated_by' => Auth::user()->login,
        ];

        $table = DB::connection('tm2mst')->table(self::TABLE);

        if ($this->editingId) {
            $table->where('id_bop_rule', $this->editingId)->update($payload);
        } else {
            $table->insert($payload + ['created_at' => now(), 'created_by' => Auth::user()->login]);
        }

        unset($this->rules);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        if (! $this->canEdit()) {
            return;
        }

        DB::connection('tm2mst')->table(self::TABLE)->where('id_bop_rule', $id)->update([
            'activate' => DB::raw('1 - activate'),
            'updated_at' => now(),
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->rules);
    }

    // Usulan takaran/waste dari data produksi nyata (BACKLOG §19.2).
    public function refreshSuggestions(): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $r = app(UsageRateEngine::class)->refresh(now()->subMonths(3), true);
        $this->flash = $r['note'] ?: "Usulan disegarkan dari {$r['sample_total']} WO selesai; {$r['applied']} aturan ber-auto diterapkan.";
        unset($this->rules);
    }

    public function applySuggestion(int $id): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $r = DB::connection('tm2mst')->table(self::TABLE)->where('id_bop_rule', $id)->first();

        if (! $r || ($r->rate_suggested === null && $r->waste_suggested === null)) {
            return;
        }

        DB::connection('tm2mst')->table(self::TABLE)->where('id_bop_rule', $id)->update(array_filter([
            'usage_rate' => $r->rate_suggested,
            'waste_pct' => $r->waste_suggested,
        ], fn ($v) => $v !== null) + ['rate_source' => 'AUTO', 'updated_at' => now(), 'updated_by' => Auth::user()->login]);

        $this->flash = 'Usulan diterapkan ke aturan tersebut.';
        unset($this->rules);
    }

    public function toggleAuto(int $id): void
    {
        if (! $this->canEdit()) {
            return;
        }

        DB::connection('tm2mst')->table(self::TABLE)->where('id_bop_rule', $id)->update([
            'auto_apply' => DB::raw('1 - auto_apply'), 'updated_at' => now(), 'updated_by' => Auth::user()->login,
        ]);
        unset($this->rules);
    }

    // Geser urutan satu langkah dengan menukar step_seq dengan tetangganya.
    public function move(int $id, int $dir): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $list = $this->rules->values();
        $i = $list->search(fn ($r) => (int) $r->id_bop_rule === $id);
        $j = $i === false ? null : $i + ($dir < 0 ? -1 : 1);

        if ($j === null || ! isset($list[$j])) {
            return;
        }

        [$a, $b] = [$list[$i], $list[$j]];
        $seqA = (int) $a->step_seq;
        $seqB = (int) $b->step_seq === $seqA ? $seqA + ($dir < 0 ? -1 : 1) : (int) $b->step_seq;
        $db = DB::connection('tm2mst');
        $by = ['updated_at' => now(), 'updated_by' => Auth::user()->login];

        $db->transaction(function () use ($db, $a, $b, $seqA, $seqB, $by) {
            $db->table(self::TABLE)->where('id_bop_rule', $a->id_bop_rule)->update(['step_seq' => $seqB] + $by);
            $db->table(self::TABLE)->where('id_bop_rule', $b->id_bop_rule)->update(['step_seq' => $seqA] + $by);
        });

        unset($this->rules);
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4">

        @if ($flash)
            <div class="px-4 py-2.5 mb-3 rounded-md bg-[#eaf7ee] border border-[#bfe6cc] text-[#1f6b3c] text-[12.5px]">{{ $flash }}</div>
        @endif

        @if ($panel === 'grid')
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total Aturan" :value="$this->rules->count()" />
            <x-grid.stat-box tone="active" label="Langkah Standar" :value="$this->rules->where('rule_scope', 'STANDARD')->count()" />
            <x-grid.stat-box tone="warning" label="Sisipan Finishing" :value="$this->rules->where('rule_scope', 'FINISHING')->count()" />
        </x-grid.stat-row>

        <div class="bg-[#f4f8ff] border border-[#cfdcf2] rounded-[10px] px-4 py-3 mb-3 text-[12px] text-[#33415c] leading-relaxed">
            Engine ProDev menyusun <b>Bill of Process</b> dari tabel ini: semua <b>Langkah Standar</b> (yang kondisinya terpenuhi)
            ditambah <b>Sisipan Finishing</b> sesuai finishing yang dipilih di Inquiry, lalu diurutkan menurut kolom <b>Urut</b>.
            Langkah bersebelahan dengan <b>Grup Gabung</b> sama dijadikan satu baris (mis. "Plong Depan + Emboss") —
            kosongkan grup gabung Emboss bila Emboss dikerjakan terpisah.
            Token label: <code>{machine}</code> <code>{attr}</code> <code>{roll}</code> <code>{pack}</code>.
            @unless ($this->canEdit())
                <br><b class="text-[#b45309]">Hanya admin yang dapat mengubah aturan.</b>
            @endunless
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="text-[12px] text-muted">Urutan berlaku untuk semua job baru; routing yang sudah disimpan MANUAL tidak ikut berubah.</div>
                <x-slot:newRecord>
                    @if ($this->canEdit())
                        <button type="button" wire:click="refreshSuggestions" title="Hitung usulan takaran & waste dari WO selesai 3 bulan terakhir"
                            class="shrink-0 px-3 py-1.5 mr-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">📈 Usulan dari Data Nyata</button>
                        <button type="button" wire:click="openForm"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    @endif
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr class="[&>th]:text-left [&>th]:px-3 [&>th]:py-2 [&>th]:bg-[#fafbfd] [&>th]:text-[#667] [&>th]:text-[11px] [&>th]:uppercase [&>th]:tracking-wide [&>th]:border-b [&>th]:border-[#eef1f5] [&>th]:sticky [&>th]:top-0 [&>th]:z-[1]">
                            <th class="w-[60px]">Urut</th>
                            <th class="w-[140px]">Jenis</th>
                            <th>Proses &amp; Label</th>
                            <th class="w-[130px]">Grup Gabung</th>
                            <th class="w-[170px]">Kondisi / Mesin</th>
                            <th class="w-[200px]">Bahan (BOM)</th>
                            <th class="w-[80px]">Status</th>
                            <th class="w-[120px] !text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rules as $r)
                            <tr wire:key="bop-{{ $r->id_bop_rule }}" @if ($this->canEdit()) wire:dblclick="openForm({{ $r->id_bop_rule }})" title="Klik 2x untuk buka detail" @endif
                                class="hover:bg-[#f7faff] cursor-pointer [&>td]:px-3 [&>td]:py-2 [&>td]:border-b [&>td]:border-[#eef1f5] {{ $r->activate ? '' : 'opacity-50' }}">
                                <td class="font-mono font-bold">{{ $r->step_seq }}</td>
                                <td>
                                    @if ($r->rule_scope === 'STANDARD')
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#e8eefb] text-[#2c3e69]">STANDAR</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fff4e0] text-[#b45309]">FINISHING</span>
                                        <div class="text-[11px] mt-0.5">{{ $r->finishing_name }}</div>
                                    @endif
                                    @if ($r->part_type)
                                        <div class="text-[10px] text-muted">hanya {{ $r->part_type }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($r->process_id)
                                        <div class="font-semibold">{{ $r->step_label }}</div>
                                        <div class="text-[11px] text-muted">{{ $r->process_name }}
                                            @if ($r->waste_pct !== null) · waste {{ (float) $r->waste_pct }}% · toleransi {{ (float) $r->tolerance_pct }}% @endif
                                        </div>
                                    @else
                                        <div class="font-semibold text-[#6b4f10]">Bahan: {{ $r->material_role }}</div>
                                        <div class="text-[11px] text-muted">tidak menambah langkah routing</div>
                                    @endif
                                    @if ($r->note)<div class="text-[11px] text-muted">{{ $r->note }}</div>@endif
                                </td>
                                <td>
                                    @if ($r->merge_group)
                                        <span class="font-mono text-[11px]">{{ $r->merge_group }}</span>
                                        @if ($r->merge_label)<div class="text-[11px] text-muted">"{{ $r->merge_label }}"</div>@endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-[11px]">
                                    {{ $r->condition_code ? (self::CONDITIONS[$r->condition_code] ?? $r->condition_code) : 'Selalu' }}
                                    @if ($r->machine_role)<div class="text-muted">{{ self::ROLES[$r->machine_role] ?? $r->machine_role }}</div>@endif
                                </td>
                                <td class="text-[11px]">
                                    @if ($r->rate_suggested !== null || $r->waste_suggested !== null)
                                        <div class="mb-1 px-1.5 py-1 rounded bg-[#f4f8ff] border border-[#cfdcf2]">
                                            <b>Usulan data nyata:</b>
                                            @if ($r->rate_suggested !== null) takaran {{ (float) $r->rate_suggested }} @endif
                                            @if ($r->waste_suggested !== null) waste {{ (float) $r->waste_suggested }}% @endif
                                            <span class="text-muted">({{ $r->suggest_sample }} WO)</span>
                                            @if ($this->canEdit())
                                                <button type="button" wire:click="applySuggestion({{ $r->id_bop_rule }})"
                                                    class="ml-1 px-1.5 py-0.5 rounded bg-white border border-[#d7dbe2] cursor-pointer">Terapkan</button>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($this->canEdit())
                                        <button type="button" wire:click="toggleAuto({{ $r->id_bop_rule }})" title="Usulan diterapkan otomatis tiap awal bulan"
                                            class="mb-1 px-1.5 py-0.5 rounded text-[10px] font-bold cursor-pointer {{ $r->auto_apply ? 'bg-[#eaf7ee] text-[#1f6b3c] border border-[#bfe6cc]' : 'bg-[#eef0f3] text-[#8a8f98] border border-[#dfe3e8]' }}">
                                            AUTO {{ $r->auto_apply ? 'AKTIF' : 'MATI' }}</button>
                                    @endif
                                    @if ($r->material_pattern)
                                        <span class="font-mono">{{ $r->material_pattern }}</span>
                                        <div class="text-muted">{{ $r->material_role }} · {{ self::MATERIAL_RULES[$r->material_rule] ?? '' }}</div>
                                        @if ($r->usage_basis)
                                            <div class="text-muted">{{ self::USAGE[$r->usage_basis] ?? $r->usage_basis }}{{ $r->usage_rate !== null ? ': '.(float) $r->usage_rate : '' }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $r->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $r->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($this->canEdit())
                                        <x-grid.action-icon icon="fa-chevron-up" title="Naikkan urutan" action="move({{ $r->id_bop_rule }}, -1)" />
                                        <x-grid.action-icon icon="fa-chevron-down" title="Turunkan urutan" action="move({{ $r->id_bop_rule }}, 1)" />
                                        <x-grid.action-icon icon="fa-pen" title="Edit" action="openForm({{ $r->id_bop_rule }})" />
                                        <x-grid.action-icon :active="$r->activate" :title="$r->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $r->id_bop_rule }})"
                                            :confirm="($r->activate ? 'Nonaktifkan' : 'Aktifkan').' aturan '.($r->step_label ?: $r->material_role).'?'"
                                            :variant="$r->activate ? 'danger' : 'default'" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="8" message="Belum ada aturan." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
        @endif

        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">{{ $editingId ? 'Edit Aturan BOP' : 'Aturan BOP Baru' }}</div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-form.select label="Jenis Aturan *" name="rule_scope" wire:model.live="rule_scope"
                        :options="['STANDARD' => 'Langkah Standar', 'FINISHING' => 'Sisipan Finishing']" />
                    @if ($rule_scope === 'FINISHING')
                        <x-form.select label="Finishing *" name="finishing_type_id" wire:model="finishing_type_id"
                            placeholder="— pilih finishing —" :options="$this->finishingOptions->all()" />
                    @else
                        <x-form.select :selected="$condition_code" :liveSync="true" label="Kondisi" name="condition_code" wire:model="condition_code"
                            placeholder="Selalu dipakai" :options="self::CONDITIONS" />
                    @endif
                    <x-form.select label="Khusus Tipe Part" name="part_type" wire:model="part_type"
                        placeholder="Semua tipe part" :options="['DC' => 'DC', 'DC+SHEET' => 'DC+SHEET', 'DC+SF' => 'DC+SF', 'SHEET' => 'SHEET', 'KRAFT' => 'KRAFT']" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-form.input label="Urut *" name="step_seq" type="number" wire:model="step_seq" />
                    <x-form.select label="Proses (kosong = aturan bahan saja)" name="process_id" wire:model="process_id"
                        placeholder="— tanpa proses —" :options="$this->processOptions->all()" />
                    <x-form.select :selected="$machine_role" :liveSync="true" label="Mesin Otomatis" name="machine_role" wire:model="machine_role"
                        placeholder="Tanpa mesin" :options="self::ROLES" />
                </div>
                <x-form.input label="Label di Routing" name="step_label" wire:model="step_label"
                    placeholder="mis. Laminating Doff (Plastik {roll} cm)" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-form.input label="Waste default (%)" name="waste_pct" type="number" step="0.1" wire:model="waste_pct" />
                    <x-form.input label="Toleransi default (%)" name="tolerance_pct" type="number" step="0.1" wire:model="tolerance_pct" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-form.input label="Grup Gabung" name="merge_group" wire:model="merge_group" placeholder="mis. PLONG (kosong = tidak digabung)" />
                    <x-form.input label="Teks Sambungan saat Digabung" name="merge_label" wire:model="merge_label" placeholder="mis. + Emboss" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-form.input label="Pola Bahan (mat_raw, pisahkan dgn |)" name="material_pattern" wire:model="material_pattern" placeholder="mis. PLASTIKLAMDOFF%" />
                    <x-form.select :selected="$material_rule" :liveSync="true" label="Cara Pilih Bahan" name="material_rule" wire:model="material_rule"
                        placeholder="Tidak ada bahan" :options="self::MATERIAL_RULES" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-form.input label="Nama Baris BOM" name="material_role" wire:model="material_role" placeholder="mis. LEM LAMINASI" />
                    <x-form.select :selected="$usage_basis" :liveSync="true" label="Dasar Pemakaian" name="usage_basis" wire:model="usage_basis"
                        placeholder="Tidak dihitung" :options="self::USAGE" />
                    <x-form.input label="Takaran" name="usage_rate" type="number" step="any" wire:model="usage_rate" placeholder="sesuai dasar pemakaian" />
                </div>
                <x-form.input label="Catatan" name="note" wire:model="note" />
                <x-form.checkbox label="Aktif" wire:model="rule_activate" />

                <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                </div>
            </form>
        @endif
    </div>
</div>
