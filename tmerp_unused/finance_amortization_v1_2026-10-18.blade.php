<?php

use App\Livewire\Concerns\OpensFromQuery;
use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinAssetDepr;
use App\Services\Acc\AssetService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Amortization -- mockup blueprint: biaya dibayar dimuka, aset tak berwujud &
// renovasi bangunan sewa. Tabel & engine sama dgn Fixed Assets (fin_asset +
// AssetService); yang beda hanya asset_type kategori dan akun lawannya
// (prabayar dikredit langsung ke akun prabayar, bukan akumulasi).
new #[Title('Amortization — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    // Dibuka dari grid v2 (?open={id}).
    protected function openFromQuery(int $id): void
    {
        $this->openView($id);
    }

    use FinanceScreen;

    private const TYPES = ['PREPAID', 'INTANGIBLE', 'LEASEHOLD'];

    public string $search = '';

    public string $categoryFilter = '';

    public string $run_period = '';

    public ?int $viewId = null;

    public string $asset_name = '';

    public string $category_id = '';

    public string $acq_date = '';

    public string $start_date = '';

    public string $cost = '';

    public string $life = '';

    public string $source_ref = '';

    public string $credit_account_id = '';

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
    public function schedules()
    {
        return FinAsset::with('category')
            ->whereIn('category_id', FinAssetCategory::whereIn('asset_type', self::TYPES)->select('category_id'))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('asset_name', 'like', "%{$this->search}%")->orWhere('asset_code', 'like', "%{$this->search}%")->orWhere('source_ref', 'like', "%{$this->search}%")))
            ->orderByDesc('depr_start_date')->limit(300)->get();
    }

    #[Computed]
    public function accumulated(): array
    {
        return FinAssetDepr::whereIn('asset_id', $this->schedules->pluck('asset_id'))
            ->selectRaw('asset_id, SUM(amount) total')->groupBy('asset_id')->pluck('total', 'asset_id')->all();
    }

    #[Computed]
    public function preview()
    {
        return app(AssetService::class)->preview((int) substr($this->run_period, 0, 4), (int) substr($this->run_period, 5, 2), self::TYPES);
    }

    #[Computed]
    public function stats(): array
    {
        $unamortized = fn (array $types) => $this->schedules->filter(fn ($a) => in_array($a->category?->asset_type, $types, true) && $a->status !== 'DISPOSED')
            ->sum(fn ($a) => (float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0));

        return [
            'prepaid' => round($unamortized(['PREPAID']), 2),
            'intangible' => round($unamortized(['INTANGIBLE', 'LEASEHOLD']), 2),
            'monthly' => $this->preview->sum('amount'),
            'pending' => $this->preview->count(),
            'active' => $this->schedules->where('status', 'ACTIVE')->count(),
            'draft' => $this->schedules->where('status', 'DRAFT')->count(),
        ];
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

    public function endDate(FinAsset $a): string
    {
        return Fmt::date($a->depr_start_date->copy()->addMonths((int) $a->useful_life_months)->subDay());
    }

    public function monthly(FinAsset $a): float
    {
        $plan = app(AssetService::class)->plan($a);

        return (float) ($plan[0] ?? 0);
    }

    public function openNew(): void
    {
        $this->reset(['asset_name', 'cost', 'source_ref']);
        $this->acq_date = now()->toDateString();
        $this->start_date = now()->startOfMonth()->toDateString();
        $this->category_id = (string) $this->categories->first()?->category_id;
        $this->life = (string) ($this->categories->first()?->useful_life_months ?: 12);
        $this->credit_account_id = (string) FinAccount::where('account_code', '111200')->value('account_id');
        $this->modal = 'form';
    }

    public function updatedCategoryId(): void
    {
        $this->life = (string) (FinAssetCategory::find($this->category_id)?->useful_life_months ?: 12);
    }

    public function save(AssetService $svc): void
    {
        $this->validate([
            'asset_name' => 'required|string|max:150',
            'category_id' => 'required|exists:tm2acc.fin_asset_category,category_id',
            'acq_date' => 'required|date',
            'start_date' => 'required|date',
            'cost' => 'required|numeric|min:1',
            'life' => 'required|integer|min:1|max:600',
            'credit_account_id' => 'required|exists:tm2acc.fin_account,account_id',
        ], [], ['asset_name' => 'Schedule name', 'cost' => 'Amount', 'life' => 'Period (months)']);

        if ($this->attempt(function () use ($svc) {
            $cat = FinAssetCategory::findOrFail($this->category_id);
            $asset = FinAsset::create([
                'asset_code' => $svc->nextCode('AM-'.$cat->category_code, $this->acq_date),
                'asset_name' => $this->asset_name, 'category_id' => $cat->category_id,
                'acquisition_date' => $this->acq_date, 'depr_start_date' => $this->start_date,
                'acquisition_cost' => $this->cost, 'residual_value' => 0,
                'useful_life_months' => (int) $this->life, 'method' => 'SL',
                'source_ref' => $this->source_ref ?: null, 'credit_account_id' => (int) $this->credit_account_id,
                'status' => 'DRAFT', 'created_by' => $this->login(), 'updated_by' => $this->login(),
            ]);
            $svc->capitalize($asset, $this->login());
        }, 'Jadwal amortisasi dibuat & dicatat: Dr akun prabayar/tak berwujud / Cr bank atau hutang.')) {
            $this->closeModal();
            unset($this->schedules, $this->stats, $this->accumulated, $this->preview);
        }
    }

    public function run(AssetService $svc): void
    {
        $id = null;
        if ($this->attempt(function () use ($svc, &$id) {
            $id = $svc->run((int) substr($this->run_period, 0, 4), (int) substr($this->run_period, 5, 2), $this->login(), self::TYPES);
        })) {
            session()->flash($id ? 'flash' : 'error', $id ? 'Jurnal amortisasi '.($this->periodOptions[$this->run_period] ?? '').' diposting.' : 'Tidak ada jadwal yang jatuh tempo di periode ini.');
            $this->closeModal();
            unset($this->schedules, $this->stats, $this->accumulated, $this->preview);
        }
    }

    public function openView(int $id): void
    {
        $this->viewId = $id;
        $this->modal = 'view';
    }

    public function export()
    {
        return $this->csvDownload('amortization-schedules-'.now()->format('Ymd').'.csv',
            ['Schedule', 'Reference', 'Category', 'Start', 'End', 'Monthly expense', 'Amortized to date', 'Unamortized balance', 'Status'],
            $this->schedules->map(fn ($a) => [
                $a->asset_name, $a->asset_code, $a->category?->category_name,
                $a->depr_start_date->format('Y-m-d'), $a->depr_start_date->copy()->addMonths((int) $a->useful_life_months)->subDay()->format('Y-m-d'),
                $this->monthly($a), (float) ($this->accumulated[$a->asset_id] ?? 0),
                (float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0), $a->status,
            ]));
    }
};
?>

