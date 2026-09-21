<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinAssetDepr;
use App\Models\Acc\FinCostCenter;
use App\Services\Acc\AssetService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Fixed Assets + Assets/Amortization Flow (mockup blueprint):
// Identify -> Set Policy -> Capitalize -> Run Schedule -> Post Journal.
// Layar ini menangani aset tetap (asset_type FIXED); prabayar & tak berwujud
// ada di layar Amortization -- engine & tabelnya sama (AssetService).
new #[Title('Fixed Assets — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    private const TYPES = ['FIXED'];

    public string $search = '';

    public string $categoryFilter = '';

    public string $run_period = '';

    public ?int $editingId = null;

    public ?int $viewId = null;

    public string $asset_name = '';

    public string $category_id = '';

    public string $acq_date = '';

    public string $start_date = '';

    public string $cost = '';

    public string $residual = '0';

    public string $life = '';

    public string $method = 'SL';

    public string $cc_id = '';

    public string $location = '';

    public string $source_ref = '';

    public string $credit_account_id = '';

    public string $disp_date = '';

    public string $disp_amount = '0';

    public string $disp_bank = '';

    public function mount(): void
    {
        $this->run_period = now()->format('Y-m');
    }

    #[Computed]
    public function categories()
    {
        return FinAssetCategory::whereIn('asset_type', self::TYPES)->where('activate', true)->orderBy('category_code')->get();
    }

    #[Computed]
    public function assets()
    {
        return FinAsset::with('category')
            ->whereIn('category_id', FinAssetCategory::whereIn('asset_type', self::TYPES)->select('category_id'))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('asset_name', 'like', "%{$this->search}%")->orWhere('asset_code', 'like', "%{$this->search}%")->orWhere('location', 'like', "%{$this->search}%")))
            ->orderByDesc('acquisition_date')->orderByDesc('asset_id')->limit(300)->get();
    }

    #[Computed]
    public function accumulated(): array
    {
        return FinAssetDepr::whereIn('asset_id', $this->assets->pluck('asset_id'))
            ->selectRaw('asset_id, SUM(amount) total')->groupBy('asset_id')->pluck('total', 'asset_id')->all();
    }

    #[Computed]
    public function stats(): array
    {
        $svc = app(AssetService::class);
        $active = $this->assets->whereNotIn('status', ['DISPOSED']);
        $cost = $active->sum(fn ($a) => (float) $a->acquisition_cost);
        $acc = collect($this->accumulated)->only($active->pluck('asset_id'))->sum();

        return [
            'cost' => $cost, 'accum' => $acc, 'nbv' => round($cost - $acc, 2),
            'count' => $active->count(),
            'review' => $active->filter(fn ($a) => $a->status === 'DRAFT' || ($a->next_review_date && $a->next_review_date->lte(today())))->count(),
            'monthly' => $svc->preview((int) substr($this->run_period, 0, 4), (int) substr($this->run_period, 5, 2), self::TYPES)->sum('amount'),
            'pending' => $svc->preview((int) substr($this->run_period, 0, 4), (int) substr($this->run_period, 5, 2), self::TYPES)->count(),
        ];
    }

    #[Computed]
    public function preview()
    {
        return app(AssetService::class)->preview((int) substr($this->run_period, 0, 4), (int) substr($this->run_period, 5, 2), self::TYPES);
    }

    #[Computed]
    public function viewing(): ?FinAsset
    {
        return $this->viewId ? FinAsset::with('category')->find($this->viewId) : null;
    }

    #[Computed]
    public function schedule(): array
    {
        return $this->viewing ? app(AssetService::class)->schedule($this->viewing) : [];
    }

    #[Computed]
    public function ccOptions(): array
    {
        return FinCostCenter::where('activate', true)->orderBy('cc_code')->pluck('cc_name', 'cc_id')->all();
    }

    #[Computed]
    public function creditOptions(): array
    {
        return FinAccount::where('is_postable', true)
            ->where(fn ($q) => $q->where('account_code', 'like', '111%')->orWhere('account_code', 'like', '211%')->orWhere('account_code', 'like', '215%'))
            ->orderBy('account_code')->get()->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    #[Computed]
    public function periodOptions(): array
    {
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $d = now()->startOfMonth()->subMonths($i);
            $out[$d->format('Y-m')] = $d->format('F Y');
        }

        return $out;
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'asset_name', 'cost', 'location', 'source_ref']);
        $this->residual = '0';
        $this->acq_date = now()->toDateString();
        $this->start_date = now()->startOfMonth()->toDateString();
        $this->category_id = (string) $this->categories->first()?->category_id;
        $this->applyCategoryDefaults();
        $this->credit_account_id = (string) FinAccount::where('account_code', '211900')->value('account_id');
        $this->modal = 'form';
    }

    public function updatedCategoryId(): void
    {
        $this->applyCategoryDefaults();
    }

    private function applyCategoryDefaults(): void
    {
        $c = FinAssetCategory::find($this->category_id);
        $this->life = (string) ($c?->useful_life_months ?: 48);
        $this->method = $c?->method ?: 'SL';
    }

    public function edit(int $id): void
    {
        $a = FinAsset::findOrFail($id);
        if ($a->status !== 'DRAFT') {
            $this->openView($id);

            return;
        }
        $this->editingId = $a->asset_id;
        $this->asset_name = $a->asset_name;
        $this->category_id = (string) $a->category_id;
        $this->acq_date = $a->acquisition_date->toDateString();
        $this->start_date = $a->depr_start_date->toDateString();
        $this->cost = (string) (float) $a->acquisition_cost;
        $this->residual = (string) (float) $a->residual_value;
        $this->life = (string) $a->useful_life_months;
        $this->method = $a->method;
        $this->cc_id = (string) $a->cc_id;
        $this->location = (string) $a->location;
        $this->source_ref = (string) $a->source_ref;
        $this->credit_account_id = (string) $a->credit_account_id;
        $this->modal = 'form';
    }

    public function openView(int $id): void
    {
        $this->viewId = $id;
        $this->modal = 'view';
    }

    public function save(bool $capitalize, AssetService $svc): void
    {
        $this->validate([
            'asset_name' => 'required|string|max:150',
            'category_id' => 'required|exists:tm2acc.fin_asset_category,category_id',
            'acq_date' => 'required|date',
            'start_date' => 'required|date',
            'cost' => 'required|numeric|min:1',
            'residual' => 'required|numeric|min:0|lt:cost',
            'life' => 'required|integer|min:1|max:600',
            'method' => 'required|in:SL,DDB',
            'credit_account_id' => 'required|exists:tm2acc.fin_account,account_id',
        ], [], ['asset_name' => 'Asset name', 'cost' => 'Acquisition cost', 'life' => 'Useful life (months)']);

        if ($this->attempt(function () use ($capitalize, $svc) {
            $data = [
                'asset_name' => $this->asset_name, 'category_id' => (int) $this->category_id,
                'acquisition_date' => $this->acq_date, 'depr_start_date' => $this->start_date,
                'acquisition_cost' => $this->cost, 'residual_value' => $this->residual,
                'useful_life_months' => (int) $this->life, 'method' => $this->method,
                'cc_id' => $this->cc_id ?: null, 'location' => $this->location ?: null,
                'source_ref' => $this->source_ref ?: null, 'credit_account_id' => (int) $this->credit_account_id,
                'next_review_date' => \Carbon\Carbon::parse($this->acq_date)->addYear()->toDateString(),
                'updated_by' => $this->login(),
            ];
            if ($this->editingId) {
                $asset = FinAsset::findOrFail($this->editingId);
                $asset->update($data);
            } else {
                $cat = FinAssetCategory::find($this->category_id);
                $asset = FinAsset::create($data + [
                    'asset_code' => $svc->nextCode('FA-'.$cat->category_code, $this->acq_date),
                    'status' => 'DRAFT', 'created_by' => $this->login(),
                ]);
            }
            if ($capitalize) {
                $svc->capitalize($asset, $this->login());
            }
        }, $capitalize ? 'Aset dikapitalisasi: Dr akun aset / Cr lawan, jadwal penyusutan aktif.' : 'Draft aset disimpan.')) {
            $this->closeModal();
            unset($this->assets, $this->stats, $this->accumulated, $this->preview);
        }
    }

    public function capitalize(int $id, AssetService $svc): void
    {
        $this->attempt(fn () => $svc->capitalize(FinAsset::findOrFail($id), $this->login()), 'Aset dikapitalisasi & mulai disusutkan.');
        unset($this->assets, $this->stats, $this->preview, $this->viewing);
    }

    public function runDepreciation(AssetService $svc): void
    {
        $id = null;
        if ($this->attempt(function () use ($svc, &$id) {
            $id = $svc->run((int) substr($this->run_period, 0, 4), (int) substr($this->run_period, 5, 2), $this->login(), self::TYPES);
        })) {
            session()->flash($id ? 'flash' : 'error', $id ? 'Jurnal penyusutan '.($this->periodOptions[$this->run_period] ?? '').' diposting.' : 'Tidak ada aset yang perlu disusutkan di periode ini.');
            $this->closeModal();
            unset($this->assets, $this->stats, $this->accumulated, $this->preview, $this->schedule);
        }
    }

    public function openDispose(int $id): void
    {
        $this->viewId = $id;
        $this->disp_date = now()->toDateString();
        $this->disp_amount = '0';
        $this->disp_bank = (string) FinAccount::where('account_code', '111200')->value('account_id');
        $this->modal = 'dispose';
    }

    public function dispose(AssetService $svc): void
    {
        $this->validate(['disp_date' => 'required|date', 'disp_amount' => 'required|numeric|min:0', 'disp_bank' => 'required|exists:tm2acc.fin_account,account_id']);
        if ($this->attempt(fn () => $svc->dispose(FinAsset::findOrFail($this->viewId), $this->disp_date, (float) $this->disp_amount, (int) $this->disp_bank, $this->login()), 'Aset dilepas; laba/rugi pelepasan masuk ke akun lain-lain.')) {
            $this->closeModal();
            unset($this->assets, $this->stats, $this->accumulated);
        }
    }

    public function export()
    {
        return $this->csvDownload('fixed-assets-'.now()->format('Ymd').'.csv',
            ['Asset code', 'Asset name', 'Category', 'Acquisition date', 'Acquisition cost', 'Accumulated depreciation', 'Net book value', 'Method', 'Life (months)', 'Status'],
            $this->assets->map(fn ($a) => [
                $a->asset_code, $a->asset_name, $a->category?->category_name, $a->acquisition_date->format('Y-m-d'),
                (float) $a->acquisition_cost, (float) ($this->accumulated[$a->asset_id] ?? 0),
                (float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0),
                $a->method, $a->useful_life_months, $a->status,
            ]));
    }

    public function statusTone(string $s): string
    {
        return ['DRAFT' => 'warn', 'ACTIVE' => 'good', 'FULLY_DEPRECIATED' => 'info', 'DISPOSED' => 'mute'][$s] ?? 'mute';
    }
};
?>

