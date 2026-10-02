<?php

use App\Services\Data\DataOriginService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Setting Data Dummy & Legacy (BACKLOG §18.7, user 2026-09-17): admin
// menentukan data DUMMY / LEGACY ditampilkan atau disembunyikan, mengatur
// aturan klasifikasi, dan me-reset data dummy ke keadaan semula (snapshot)
// setelah UAT. Menyembunyikan != menghapus. Route dijaga middleware admin.
new #[Title('Dummy & Legacy Data — TELASIH MAKMUR ERP')] class extends Component
{
    public bool $showDummy = true;

    public bool $showLegacy = true;

    public bool $newAsDummy = false;

    public bool $hideIncomplete = false;

    public ?array $promotePreview = null;

    public bool $reqForm = false;

    public string $req_db = 'tm2mst';

    public string $req_table = '';

    public string $req_column = '';

    public string $req_label = '';

    public string $flash = '';

    public string $dbFilter = '';

    public ?array $classifyPreview = null;

    public ?array $resetPreview = null;

    public bool $removeNew = false;

    // Form aturan
    public ?int $ruleId = null;

    public bool $ruleForm = false;

    public string $match_type = 'CREATED_BY';

    public string $db_name = '';

    public string $table_name = '';

    public string $column_name = '';

    public string $match_value = '';

    public string $origin = 'DUMMY';

    public $priority = 100;

    public string $note = '';

    public const TYPES = [
        'CREATED_BY' => 'Creator (created_by) matches pattern',
        'COLUMN_LIKE' => 'Column value matches pattern',
        'COLUMN_FILLED' => 'Column filled',
    ];

    public function mount(): void
    {
        $svc = app(DataOriginService::class);
        $hidden = $svc->hiddenOrigins();
        $this->showDummy = ! in_array('DUMMY', $hidden, true);
        $this->showLegacy = ! in_array('LEGACY', $hidden, true);
        $this->newAsDummy = $svc->newRowsAsDummy();
        $this->hideIncomplete = $svc->hideLegacyIncomplete();
    }

    private function svc(): DataOriginService
    {
        return app(DataOriginService::class);
    }

    #[Computed]
    public function counts(): array
    {
        return $this->svc()->counts();
    }

    #[Computed]
    public function totals(): array
    {
        $t = ['LIVE' => 0, 'DUMMY' => 0, 'LEGACY' => 0];
        foreach ($this->counts as $c) {
            foreach ($t as $k => $v) {
                $t[$k] += $c[$k];
            }
        }

        return $t;
    }

    #[Computed]
    public function marked(): array
    {
        return collect($this->counts)
            ->filter(fn ($c, $k) => ($c['DUMMY'] || $c['LEGACY']) && ($this->dbFilter === '' || str_starts_with($k, $this->dbFilter.'.')))
            ->sortByDesc(fn ($c) => $c['DUMMY'] + $c['LEGACY'])
            ->all();
    }

    #[Computed]
    public function rules()
    {
        return DB::connection('tm2sys')->table('sys_data_origin_rule')->orderBy('priority')->orderBy('id_rule')->get();
    }

    #[Computed]
    public function snapshot(): array
    {
        return $this->svc()->snapshotInfo();
    }

    public function saveSettings(): void
    {
        foreach (['DATA_SHOW_DUMMY' => $this->showDummy, 'DATA_SHOW_LEGACY' => $this->showLegacy,
            'DATA_NEW_AS_DUMMY' => $this->newAsDummy, 'DATA_HIDE_LEGACY_INCOMPLETE' => $this->hideIncomplete] as $key => $val) {
            DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->update([
                'constant_number' => $val ? 1 : 0, 'updated_by' => Auth::user()->login, 'updated_at' => now(),
            ]);
        }

        $this->flash = t('Settings saved — applies to pages opened after this.');
    }

    // ---------- aturan ----------

    public function openRule(?int $id = null): void
    {
        $r = $id ? DB::connection('tm2sys')->table('sys_data_origin_rule')->where('id_rule', $id)->first() : null;
        $this->ruleId = $id;
        $this->match_type = $r->match_type ?? 'CREATED_BY';
        $this->db_name = $r->db_name ?? '';
        $this->table_name = $r->table_name ?? '';
        $this->column_name = $r->column_name ?? '';
        $this->match_value = $r->match_value ?? '';
        $this->origin = $r->origin ?? 'DUMMY';
        $this->priority = $r->priority ?? ((int) $this->rules->max('priority') + 10);
        $this->note = $r->note ?? '';
        $this->resetErrorBag();
        $this->ruleForm = true;
    }

    public function saveRule(): void
    {
        $data = $this->validate([
            'match_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'db_name' => ['nullable', 'in:'.implode(',', DataOriginService::DATABASES)],
            'table_name' => ['nullable', 'regex:/^\w+$/', 'max:64'],
            'column_name' => ['nullable', 'required_unless:match_type,CREATED_BY', 'regex:/^\w+$/', 'max:64'],
            'match_value' => ['nullable', 'required_unless:match_type,COLUMN_FILLED', 'string', 'max:100'],
            'origin' => ['required', 'in:DUMMY,LEGACY'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $blank = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;
        $payload = [
            'match_type' => $data['match_type'],
            'db_name' => $blank($data['db_name']),
            'table_name' => $blank($data['table_name']),
            'column_name' => $data['match_type'] === 'CREATED_BY' ? null : $blank($data['column_name']),
            'match_value' => $data['match_type'] === 'COLUMN_FILLED' ? null : $blank($data['match_value']),
            'origin' => $data['origin'],
            'priority' => (int) $data['priority'],
            'note' => $blank($data['note']),
            'updated_at' => now(),
            'updated_by' => Auth::user()->login,
        ];

        $t = DB::connection('tm2sys')->table('sys_data_origin_rule');
        $this->ruleId
            ? $t->where('id_rule', $this->ruleId)->update($payload)
            : $t->insert($payload + ['activate' => 1, 'created_at' => now(), 'created_by' => Auth::user()->login]);

        $this->ruleForm = false;
        $this->classifyPreview = null;
        unset($this->rules);
    }

    public function toggleRule(int $id): void
    {
        DB::connection('tm2sys')->table('sys_data_origin_rule')->where('id_rule', $id)->update([
            'activate' => DB::raw('1 - activate'), 'updated_at' => now(), 'updated_by' => Auth::user()->login,
        ]);
        $this->classifyPreview = null;
        unset($this->rules);
    }

    public function previewClassify(): void
    {
        $this->classifyPreview = $this->svc()->classify(true);
    }

    public function applyClassify(): void
    {
        $done = $this->svc()->classify(false);
        $n = array_sum(array_map(fn ($c) => $c['DUMMY'] + $c['LEGACY'], $done));
        $this->classifyPreview = null;
        $this->flash = str_replace(':n', $n, t('Classification applied: :n rows marked. Take a new snapshot if DUMMY data grows.'));
        unset($this->counts, $this->totals, $this->marked);
    }

    // ---------- kelengkapan data legacy ----------

    #[Computed]
    public function requirements()
    {
        return DB::connection('tm2sys')->table('sys_data_requirement')->orderBy('db_name')->orderBy('table_name')->get();
    }

    #[Computed]
    public function readiness(): array
    {
        return $this->svc()->readiness();
    }

    public function saveRequirement(): void
    {
        $data = $this->validate([
            'req_db' => ['required', 'in:'.implode(',', DataOriginService::DATABASES)],
            'req_table' => ['required', 'regex:/^\w+$/', 'max:64'],
            'req_column' => ['required', 'regex:/^\w+$/', 'max:64'],
            'req_label' => ['required', 'string', 'max:100'],
        ]);

        DB::connection('tm2sys')->table('sys_data_requirement')->updateOrInsert(
            ['db_name' => $data['req_db'], 'table_name' => $data['req_table'], 'column_name' => $data['req_column']],
            ['label' => $data['req_label'], 'activate' => 1, 'created_at' => now(), 'updated_at' => now(),
                'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login],
        );

        $this->reqForm = false;
        $this->req_table = $this->req_column = $this->req_label = '';
        $this->svc()->computeReady(false);
        unset($this->requirements, $this->readiness);
        $this->flash = t('Requirement saved and data completeness recalculated.');
    }

    public function toggleRequirement(int $id): void
    {
        DB::connection('tm2sys')->table('sys_data_requirement')->where('id_requirement', $id)->update([
            'activate' => DB::raw('1 - activate'), 'updated_at' => now(), 'updated_by' => Auth::user()->login,
        ]);
        $this->svc()->computeReady(false);
        unset($this->requirements, $this->readiness);
    }

    public function recomputeReady(): void
    {
        $r = $this->svc()->computeReady(false);
        $n = array_sum(array_column($r, 'incomplete'));
        unset($this->readiness);
        $this->flash = str_replace(':n', $n, t('Completeness recalculated: :n rows do not meet the requirements yet.'));
    }

    public function previewPromote(): void
    {
        $this->promotePreview = $this->svc()->promoteReadyLegacy(true);
    }

    public function applyPromote(?string $only = null): void
    {
        $done = $this->svc()->promoteReadyLegacy(false, $only);
        $n = array_sum($done);
        $this->promotePreview = null;
        $this->flash = str_replace(':n', $n, t(':n complete LEGACY rows turned into real data (LIVE).'));
        unset($this->counts, $this->totals, $this->marked, $this->readiness);
    }

    // ---------- snapshot & reset ----------

    public function takeSnapshot(): void
    {
        $n = $this->svc()->snapshot(Auth::user()->login);
        $this->resetPreview = null;
        $this->flash = str_replace(':n', $n, t('Snapshot saved: :n DUMMY rows become the original state for reset.'));
        unset($this->snapshot);
    }

    public function previewReset(): void
    {
        $this->resetPreview = $this->svc()->reset(true, $this->removeNew);
    }

    public function applyReset(): void
    {
        $r = $this->svc()->reset(false, $this->removeNew);
        $this->resetPreview = null;
        $this->flash = str_replace([':restored', ':changed'], [$r['restored'], $r['changed']], t('Reset done: :restored rows returned, :changed rows had their content restored'))
            .($this->removeNew ? str_replace(':new', $r['new'], t(', :new new dummy rows deleted.')) : '.');
        Cache::forget('data_origin.tables');
        unset($this->counts, $this->totals, $this->marked);
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4 flex flex-col gap-4">

        @if ($flash)
            <div class="px-4 py-2.5 rounded-md bg-[#eaf7ee] border border-[#bfe6cc] text-[#1f6b3c] text-[12.5px]">{{ $flash }}</div>
        @endif

        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="active" :label="t('LIVE rows')" :value="number_format($this->totals['LIVE'], 0, ',', '.')" />
            <x-grid.stat-box tone="warn" :label="t('DUMMY rows')" :value="number_format($this->totals['DUMMY'], 0, ',', '.')" />
            <x-grid.stat-box tone="total" :label="t('LEGACY rows')" :value="number_format($this->totals['LEGACY'], 0, ',', '.')" />
        </x-grid.stat-row>

        {{-- 1. Setting tampilan --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4">
            <div class="text-sm font-bold text-navy mb-1">{{ t('Data Display') }}</div>
            <div class="text-[12px] text-muted mb-3">{{ t('Hiding does not delete data. Applies to every screen that reads through the application models.') }}</div>
            <div class="flex flex-col gap-2">
                <x-form.checkbox :label="t('Show DUMMY data (demo / UAT)')" wire:model="showDummy" />
                <x-form.checkbox :label="t('Show LEGACY data (old system migration)')" wire:model.live="showLegacy" />
                @unless ($showLegacy)
                    <div class="text-[12px] px-3 py-2 rounded bg-[#fff4e0] border border-[#f1d39a] text-[#8a5a00]">
                        ⚠ {{ str_replace(':n', number_format($this->totals['LEGACY'], 0, ',', '.'), t('Hiding LEGACY will hide :n rows — including most of the imported Material Master, Customers, Suppliers, Products and employees.')) }}
                        {{ t('Make sure working data no longer depends on that data.') }}
                    </div>
                @endunless
                <x-form.checkbox :label="t('Hide LEGACY data that does not meet the mandatory requirements yet')" wire:model="hideIncomplete" />
                <x-form.checkbox :label="t('UAT mode: new data automatically marked DUMMY (included in reset)')" wire:model="newAsDummy" />
            </div>
            <div class="mt-3">
                <button type="button" wire:click="saveSettings" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
            </div>
        </div>

        {{-- 2. Kelengkapan data legacy --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4">
            <div class="text-sm font-bold text-navy mb-1">{{ t('Legacy Data Completeness') }}</div>
            <div class="text-[12px] text-muted mb-3">
                {{ t('LEGACY data that meets the mandatory requirements may be turned into real data (LIVE).') }}
                {{ t('The rest stays LEGACY so it is visible that it still needs completing. Tables without mandatory requirements are considered compliant.') }}
            </div>

            <table class="w-full border-collapse text-[12px] mb-3">
                <tr class="text-left text-[11px] text-muted uppercase"><th class="py-1">{{ t('Table') }}</th><th>{{ t('Mandatory requirements') }}</th><th class="text-right">{{ t('Legacy complete') }}</th><th class="text-right">{{ t('Legacy incomplete') }}</th><th class="text-right">{{ t('LIVE incomplete') }}</th></tr>
                @forelse ($this->readiness as $tbl => $r)
                    <tr class="border-t border-[#eef1f5]">
                        <td class="py-1 font-mono text-[11.5px]">{{ $tbl }}</td>
                        <td class="text-[11.5px]">{{ $this->requirements->filter(fn ($q) => $q->db_name.'.'.$q->table_name === $tbl && $q->activate)->pluck('label')->implode(', ') }}</td>
                        <td class="text-right font-bold text-[#1f8a4c]">{{ number_format($r['LEGACY_READY'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold text-[#b45309]">{{ number_format($r['LEGACY_INCOMPLETE'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($r['LIVE_INCOMPLETE'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-muted text-center">{{ t('No mandatory requirements registered yet.') }}</td></tr>
                @endforelse
            </table>

            <div class="flex flex-wrap gap-2 items-center">
                <button type="button" wire:click="recomputeReady" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">↻ {{ t('Recalculate Completeness') }}</button>
                <button type="button" wire:click="previewPromote" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">🔍 {{ t('Preview Make Real') }}</button>
                <button type="button" wire:click="$set('reqForm', true)" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">+ {{ t('Mandatory Requirement') }}</button>
            </div>

            @if ($reqForm)
                <form wire:submit="saveRequirement" class="mt-3 p-3 bg-[#fafbfd] border border-[#eef1f5] rounded grid grid-cols-1 md:grid-cols-5 gap-3">
                    <x-form.select :label="t('Database').' *'" name="req_db" wire:model="req_db" :selected="$req_db" :liveSync="true"
                        :options="array_combine(DataOriginService::DATABASES, DataOriginService::DATABASES)" />
                    <x-form.input :label="t('Table').' *'" name="req_table" wire:model="req_table" :placeholder="t('e.g.').' prod_product'" />
                    <x-form.input :label="t('Mandatory column').' *'" name="req_column" wire:model="req_column" :placeholder="t('e.g.').' customer_id'" />
                    <x-form.input :label="t('Requirement name').' *'" name="req_label" wire:model="req_label" :placeholder="t('e.g. Customer ownership')" />
                    <div class="flex items-end gap-2">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                        <button type="button" wire:click="$set('reqForm', false)" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                    </div>
                </form>
            @endif

            @if ($this->requirements->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($this->requirements as $q)
                        <button type="button" wire:click="toggleRequirement({{ $q->id_requirement }})"
                            class="px-2.5 py-1 rounded-full text-[11px] border cursor-pointer {{ $q->activate ? 'bg-[#eaf7ee] border-[#bfe6cc] text-[#1f6b3c]' : 'bg-[#eef0f3] border-[#dfe3e8] text-[#8a8f98] line-through' }}"
                            title="{{ t('Click to activate/deactivate') }}">{{ $q->table_name }}.{{ $q->column_name }} — {{ $q->label }}</button>
                    @endforeach
                </div>
            @endif

            @if ($promotePreview !== null)
                <div class="mt-3 text-[12px]">
                    @if ($promotePreview)
                        {{ t('Will be made LIVE (legacy that is already complete):') }}
                        <table class="w-full mt-2 border-collapse">
                            <tr class="text-left text-[11px] text-muted uppercase"><th class="py-1">{{ t('Table') }}</th><th class="text-right">{{ t('Rows') }}</th><th></th></tr>
                            @foreach ($promotePreview as $tbl => $n)
                                <tr class="border-t border-[#eef1f5]">
                                    <td class="py-1 font-mono text-[11.5px]">{{ $tbl }}</td>
                                    <td class="text-right">{{ number_format($n, 0, ',', '.') }}</td>
                                    <td class="text-right">
                                        <button type="button" wire:click="applyPromote('{{ $tbl }}')" wire:confirm="{{ str_replace([':n', ':table'], [$n, $tbl], t('Make :n rows of :table real data (LIVE)?')) }}"
                                            class="px-2 py-1 rounded text-[11px] font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Make Real') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                        <button type="button" wire:click="applyPromote" wire:confirm="{{ t('Make ALL complete legacy data real data (LIVE)?') }}"
                            class="mt-3 px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Make Real (all tables)') }}</button>
                    @else
                        <span class="text-[#1f6b3c]">{{ t('No complete LEGACY rows need promoting.') }}</span>
                    @endif
                </div>
            @endif
        </div>

        {{-- 3. Snapshot & reset --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4">
            <div class="text-sm font-bold text-navy mb-1">{{ t('Reset Dummy Data') }}</div>
            <div class="text-[12px] text-muted mb-3">
                {{ t('Snapshot = original state of DUMMY data. Reset returns dummy rows deleted or changed during UAT to the snapshot content.') }}
                {{ t('Last snapshot:') }} <b>{{ number_format($this->snapshot['rows'], 0, ',', '.') }}</b> {{ t('rows') }}
                @if ($this->snapshot['at']) · {{ $this->snapshot['at'] }} {{ t('by') }} {{ $this->snapshot['by'] }} @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="takeSnapshot" wire:confirm="{{ t('Replace the snapshot with the current state of DUMMY data?') }}"
                    class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">📸 {{ t('Retake Snapshot') }}</button>
                <button type="button" wire:click="previewReset" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">🔍 {{ t('Preview Reset') }}</button>
                <label class="flex items-center gap-1.5 text-[12px] ml-2">
                    <input type="checkbox" wire:model.live="removeNew"> {{ t('Also delete DUMMY data created after the snapshot') }}
                </label>
            </div>
            @if ($resetPreview)
                <div class="mt-3 text-[12px]">
                    {{ t('Will be returned:') }} <b>{{ $resetPreview['restored'] }}</b> {{ t('rows missing/changed flag,') }}
                    <b>{{ $resetPreview['changed'] }}</b> {{ t('rows changed content,') }}
                    {{ t('new dummy data:') }} <b>{{ $resetPreview['new'] }}</b>{{ ' '.($removeNew ? t('(will be deleted)') : t('(left as is)')) }}.
                    @if ($resetPreview['tables'])
                        <table class="w-full mt-2 border-collapse text-[12px]">
                            <tr class="text-left text-[11px] text-muted uppercase"><th class="py-1">{{ t('Table') }}</th><th>{{ t('Returned') }}</th><th>{{ t('Changed') }}</th><th>{{ t('New') }}</th></tr>
                            @foreach ($resetPreview['tables'] as $tbl => $r)
                                <tr class="border-t border-[#eef1f5]"><td class="py-1 font-mono">{{ $tbl }}</td><td>{{ $r['restored'] }}</td><td>{{ $r['changed'] }}</td><td>{{ $r['new'] }}</td></tr>
                            @endforeach
                        </table>
                        <button type="button" wire:click="applyReset" wire:confirm="{{ t('Reset DUMMY data to the snapshot state now?') }}"
                            class="mt-3 px-4 py-2 rounded text-xs font-semibold bg-[#b45309] text-white cursor-pointer">↺ {{ t('Reset Now') }}</button>
                    @else
                        <div class="mt-2 text-[#1f6b3c]">{{ t('Dummy data already matches the snapshot — nothing needs resetting.') }}</div>
                    @endif
                </div>
            @endif
        </div>

        {{-- 4. Aturan klasifikasi --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="text-sm font-bold text-navy">{{ t('Classification Rules') }}</div>
                <div class="text-[11.5px] text-muted">{{ t('Only raises LIVE → DUMMY/LEGACY. Children of DUMMY rows (relations) also become DUMMY.') }}</div>
                <x-slot:newRecord>
                    <div class="flex gap-1.5">
                        <button type="button" wire:click="previewClassify" class="px-3 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">🔍 {{ t('Preview') }}</button>
                        <button type="button" wire:click="openRule" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    </div>
                </x-slot:newRecord>
            </x-grid.toolbar>

            @if ($ruleForm)
                <form wire:submit="saveRule" class="p-4 bg-[#fafbfd] border-b border-[#eef1f5] grid grid-cols-1 md:grid-cols-4 gap-3">
                    <x-form.select :selected="$match_type" :liveSync="true" :label="t('Type').' *'" name="match_type" wire:model.live="match_type" :options="array_map('t', self::TYPES)" />
                    <x-form.select :selected="$db_name" :liveSync="true" :label="t('Database')" name="db_name" wire:model="db_name" :placeholder="t('All databases')"
                        :options="array_combine(DataOriginService::DATABASES, DataOriginService::DATABASES)" />
                    <x-form.input :label="t('Table')" name="table_name" wire:model="table_name" :placeholder="t('empty = all tables')" />
                    @if ($match_type !== 'CREATED_BY')
                        <x-form.input :label="t('Column').' *'" name="column_name" wire:model="column_name" :placeholder="t('e.g.').' inquiry_no'" />
                    @endif
                    @if ($match_type !== 'COLUMN_FILLED')
                        <x-form.input :label="t('Pattern (LIKE, % = any)').' *'" name="match_value" wire:model="match_value" :placeholder="t('e.g.').' IQ2690%'" />
                    @endif
                    <x-form.select :label="t('Mark as').' *'" name="origin" wire:model="origin" :options="['DUMMY' => 'DUMMY', 'LEGACY' => 'LEGACY']" />
                    <x-form.input :label="t('Priority')" name="priority" type="number" wire:model="priority" />
                    <x-form.input :label="t('Notes')" name="note" wire:model="note" />
                    <div class="md:col-span-4 flex gap-2">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                        <button type="button" wire:click="$set('ruleForm', false)" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                    </div>
                </form>
            @endif

            @if ($classifyPreview !== null)
                <div class="p-4 border-b border-[#eef1f5] text-[12px]">
                    @if ($classifyPreview)
                        {{ t('Will be marked:') }}
                        @foreach ($classifyPreview as $tbl => $c)
                            <span class="inline-block mr-3"><span class="font-mono">{{ $tbl }}</span> · D {{ $c['DUMMY'] }} · L {{ $c['LEGACY'] }}</span>
                        @endforeach
                        <div class="mt-2">
                            <button type="button" wire:click="applyClassify" wire:confirm="{{ t('Apply this classification?') }}"
                                class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Apply') }}</button>
                        </div>
                    @else
                        <span class="text-[#1f6b3c]">{{ t('No LIVE rows match the rules — everything is already classified.') }}</span>
                    @endif
                </div>
            @endif

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr class="[&>th]:text-left [&>th]:px-3 [&>th]:py-2 [&>th]:bg-[#fafbfd] [&>th]:text-[#667] [&>th]:text-[11px] [&>th]:uppercase [&>th]:border-b [&>th]:border-[#eef1f5]">
                            <th class="w-[60px]">{{ t('Prio') }}</th><th>{{ t('Type') }}</th><th>{{ t('Scope') }}</th><th>{{ t('Pattern') }}</th><th class="w-[90px]">{{ t('Mark') }}</th><th>{{ t('Notes') }}</th><th class="w-[80px]">{{ t('Status') }}</th><th class="w-[90px] !text-right">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rules as $r)
                            <tr wire:key="rule-{{ $r->id_rule }}" wire:dblclick="openRule({{ $r->id_rule }})"
                                class="hover:bg-[#f7faff] cursor-pointer [&>td]:px-3 [&>td]:py-2 [&>td]:border-b [&>td]:border-[#eef1f5] {{ $r->activate ? '' : 'opacity-50' }}">
                                <td class="font-mono">{{ $r->priority }}</td>
                                <td>{{ isset(self::TYPES[$r->match_type]) ? t(self::TYPES[$r->match_type]) : $r->match_type }}</td>
                                <td class="font-mono text-[11.5px]">{{ $r->db_name ?: '*' }}.{{ $r->table_name ?: '*' }}{{ $r->column_name ? '.'.$r->column_name : '' }}</td>
                                <td class="font-mono text-[11.5px]">{{ $r->match_value ?? t('(filled)') }}</td>
                                <td><span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->origin === 'DUMMY' ? 'bg-[#fff4e0] text-[#b45309]' : 'bg-[#e8eefb] text-[#2c3e69]' }}">{{ $r->origin }}</span></td>
                                <td class="text-[11.5px] text-muted">{{ $r->note }}</td>
                                <td><span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $r->activate ? t('ACTIVE') : t('INACTIVE') }}</span></td>
                                <td class="text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" :title="t('Edit')" action="openRule({{ $r->id_rule }})" />
                                    <x-grid.action-icon :active="$r->activate" :title="$r->activate ? t('Deactivate') : t('Activate')" action="toggleRule({{ $r->id_rule }})"
                                        :variant="$r->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>

        {{-- 5. Ringkasan per tabel --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="text-sm font-bold text-navy">{{ t('Tables Containing DUMMY / LEGACY Data') }}</div>
                <select wire:model.live="dbFilter" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="">{{ t('All databases') }}</option>
                    @foreach (DataOriginService::DATABASES as $d)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>
            </x-grid.toolbar>
            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr class="[&>th]:text-left [&>th]:px-3 [&>th]:py-2 [&>th]:bg-[#fafbfd] [&>th]:text-[#667] [&>th]:text-[11px] [&>th]:uppercase [&>th]:border-b [&>th]:border-[#eef1f5]">
                            <th>{{ t('Table') }}</th><th class="!text-right">LIVE</th><th class="!text-right">DUMMY</th><th class="!text-right">LEGACY</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->marked as $tbl => $c)
                            <tr class="[&>td]:px-3 [&>td]:py-1.5 [&>td]:border-b [&>td]:border-[#eef1f5]">
                                <td class="font-mono text-[11.5px]">{{ $tbl }}</td>
                                <td class="text-right">{{ number_format($c['LIVE'], 0, ',', '.') }}</td>
                                <td class="text-right {{ $c['DUMMY'] ? 'font-bold text-[#b45309]' : 'text-muted' }}">{{ number_format($c['DUMMY'], 0, ',', '.') }}</td>
                                <td class="text-right {{ $c['LEGACY'] ? 'font-bold text-[#2c3e69]' : 'text-muted' }}">{{ number_format($c['LEGACY'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="4" :message="t('No DUMMY / LEGACY data.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
