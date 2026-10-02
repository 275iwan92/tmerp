<?php

use App\Livewire\Concerns\OpensFromQuery;
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
new #[Title('Bill of Process Rules — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    // Dibuka dari grid v2 (?open={id}).
    protected function openFromQuery(int $id): void
    {
        $this->openForm($id);
    }

    // Dibuka dari grid v2 (+ New Record -> ?new=1).
    protected function newFromQuery(): void
    {
        $this->openForm();
    }

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
        'PLANO_NEEDS_CUT' => 'Plano size differs from the print sheet',
        'MULTI_LAYER' => 'Layered material (more than 1 material / DC+SF / DC+SHEET)',
        'NEEDS_GLUE' => 'Structure needs glue (glue / lock bottom / crash)',
    ];

    public const ROLES = ['PRINT' => 'Printing machine chosen by ProDev', 'DIECUT' => 'Die-cut machine chosen by ProDev'];

    public const MATERIAL_RULES = [
        'ROLL_WIDTH_GE_SHEET' => 'Smallest roll whose width >= the long side of the sheet',
        'MATCH_ATTRIBUTE' => 'Material name that best matches the finishing attribute',
        'BEST_STOCK' => 'Matching material with the most warehouse stock',
        'INK_BY_NAME' => 'Ink by each unit name ({ink} in the pattern)',
    ];

    public const USAGE = [
        'SHEET_LENGTH' => 'Meters per sheet (short side) x rate',
        'SHEET_AREA' => 'Grams per m² of print sheet',
        'INK_COVERAGE' => 'Grams per m² of ink-covered area',
        'PER_PCS' => 'Grams per product pcs',
        'PER_PACK' => 'Meters per pack',
        'AREA_UNKNOWN' => 'Usage area cannot be calculated yet (enter manually)',
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
        $this->flash = $r['note'] ?: str_replace([':n', ':applied'], [$r['sample_total'], $r['applied']], t('Suggestions refreshed from :n completed WOs; :applied auto rules applied.'));
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

        $this->flash = t('Suggestion applied to that rule.');
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
            <x-grid.stat-box tone="total" :label="t('Total BOP Rules')" :value="$this->rules->count()" />
            <x-grid.stat-box tone="active" :label="t('Standard Steps')" :value="$this->rules->where('rule_scope', 'STANDARD')->count()" />
            <x-grid.stat-box tone="warning" :label="t('Finishing Inserts')" :value="$this->rules->where('rule_scope', 'FINISHING')->count()" />
        </x-grid.stat-row>

        <div class="bg-[#f4f8ff] border border-[#cfdcf2] rounded-[10px] px-4 py-3 mb-3 text-[12px] text-[#33415c] leading-relaxed">
            {!! t('The ProDev engine builds the <b>Bill of Process</b> from this table: all <b>Standard Steps</b> (whose condition is met) plus the <b>Finishing Inserts</b> for the finishing chosen in the Inquiry, sorted by the <b>Step Order</b> column.') !!}
            {!! t('Adjacent steps with the same <b>Merge Group</b> become one row (e.g. "Plong Depan + Emboss") — leave the Emboss merge group empty when Emboss is done separately.') !!}
            {{ t('Label tokens:') }} <code>{machine}</code> <code>{attr}</code> <code>{roll}</code> <code>{pack}</code>.
            @unless ($this->canEdit())
                <br><b class="text-[#b45309]">{{ t('Only admins can change the rules.') }}</b>
            @endunless
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="text-[12px] text-muted">{{ t('The order applies to all new jobs; routings already saved as MANUAL do not change.') }}</div>
                <x-slot:newRecord>
                    @if ($this->canEdit())
                        <button type="button" wire:click="refreshSuggestions" title="{{ t('Calculate suggested rate & waste from WOs completed in the last 3 months') }}"
                            class="shrink-0 px-3 py-1.5 mr-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">📈 {{ t('Suggestions from Real Data') }}</button>
                        <button type="button" wire:click="openForm"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    @endif
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr class="[&>th]:text-left [&>th]:px-3 [&>th]:py-2 [&>th]:bg-[#fafbfd] [&>th]:text-[#667] [&>th]:text-[11px] [&>th]:uppercase [&>th]:tracking-wide [&>th]:border-b [&>th]:border-[#eef1f5] [&>th]:sticky [&>th]:top-0 [&>th]:z-[1]">
                            <th class="w-[60px]">{{ t('Step Order') }}</th>
                            <th class="w-[140px]">{{ t('Kind') }}</th>
                            <th>{{ t('Process & Label') }}</th>
                            <th class="w-[130px]">{{ t('Merge Group') }}</th>
                            <th class="w-[170px]">{{ t('Condition / Machine') }}</th>
                            <th class="w-[200px]">{{ t('Material (BOM)') }}</th>
                            <th class="w-[80px]">{{ t('Status') }}</th>
                            <th class="w-[120px] !text-right">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rules as $r)
                            <tr wire:key="bop-{{ $r->id_bop_rule }}" @if ($this->canEdit()) wire:dblclick="openForm({{ $r->id_bop_rule }})" title="{{ t('Double-click to open detail') }}" @endif
                                class="hover:bg-[#f7faff] cursor-pointer [&>td]:px-3 [&>td]:py-2 [&>td]:border-b [&>td]:border-[#eef1f5] {{ $r->activate ? '' : 'opacity-50' }}">
                                <td class="font-mono font-bold">{{ $r->step_seq }}</td>
                                <td>
                                    @if ($r->rule_scope === 'STANDARD')
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#e8eefb] text-[#2c3e69]">{{ t('STANDARD') }}</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fff4e0] text-[#b45309]">{{ t('FINISHING') }}</span>
                                        <div class="text-[11px] mt-0.5">{{ $r->finishing_name }}</div>
                                    @endif
                                    @if ($r->part_type)
                                        <div class="text-[10px] text-muted">{{ str_replace(':type', $r->part_type, t('only :type')) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($r->process_id)
                                        <div class="font-semibold">{{ $r->step_label }}</div>
                                        <div class="text-[11px] text-muted">{{ $r->process_name }}
                                            @if ($r->waste_pct !== null) · {{ t('waste') }} {{ (float) $r->waste_pct }}% · {{ t('tolerance') }} {{ (float) $r->tolerance_pct }}% @endif
                                        </div>
                                    @else
                                        <div class="font-semibold text-[#6b4f10]">{{ t('Material') }}: {{ $r->material_role }}</div>
                                        <div class="text-[11px] text-muted">{{ t('does not add a routing step') }}</div>
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
                                    {{ $r->condition_code ? (isset(self::CONDITIONS[$r->condition_code]) ? t(self::CONDITIONS[$r->condition_code]) : $r->condition_code) : t('Always') }}
                                    @if ($r->machine_role)<div class="text-muted">{{ isset(self::ROLES[$r->machine_role]) ? t(self::ROLES[$r->machine_role]) : $r->machine_role }}</div>@endif
                                </td>
                                <td class="text-[11px]">
                                    @if ($r->rate_suggested !== null || $r->waste_suggested !== null)
                                        <div class="mb-1 px-1.5 py-1 rounded bg-[#f4f8ff] border border-[#cfdcf2]">
                                            <b>{{ t('Real-data suggestion:') }}</b>
                                            @if ($r->rate_suggested !== null) {{ t('rate') }} {{ (float) $r->rate_suggested }} @endif
                                            @if ($r->waste_suggested !== null) {{ t('waste') }} {{ (float) $r->waste_suggested }}% @endif
                                            <span class="text-muted">({{ $r->suggest_sample }} WO)</span>
                                            @if ($this->canEdit())
                                                <button type="button" wire:click="applySuggestion({{ $r->id_bop_rule }})"
                                                    class="ml-1 px-1.5 py-0.5 rounded bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Apply') }}</button>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($this->canEdit())
                                        <button type="button" wire:click="toggleAuto({{ $r->id_bop_rule }})" title="{{ t('Suggestion is applied automatically at the start of each month') }}"
                                            class="mb-1 px-1.5 py-0.5 rounded text-[10px] font-bold cursor-pointer {{ $r->auto_apply ? 'bg-[#eaf7ee] text-[#1f6b3c] border border-[#bfe6cc]' : 'bg-[#eef0f3] text-[#8a8f98] border border-[#dfe3e8]' }}">
                                            {{ $r->auto_apply ? t('AUTO ON') : t('AUTO OFF') }}</button>
                                    @endif
                                    @if ($r->material_pattern)
                                        <span class="font-mono">{{ $r->material_pattern }}</span>
                                        <div class="text-muted">{{ $r->material_role }} · {{ isset(self::MATERIAL_RULES[$r->material_rule]) ? t(self::MATERIAL_RULES[$r->material_rule]) : '' }}</div>
                                        @if ($r->usage_basis)
                                            <div class="text-muted">{{ isset(self::USAGE[$r->usage_basis]) ? t(self::USAGE[$r->usage_basis]) : $r->usage_basis }}{{ $r->usage_rate !== null ? ': '.(float) $r->usage_rate : '' }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $r->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $r->activate ? t('ACTIVE') : t('INACTIVE') }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($this->canEdit())
                                        <x-grid.action-icon icon="fa-chevron-up" :title="t('Move up')" action="move({{ $r->id_bop_rule }}, -1)" />
                                        <x-grid.action-icon icon="fa-chevron-down" :title="t('Move down')" action="move({{ $r->id_bop_rule }}, 1)" />
                                        <x-grid.action-icon icon="fa-pen" :title="t('Edit')" action="openForm({{ $r->id_bop_rule }})" />
                                        <x-grid.action-icon :active="$r->activate" :title="$r->activate ? t('Deactivate') : t('Activate')" action="toggleActive({{ $r->id_bop_rule }})"
                                            :confirm="str_replace(':name', $r->step_label ?: $r->material_role, $r->activate ? t('Deactivate rule :name?') : t('Activate rule :name?'))"
                                            :variant="$r->activate ? 'danger' : 'default'" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="8" :message="t('No BOP rules yet.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
        @endif

        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">{{ $editingId ? t('Edit BOP Rule') : t('New BOP Rule') }}</div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-form.select :label="t('Rule Kind').' *'" name="rule_scope" wire:model.live="rule_scope"
                        :options="['STANDARD' => t('Standard Steps'), 'FINISHING' => t('Finishing Inserts')]" />
                    @if ($rule_scope === 'FINISHING')
                        <x-form.select :label="t('Finishing').' *'" name="finishing_type_id" wire:model="finishing_type_id"
                            :placeholder="t('— select finishing —')" :options="$this->finishingOptions->all()" />
                    @else
                        <x-form.select :selected="$condition_code" :liveSync="true" :label="t('Condition')" name="condition_code" wire:model="condition_code"
                            :placeholder="t('Always used')" :options="array_map('t', self::CONDITIONS)" />
                    @endif
                    <x-form.select :label="t('Part Type Only')" name="part_type" wire:model="part_type"
                        :placeholder="t('All part types')" :options="['DC' => 'DC', 'DC+SHEET' => 'DC+SHEET', 'DC+SF' => 'DC+SF', 'SHEET' => 'SHEET', 'KRAFT' => 'KRAFT']" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-form.input :label="t('Step Order').' *'" name="step_seq" type="number" wire:model="step_seq" />
                    <x-form.select :label="t('Process (empty = material rule only)')" name="process_id" wire:model="process_id"
                        :placeholder="t('— no process —')" :options="$this->processOptions->all()" />
                    <x-form.select :selected="$machine_role" :liveSync="true" :label="t('Automatic Machine')" name="machine_role" wire:model="machine_role"
                        :placeholder="t('No machine')" :options="array_map('t', self::ROLES)" />
                </div>
                <x-form.input :label="t('Label in Routing')" name="step_label" wire:model="step_label"
                    :placeholder="t('e.g. Laminating Doff (Plastik {roll} cm)')" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-form.input :label="t('Default waste (%)')" name="waste_pct" type="number" step="0.1" wire:model="waste_pct" />
                    <x-form.input :label="t('Default tolerance (%)')" name="tolerance_pct" type="number" step="0.1" wire:model="tolerance_pct" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-form.input :label="t('Merge Group')" name="merge_group" wire:model="merge_group" :placeholder="t('e.g. PLONG (empty = not merged)')" />
                    <x-form.input :label="t('Joining Text when Merged')" name="merge_label" wire:model="merge_label" :placeholder="t('e.g. + Emboss')" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-form.input :label="t('Material Pattern (mat_raw, separate with |)')" name="material_pattern" wire:model="material_pattern" :placeholder="t('e.g. PLASTIKLAMDOFF%')" />
                    <x-form.select :selected="$material_rule" :liveSync="true" :label="t('Material Selection Method')" name="material_rule" wire:model="material_rule"
                        :placeholder="t('No material')" :options="array_map('t', self::MATERIAL_RULES)" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-form.input :label="t('BOM Line Name')" name="material_role" wire:model="material_role" :placeholder="t('e.g. LEM LAMINASI')" />
                    <x-form.select :selected="$usage_basis" :liveSync="true" :label="t('Usage Basis')" name="usage_basis" wire:model="usage_basis"
                        :placeholder="t('Not calculated')" :options="array_map('t', self::USAGE)" />
                    <x-form.input :label="t('Usage Rate')" name="usage_rate" type="number" step="any" wire:model="usage_rate" :placeholder="t('per the usage basis')" />
                </div>
                <x-form.input :label="t('Notes')" name="note" wire:model="note" />
                <x-form.checkbox :label="t('Active')" wire:model="rule_activate" />

                <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                    <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                </div>
            </form>
        @endif
    </div>
</div>
