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
        ], [], ['asset_name' => t('Schedule name'), 'cost' => t('Amount'), 'life' => t('Period (months)')]);

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
        }, t('Amortization schedule created & recorded: Dr prepaid/intangible account / Cr bank or payable.'))) {
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
            session()->flash($id ? 'flash' : 'error', $id ? str_replace(':period', $this->periodOptions[$this->run_period] ?? '', t('Amortization journal :period posted.')) : t('No schedules are due in this period.'));
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

<x-ledger.page :title="t('Amortization')" :subtitle="t('Prepaid expenses, intangible assets, leasehold improvements, schedules, and amortization journals.')">
    <x-slot:actions>
        <select class="lg-period" wire:model.live="run_period" x-data x-effect="$el.value = @js($run_period)">
            @foreach ($this->periodOptions as $k => $label)<option value="{{ $k }}">▣ {{ $label }}</option>@endforeach
        </select>
        <button type="button" class="lg-btn light" wire:click="export">⇩ {{ t('Export Schedule') }}</button>
        <button type="button" class="lg-btn primary" wire:click="openNew">＋ {{ t('New Schedule') }}</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Prepaid Balance')" icon="◔" :value="Fmt::short($this->stats['prepaid'])" :sub="t('Remaining unamortized')" />
        <x-ledger.metric :label="t('Intangible & Leasehold')" icon="◇" :value="Fmt::short($this->stats['intangible'])" :sub="t('Net carrying amount')" />
        <x-ledger.metric :label="t('Monthly Expense')" icon="↓" :value="Fmt::short($this->stats['monthly'])" :pill="str_replace(':n', $this->stats['pending'], t(':n schedules'))" tone="warn" />
        <x-ledger.metric :label="t('Pending Runs')" icon="◷" :value="$this->stats['pending']" :pill="$this->stats['pending'] ? t('Due at close') : t('All posted')" :tone="$this->stats['pending'] ? 'warn' : 'good'" />
    </div>

    <x-ledger.panel :title="t('Amortization Schedule Register')" :subtitle="t('Active schedules and upcoming monthly postings.')">
        <x-slot:action><button type="button" class="lg-link" wire:click="$set('modal', 'run')">{{ str_replace(':month', \Carbon\Carbon::parse($run_period.'-01')->format('F'), t('Preview :month Run')) }}</button></x-slot:action>
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search asset, contract, or schedule...') }}">
            <select class="lg-select" wire:model.live="categoryFilter" x-data x-effect="$el.value = @js($categoryFilter)">
                <option value="">{{ t('All categories') }}</option>
                @foreach ($this->categories as $c)<option value="{{ $c->category_id }}">{{ $c->category_name }}</option>@endforeach
            </select>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>{{ t('Schedule / Reference') }}</th><th>{{ t('Category') }}</th><th>{{ t('Start Date') }}</th><th>{{ t('End Date') }}</th><th class="r">{{ t('Monthly Expense') }}</th><th class="r">{{ t('Unamortized Balance') }}</th><th>{{ t('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->schedules as $a)
                        <tr wire:key="am-{{ $a->asset_id }}" wire:dblclick="openView({{ $a->asset_id }})" style="cursor:pointer">
                            <td><span class="lg-name">{{ $a->asset_name }}</span><span class="lg-code">{{ $a->asset_code }} {{ $a->source_ref ? '· '.$a->source_ref : '' }}</span></td>
                            <td>{{ $a->category?->category_name }}</td>
                            <td>{{ Fmt::date($a->depr_start_date) }}</td>
                            <td>{{ $this->endDate($a) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($this->monthly($a)) }}</td>
                            <td class="r lg-money">{{ Fmt::rp((float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0)) }}</td>
                            <td><x-ledger.tag :tone="['DRAFT' => 'warn', 'ACTIVE' => 'good', 'FULLY_DEPRECIATED' => 'info', 'DISPOSED' => 'mute'][$a->status] ?? 'mute'">{{ t($a->status === 'FULLY_DEPRECIATED' ? 'Fully amortized' : ucfirst(strtolower($a->status))) }}</x-ledger.tag></td>
                            <td class="r"><button type="button" class="lg-dots" wire:click="openView({{ $a->asset_id }})">⋮</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="lg-empty">{{ t('No amortization schedules yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><span>{{ str_replace([':n', ':active'], [$this->schedules->count(), $this->stats['active']], t(':n schedules · :active active')) }}</span><span>{{ t('Double-click a row for the full schedule') }}</span></div>
    </x-ledger.panel>

    @if ($modal === 'form')
        <x-ledger.modal :title="t('Create Amortization Schedule')">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field :label="t('Schedule name')" name="asset_name" full><input wire:model="asset_name" placeholder="{{ t('e.g. Vehicle Insurance 2026 / ERP License') }}"></x-ledger.field>
                <x-ledger.field :label="t('Category')" name="category_id">
                    <select wire:model.live="category_id" x-data x-effect="$el.value = @js($category_id)">
                        @foreach ($this->categories as $c)<option value="{{ $c->category_id }}">{{ $c->category_name }}</option>@endforeach
                    </select>
                </x-ledger.field>
                <x-ledger.field :label="t('Period (months)')" name="life"><input type="number" min="1" wire:model.live="life"></x-ledger.field>
                <x-ledger.field :label="t('Document date')" name="acq_date"><input type="date" wire:model="acq_date"></x-ledger.field>
                <x-ledger.field :label="t('Amortization start')" name="start_date"><input type="date" wire:model="start_date"></x-ledger.field>
                <x-ledger.field :label="t('Amount (IDR)')" name="cost" :hint="$cost && $life ? str_replace(':amount', Fmt::rp((float) $cost / max(1, (int) $life)), t('Monthly expense :amount')) : null"><input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="cost"></x-ledger.field>
                <x-ledger.field :label="t('Reference')" name="source_ref"><input wire:model="source_ref" placeholder="{{ t('e.g. Policy 2026-0011') }}"></x-ledger.field>
                <x-ledger.field :label="t('Paid from / payable to')" name="credit_account_id" full>
                    <select wire:model="credit_account_id" x-data x-effect="$el.value = @js($credit_account_id)">@foreach ($this->creditOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn primary" wire:click="save">{{ t('Save') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'run')
        <x-ledger.modal :title="t('Amortization Run').' — '.($this->periodOptions[$run_period] ?? $run_period)" size="xl">
            <div class="lg-tablewrap" style="padding:16px 20px">
                @if ($errorMessage)<div class="lg-alert err">{{ $errorMessage }}</div>@endif
                <table>
                    <thead><tr><th>{{ t('Schedule') }}</th><th>{{ t('Category') }}</th><th class="r">{{ t('Monthly expense') }}</th></tr></thead>
                    <tbody>
                        @forelse ($this->preview as $p)
                            <tr><td>{{ $p->asset->asset_code }} · {{ $p->asset->asset_name }}</td><td>{{ $p->asset->category?->category_name }}</td><td class="r lg-money">{{ Fmt::rp($p->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="lg-empty">{{ t('All schedules have been posted for this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="lg-foot"><b>{{ str_replace(':n', $this->preview->count(), t(':n schedules')) }}</b><b class="lg-money">{{ Fmt::rp($this->preview->sum('amount')) }}</b></div>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn primary" wire:click="run" @disabled($this->preview->isEmpty())>{{ t('Post Amortization Journal') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'view' && $this->viewing)
        @foreach ([$this->viewing] as $a)
            <x-ledger.modal :title="$a->asset_code.' · '.$a->asset_name" size="xl">
                <dl class="lg-kv" style="padding-top:16px">
                    <dt>{{ t('Category') }}</dt><dd>{{ $a->category?->category_name }} ({{ $a->category?->asset_type }})</dd>
                    <dt>{{ t('Period') }}</dt><dd>{{ Fmt::date($a->depr_start_date) }} — {{ $this->endDate($a) }} · {{ str_replace(':n', $a->useful_life_months, t(':n months')) }}</dd>
                    <dt>{{ t('Amounts') }}</dt><dd>{{ str_replace([':value', ':amortized', ':remaining'], [Fmt::rp($a->acquisition_cost), Fmt::rp($this->accumulated[$a->asset_id] ?? 0), Fmt::rp((float) $a->acquisition_cost - (float) ($this->accumulated[$a->asset_id] ?? 0))], t('Value :value · amortized :amortized · remaining :remaining')) }}</dd>
                    <dt>{{ t('Reference') }}</dt><dd>{{ $a->source_ref ?: '—' }}</dd>
                </dl>
                <div class="lg-tablewrap" style="padding:0 20px 14px; max-height:340px">
                    <table>
                        <thead><tr><th>{{ t('Month') }}</th><th class="r">{{ t('Expense') }}</th><th class="r">{{ t('Amortized') }}</th><th class="r">{{ t('Remaining') }}</th><th>{{ t('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($this->schedule as $row)
                                <tr><td>{{ $row['month'] }}</td><td class="r lg-money">{{ Fmt::rp($row['amount']) }}</td><td class="r lg-money">{{ Fmt::rp($row['accumulated']) }}</td><td class="r lg-money">{{ Fmt::rp($row['nbv']) }}</td><td><x-ledger.tag :tone="$row['status'] === 'POSTED' ? 'good' : 'mute'">{{ $row['status'] === 'POSTED' ? t('Posted') : t('Planned') }}</x-ledger.tag></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-slot:footer><button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Close') }}</button></x-slot:footer>
            </x-ledger.modal>
        @endforeach
    @endif
</x-ledger.page>