<x-ledger.page title="Fixed Assets" subtitle="Capitalization, asset register, depreciation schedules, and lifecycle activities.">
    <x-slot:actions>
        <select class="lg-period" wire:model.live="run_period" x-data x-effect="$el.value = @js($run_period)">
            @foreach ($this->periodOptions as $k => $label)<option value="{{ $k }}">▣ {{ $label }}</option>@endforeach
        </select>
        <button type="button" class="lg-btn light" wire:click="export">⇩ Export</button>
        <button type="button" class="lg-btn light" wire:click="$set('modal', 'run')">▶ Run Depreciation</button>
        <button type="button" class="lg-btn primary" wire:click="openNew">＋ Add Asset</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="Acquisition Cost" icon="▰" :value="Fmt::short($this->stats['cost'])" :sub="$this->stats['count'].' assets in register'" />
        <x-ledger.metric label="Accum. Depreciation" icon="◔" :value="Fmt::short($this->stats['accum'])" :sub="'As at '.Fmt::date(now())" />
        <x-ledger.metric label="Net Book Value" icon="◇" :value="Fmt::short($this->stats['nbv'])" :sub="'Monthly charge '.Fmt::short($this->stats['monthly'])" />
        <x-ledger.metric label="Due for Review" icon="!" :value="$this->stats['review']" :pill="$this->stats['review'] ? 'Action needed' : 'All reviewed'" :tone="$this->stats['review'] ? 'warn' : 'good'" />
    </div>

    <x-ledger.panel class="lg-stack" title="Asset / Amortization Accounting Flow" subtitle="Controlled conversion of expenditure into periodic expense.">
        <x-ledger.flow :nodes="[
            ['Identify Item', 'Invoice or contract qualifies as asset, prepaid, or immediate expense.'],
            ['Set Policy', 'Cost center, useful life, method, residual value, start date, tax group.'],
            ['Capitalize', 'Dr asset; Cr AP / Bank / GR-IR, with source-document link.', $this->assets->where('status', 'DRAFT')->count() ?: null],
            ['Run Schedule', 'Calculate monthly depreciation for open periods.', $this->stats['pending'] ? $this->stats['pending'].' due' : null],
            ['Post Journal', 'Dr depreciation expense; Cr accumulated depreciation.'],
        ]" />
    </x-ledger.panel>

    <x-ledger.panel title="Asset Register" subtitle="Scheduled entries are included in period-close controls.">
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="Search asset name, code, or location...">
            <select class="lg-select" wire:model.live="categoryFilter" x-data x-effect="$el.value = @js($categoryFilter)">
                <option value="">All categories</option>
                @foreach ($this->categories as $c)<option value="{{ $c->category_id }}">{{ $c->category_name }}</option>@endforeach
            </select>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>Asset</th><th>Category</th><th>Acquisition Date</th><th class="r">Acquisition Cost</th><th class="r">Accum. Depr.</th><th class="r">Net Book Value</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->assets as $a)
                        <tr wire:key="fa-{{ $a->asset_id }}" wire:dblclick="openView({{ $a->asset_id }})" style="cursor:pointer">
                            <td><span class="lg-name">{{ $a->asset_name }}</span><span class="lg-code">{{ $a->asset_code }} · {{ $a->location }}</span></td>
                            <td>{{ $a->category?->category_name }}<span class="lg-code">{{ $a->method === 'SL' ? 'Garis lurus' : 'Saldo menurun' }} · {{ $a->useful_life_months }} bln</span></td>
                            <td>{{ Fmt::date($a->acquisition_date) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($a->acquisition_cost) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($this->accumulated[$a->asset_id] ?? 0, true) }}</td>
                            <td class="r lg-money">{{ Fmt::rp((float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0)) }}</td>
                            <td><x-ledger.tag :tone="$this->statusTone($a->status)">{{ ucwords(strtolower(str_replace('_', ' ', $a->status))) }}</x-ledger.tag></td>
                            <td class="r">
                                <div class="lg-rowmenu" x-data="{ o: false }" @click.outside="o = false">
                                    <button type="button" class="lg-dots" @click="o = !o">⋮</button>
                                    <div class="m" x-show="o" x-cloak>
                                        <button type="button" wire:click="openView({{ $a->asset_id }})">Open schedule</button>
                                        @if ($a->status === 'DRAFT')
                                            <button type="button" wire:click="edit({{ $a->asset_id }})">Edit</button>
                                            <button type="button" wire:click="capitalize({{ $a->asset_id }})">Capitalize</button>
                                        @endif
                                        @if (in_array($a->status, ['ACTIVE', 'FULLY_DEPRECIATED'], true))
                                            <button type="button" wire:click="openDispose({{ $a->asset_id }})">Dispose</button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="lg-empty">No assets registered.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><span>{{ $this->assets->count() }} assets</span><span>Double-click a row for the depreciation schedule</span></div>
    </x-ledger.panel>

    @if ($modal === 'form')
        <x-ledger.modal :title="$editingId ? 'Edit Asset' : 'Add Fixed Asset'" size="xl">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field label="Asset name" name="asset_name" full><input wire:model="asset_name" placeholder="e.g. Mesin Cetak Offset Komori 4 warna"></x-ledger.field>
                <x-ledger.field label="Category" name="category_id">
                    <select wire:model.live="category_id" x-data x-effect="$el.value = @js($category_id)">
                        @foreach ($this->categories as $c)<option value="{{ $c->category_id }}">{{ $c->category_name }} ({{ $c->tax_group }})</option>@endforeach
                    </select>
                </x-ledger.field>
                <x-ledger.field label="Cost center" name="cc_id">
                    <select wire:model="cc_id" x-data x-effect="$el.value = @js($cc_id)"><option value="">—</option>@foreach ($this->ccOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
                <x-ledger.field label="Acquisition date" name="acq_date"><input type="date" wire:model="acq_date"></x-ledger.field>
                <x-ledger.field label="Depreciation start" name="start_date" hint="Umumnya awal bulan perolehan."><input type="date" wire:model="start_date"></x-ledger.field>
                <x-ledger.field label="Acquisition cost (IDR)" name="cost"><input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="cost"></x-ledger.field>
                <x-ledger.field label="Residual value (IDR)" name="residual"><input type="number" min="0" step="0.01" wire:model="residual"></x-ledger.field>
                <x-ledger.field label="Useful life (months)" name="life"><input type="number" min="1" wire:model.live="life"></x-ledger.field>
                <x-ledger.field label="Method" name="method" :hint="$cost && $life ? 'Beban bulan pertama ± '.Fmt::rp(($method === 'SL' ? ((float) $cost - (float) $residual) / max(1, (int) $life) : (float) $cost * (2 / (max(12, (int) $life) / 12)) / 12)) : null">
                    <select wire:model.live="method" x-data x-effect="$el.value = @js($method)"><option value="SL">Garis lurus (straight line)</option><option value="DDB">Saldo menurun ganda</option></select>
                </x-ledger.field>
                <x-ledger.field label="Location" name="location"><input wire:model="location" placeholder="e.g. Pabrik — Ruang Cetak"></x-ledger.field>
                <x-ledger.field label="Source document" name="source_ref"><input wire:model="source_ref" placeholder="e.g. BILL-AP-00012 / kontrak"></x-ledger.field>
                <x-ledger.field label="Capitalization credit account" name="credit_account_id" full hint="Lawan jurnal kapitalisasi: Hutang, Bank, atau GR/IR Clearing bila barang sudah diterima.">
                    <select wire:model="credit_account_id" x-data x-effect="$el.value = @js($credit_account_id)">@foreach ($this->creditOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn light" wire:click="save(false)">{{ \App\Support\Localization\Translator::t('shell_btn_save', 'Simpan') }}</button>
                <button type="button" class="lg-btn primary" wire:click="save(true)">Save & Capitalize</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'run')
        <x-ledger.modal title="Depreciation Run — {{ $this->periodOptions[$run_period] ?? $run_period }}" size="xl">
            <div class="lg-tablewrap" style="padding:16px 20px">
                @if ($errorMessage)<div class="lg-alert err">{{ $errorMessage }}</div>@endif
                <table>
                    <thead><tr><th>Asset</th><th>Category</th><th class="r">Monthly charge</th><th>Expense account</th></tr></thead>
                    <tbody>
                        @forelse ($this->preview as $p)
                            <tr><td>{{ $p->asset->asset_code }} · {{ $p->asset->asset_name }}</td><td>{{ $p->asset->category?->category_name }}</td><td class="r lg-money">{{ Fmt::rp($p->amount) }}</td><td>{{ \App\Models\Acc\FinAccount::find($p->asset->category->expense_account_id)?->account_code }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="lg-empty">Semua aset sudah disusutkan untuk periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="lg-foot"><b>{{ $this->preview->count() }} assets</b><b class="lg-money">{{ Fmt::rp($this->preview->sum('amount')) }}</b></div>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn primary" wire:click="runDepreciation" @disabled($this->preview->isEmpty())>Post Depreciation Journal</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'view' && $this->viewing)
        @foreach ([$this->viewing] as $a)
            <x-ledger.modal :title="$a->asset_code.' · '.$a->asset_name" size="xl">
                <dl class="lg-kv" style="padding-top:16px">
                    <dt>Category</dt><dd>{{ $a->category?->category_name }} · tax group {{ $a->category?->tax_group }}</dd>
                    <dt>Policy</dt><dd>{{ $a->method === 'SL' ? 'Garis lurus' : 'Saldo menurun ganda' }} · {{ $a->useful_life_months }} bulan · residu {{ Fmt::rp($a->residual_value) }}</dd>
                    <dt>Dates</dt><dd>Perolehan {{ Fmt::date($a->acquisition_date) }} · mulai susut {{ Fmt::date($a->depr_start_date) }}</dd>
                    <dt>Values</dt><dd>Cost {{ Fmt::rp($a->acquisition_cost) }} · akumulasi {{ Fmt::rp($this->accumulated[$a->asset_id] ?? 0) }} · NBV {{ Fmt::rp((float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0)) }}</dd>
                    <dt>Status</dt><dd><x-ledger.tag :tone="$this->statusTone($a->status)">{{ $a->status }}</x-ledger.tag> {{ $a->location }}</dd>
                </dl>
                <div class="lg-tablewrap" style="padding:0 20px 14px; max-height:340px">
                    <table>
                        <thead><tr><th>Month</th><th class="r">Charge</th><th class="r">Accumulated</th><th class="r">Book value</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($this->schedule as $row)
                                <tr><td>{{ $row['month'] }}</td><td class="r lg-money">{{ Fmt::rp($row['amount']) }}</td><td class="r lg-money">{{ Fmt::rp($row['accumulated']) }}</td><td class="r lg-money">{{ Fmt::rp($row['nbv']) }}</td><td><x-ledger.tag :tone="$row['status'] === 'POSTED' ? 'good' : 'mute'">{{ $row['status'] === 'POSTED' ? 'Posted' : 'Planned' }}</x-ledger.tag></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-slot:footer>
                    @if ($a->status === 'DRAFT')<button type="button" class="lg-btn primary" wire:click="capitalize({{ $a->asset_id }})">Capitalize</button>@endif
                    @if (in_array($a->status, ['ACTIVE', 'FULLY_DEPRECIATED'], true))<button type="button" class="lg-btn light" wire:click="openDispose({{ $a->asset_id }})">Dispose</button>@endif
                    <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shell_tab_close_title', 'Tutup') }}</button>
                </x-slot:footer>
            </x-ledger.modal>
        @endforeach
    @endif

    @if ($modal === 'dispose')
        <x-ledger.modal title="Dispose Asset">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field label="Disposal date" name="disp_date"><input type="date" wire:model="disp_date"></x-ledger.field>
                <x-ledger.field label="Proceeds (IDR)" name="disp_amount"><input type="number" min="0" step="0.01" wire:model="disp_amount"></x-ledger.field>
                <x-ledger.field label="Proceeds to" name="disp_bank" full>
                    <select wire:model="disp_bank" x-data x-effect="$el.value = @js($disp_bank)">@foreach ($this->cashBankOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
                <p class="lg-sub lg-field full">Jurnal: Dr akumulasi penyusutan + Dr kas (bila ada) / Cr harga perolehan, selisih ke laba/rugi lain-lain.</p>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn primary" wire:click="dispose">Post Disposal</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif
</x-ledger.page>