<x-ledger.page title="Amortization" subtitle="Prepaid expenses, intangible assets, leasehold improvements, schedules, and amortization journals.">
    <x-slot:actions>
        <select class="lg-period" wire:model.live="run_period" x-data x-effect="$el.value = @js($run_period)">
            @foreach ($this->periodOptions as $k => $label)<option value="{{ $k }}">▣ {{ $label }}</option>@endforeach
        </select>
        <button type="button" class="lg-btn light" wire:click="export">⇩ Export Schedule</button>
        <button type="button" class="lg-btn primary" wire:click="openNew">＋ New Schedule</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="Prepaid Balance" icon="◔" :value="Fmt::short($this->stats['prepaid'])" sub="Remaining unamortized" />
        <x-ledger.metric label="Intangible & Leasehold" icon="◇" :value="Fmt::short($this->stats['intangible'])" sub="Net carrying amount" />
        <x-ledger.metric label="Monthly Expense" icon="↓" :value="Fmt::short($this->stats['monthly'])" :pill="$this->stats['pending'].' schedules'" tone="warn" />
        <x-ledger.metric label="Pending Runs" icon="◷" :value="$this->stats['pending']" :pill="$this->stats['pending'] ? 'Due at close' : 'All posted'" :tone="$this->stats['pending'] ? 'warn' : 'good'" />
    </div>

    <x-ledger.panel title="Amortization Schedule Register" subtitle="Active schedules and upcoming monthly postings.">
        <x-slot:action><button type="button" class="lg-link" wire:click="$set('modal', 'run')">Preview {{ \Carbon\Carbon::parse($run_period.'-01')->format('F') }} Run</button></x-slot:action>
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="Search asset, contract, or schedule...">
            <select class="lg-select" wire:model.live="categoryFilter" x-data x-effect="$el.value = @js($categoryFilter)">
                <option value="">All categories</option>
                @foreach ($this->categories as $c)<option value="{{ $c->category_id }}">{{ $c->category_name }}</option>@endforeach
            </select>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>Schedule / Reference</th><th>Category</th><th>Start Date</th><th>End Date</th><th class="r">Monthly Expense</th><th class="r">Unamortized Balance</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->schedules as $a)
                        <tr wire:key="am-{{ $a->asset_id }}" wire:dblclick="openView({{ $a->asset_id }})" style="cursor:pointer">
                            <td><span class="lg-name">{{ $a->asset_name }}</span><span class="lg-code">{{ $a->asset_code }} {{ $a->source_ref ? '· '.$a->source_ref : '' }}</span></td>
                            <td>{{ $a->category?->category_name }}</td>
                            <td>{{ Fmt::date($a->depr_start_date) }}</td>
                            <td>{{ $this->endDate($a) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($this->monthly($a)) }}</td>
                            <td class="r lg-money">{{ Fmt::rp((float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0)) }}</td>
                            <td><x-ledger.tag :tone="['DRAFT' => 'warn', 'ACTIVE' => 'good', 'FULLY_DEPRECIATED' => 'info', 'DISPOSED' => 'mute'][$a->status] ?? 'mute'">{{ $a->status === 'FULLY_DEPRECIATED' ? 'Fully amortized' : ucfirst(strtolower($a->status)) }}</x-ledger.tag></td>
                            <td class="r"><button type="button" class="lg-dots" wire:click="openView({{ $a->asset_id }})">⋮</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="lg-empty">No amortization schedules yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><span>{{ $this->schedules->count() }} schedules · {{ $this->stats['active'] }} active</span><span>Double-click a row for the full schedule</span></div>
    </x-ledger.panel>

    @if ($modal === 'form')
        <x-ledger.modal title="Create Amortization Schedule">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field label="Schedule name" name="asset_name" full><input wire:model="asset_name" placeholder="e.g. Asuransi Kendaraan 2026 / Lisensi ERP"></x-ledger.field>
                <x-ledger.field label="Category" name="category_id">
                    <select wire:model.live="category_id" x-data x-effect="$el.value = @js($category_id)">
                        @foreach ($this->categories as $c)<option value="{{ $c->category_id }}">{{ $c->category_name }}</option>@endforeach
                    </select>
                </x-ledger.field>
                <x-ledger.field label="Period (months)" name="life"><input type="number" min="1" wire:model.live="life"></x-ledger.field>
                <x-ledger.field label="Document date" name="acq_date"><input type="date" wire:model="acq_date"></x-ledger.field>
                <x-ledger.field label="Amortization start" name="start_date"><input type="date" wire:model="start_date"></x-ledger.field>
                <x-ledger.field label="Amount (IDR)" name="cost" :hint="$cost && $life ? 'Beban bulanan '.Fmt::rp((float) $cost / max(1, (int) $life)) : null"><input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="cost"></x-ledger.field>
                <x-ledger.field label="Reference" name="source_ref"><input wire:model="source_ref" placeholder="e.g. Polis 2026-0011"></x-ledger.field>
                <x-ledger.field label="Paid from / payable to" name="credit_account_id" full>
                    <select wire:model="credit_account_id" x-data x-effect="$el.value = @js($credit_account_id)">@foreach ($this->creditOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn primary" wire:click="save">{{ \App\Support\Localization\Translator::t('shell_btn_save', 'Simpan') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'run')
        <x-ledger.modal title="Amortization Run — {{ $this->periodOptions[$run_period] ?? $run_period }}" size="xl">
            <div class="lg-tablewrap" style="padding:16px 20px">
                @if ($errorMessage)<div class="lg-alert err">{{ $errorMessage }}</div>@endif
                <table>
                    <thead><tr><th>Schedule</th><th>Category</th><th class="r">Monthly expense</th></tr></thead>
                    <tbody>
                        @forelse ($this->preview as $p)
                            <tr><td>{{ $p->asset->asset_code }} · {{ $p->asset->asset_name }}</td><td>{{ $p->asset->category?->category_name }}</td><td class="r lg-money">{{ Fmt::rp($p->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="lg-empty">Semua jadwal sudah diposting untuk periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="lg-foot"><b>{{ $this->preview->count() }} schedules</b><b class="lg-money">{{ Fmt::rp($this->preview->sum('amount')) }}</b></div>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn primary" wire:click="run" @disabled($this->preview->isEmpty())>Post Amortization Journal</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'view' && $this->viewing)
        @foreach ([$this->viewing] as $a)
            <x-ledger.modal :title="$a->asset_code.' · '.$a->asset_name" size="xl">
                <dl class="lg-kv" style="padding-top:16px">
                    <dt>Category</dt><dd>{{ $a->category?->category_name }} ({{ $a->category?->asset_type }})</dd>
                    <dt>Period</dt><dd>{{ Fmt::date($a->depr_start_date) }} — {{ $this->endDate($a) }} · {{ $a->useful_life_months }} bulan</dd>
                    <dt>Amounts</dt><dd>Nilai {{ Fmt::rp($a->acquisition_cost) }} · teramortisasi {{ Fmt::rp($this->accumulated[$a->asset_id] ?? 0) }} · sisa {{ Fmt::rp((float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0)) }}</dd>
                    <dt>Reference</dt><dd>{{ $a->source_ref ?: '—' }}</dd>
                </dl>
                <div class="lg-tablewrap" style="padding:0 20px 14px; max-height:340px">
                    <table>
                        <thead><tr><th>Month</th><th class="r">Expense</th><th class="r">Amortized</th><th class="r">Remaining</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($this->schedule as $row)
                                <tr><td>{{ $row['month'] }}</td><td class="r lg-money">{{ Fmt::rp($row['amount']) }}</td><td class="r lg-money">{{ Fmt::rp($row['accumulated']) }}</td><td class="r lg-money">{{ Fmt::rp($row['nbv']) }}</td><td><x-ledger.tag :tone="$row['status'] === 'POSTED' ? 'good' : 'mute'">{{ $row['status'] === 'POSTED' ? 'Posted' : 'Planned' }}</x-ledger.tag></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-slot:footer><button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shell_tab_close_title', 'Tutup') }}</button></x-slot:footer>
            </x-ledger.modal>
        @endforeach
    @endif
</x-ledger.page>
