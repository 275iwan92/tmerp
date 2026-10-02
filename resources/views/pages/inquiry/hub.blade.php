<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpInquiry;
use App\Services\Sales\InquiryDuplicator;
use App\Services\Sales\QuotationBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('TELASIH MAKMUR — Sistem Inquiry')] class extends Component
{
    use HasGridStatFilter;

    #[Url]
    public string $fq = '';

    #[Url]
    public string $fst = '';

    #[Url]
    public string $ftype = '';

    public ?int $selectedId = null;

    // Wizard "Create Inquiry" (keputusan user 2026-09-28, gantinya 3 kartu besar):
    // jenis -> sumber (khusus RECALC/REVISE) -> pilih data -> ringkasan.
    // Sumber "Master Produk" sengaja belum aktif: prod_part tidak menyimpan
    // dieline_spec (TMDieline DSL) yang dipakai form Inquiry, jadi butuh resolver
    // yang dirancang terpisah dulu.
    public ?string $wizStep = null;

    public ?string $wizJenis = null;

    public ?string $wizSumber = null;

    public ?int $wizPickedId = null;

    public string $wizQuery = '';

    // Duplicate = spec sama, customer lain -> lahir sebagai NEW DRAFT baru.
    public ?int $dupSourceId = null;

    public ?int $dupCustomerId = null;

    public string $dupCustomerQuery = '';

    private const TYPE_LABELS = [
        'NEW' => 'New Product',
        'RECALC' => 'Recalculate Price',
        'REVISE' => 'Revise Specification',
    ];

    #[Url]
    public bool $ready = false;

    public function showDrafts(): void
    {
        $this->fst = $this->fst === 'DRAFT' ? '' : 'DRAFT';
        $this->ready = false;
        $this->selectedId = null;
    }

    public function toggleReady(): void
    {
        $this->ready = ! $this->ready;
        $this->fst = '';
        $this->selectedId = null;
    }

    public function updatedFq(): void
    {
        $this->selectedId = null;
    }

    public function updatedFst(): void
    {
        $this->selectedId = null;
    }

    public function updatedFtype(): void
    {
        $this->selectedId = null;
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function openSelected()
    {
        if (! $this->selectedId) {
            return;
        }

        return $this->openRow($this->selectedId);
    }

    // Klik 2x baris / ikon Aksi -- buka langsung tanpa lewat select+tombol
    // footer dulu (GRID_UI_STANDARD.md: klik 2x buka detail).
    public function openRow(int $id)
    {
        $type = IqpInquiry::whereKey($id)->value('inquiry_type');

        return match ($type) {
            'RECALC' => redirect()->route('inquiry.recalc', ['id' => $id]),
            'REVISE' => redirect()->route('inquiry.revise', ['id' => $id]),
            default => redirect()->route('inquiry.form', ['id' => $id]),
        };
    }

    public function typeLabel(string $type): string
    {
        return isset(self::TYPE_LABELS[$type]) ? t(self::TYPE_LABELS[$type]) : $type;
    }

    // ============== Stat box = filter data (griduistandard.md §2b) ==============
    // Win/Loss sengaja TIDAK di sini -- pindah ke Quotation (keputusan user 2026-09-28).
    #[Computed]
    public function statUnconfirmed(): int
    {
        return IqpInquiry::where('stat_confirm_sales', false)->whereNotIn('sub_state', ['CANCELLED', 'LOST'])->count();
    }

    #[Computed]
    public function statProdev(): int
    {
        return IqpInquiry::whereHas('parts', fn ($p) => $p->whereNotNull('prod_part_id'))->count();
    }

    #[Computed]
    public function statProcost(): int
    {
        return IqpInquiry::whereHas('costing')->count();
    }

    protected function statFilterDefs(): array
    {
        return [
            'unconfirmed' => ['label' => t('Not Confirm'), 'value' => $this->statUnconfirmed, 'tone' => 'warn',
                'filter' => fn ($q) => $q->where('stat_confirm_sales', false)->whereNotIn('sub_state', ['CANCELLED', 'LOST'])],
            'ready' => ['label' => t('Ready to Quote'), 'value' => $this->readyToQuoteIds->count(), 'tone' => 'total',
                'filter' => fn ($q) => $q->whereKey($this->readyToQuoteIds)],
            'prodev' => ['label' => t('Processed by Pro-Dev'), 'value' => $this->statProdev, 'tone' => 'extra',
                'filter' => fn ($q) => $q->whereHas('parts', fn ($p) => $p->whereNotNull('prod_part_id'))],
            'procost' => ['label' => t('Processed by Pro-Cost'), 'value' => $this->statProcost, 'tone' => 'active',
                'filter' => fn ($q) => $q->whereHas('costing')],
        ];
    }

    public function typeTabs(): array
    {
        return ['' => t('All')] + collect(self::TYPE_LABELS)->mapWithKeys(fn ($v, $k) => [$k => $this->typeLabel($k)])->all();
    }

    public function typeTabBadges(): array
    {
        $counts = IqpInquiry::selectRaw('inquiry_type, COUNT(*) n')->groupBy('inquiry_type')->pluck('n', 'inquiry_type');

        return collect($this->typeTabs())->map(fn ($label, $key) => [
            'text' => $key === '' ? $counts->sum() : ($counts[$key] ?? 0), 'tone' => 'neutral',
        ])->all();
    }

    #[Computed]
    public function draftCount(): int
    {
        return IqpInquiry::where('sub_state', 'DRAFT')->count();
    }

    // Ready to Quote = daftar yang SAMA dgn jendela "Choose inquiry" di Quotation
    // (costing published, belum ditarik ke quotation mana pun). Membuat quotation
    // tidak mengubah sub_state, jadi sub_state=PRICE_APPROVED tidak bisa dipakai.
    #[Computed]
    public function readyToQuoteIds()
    {
        return QuotationBuilder::availableInquiries()->pluck('id_inquiry');
    }

    #[Computed]
    public function inquiries()
    {
        return $this->applyStatFilter(IqpInquiry::query())
            ->when($this->ready, fn ($q) => $q->whereKey($this->readyToQuoteIds))
            ->with(['customer', 'tiers' => fn ($q) => $q->where('is_primary', true), 'parts' => fn ($q) => $q->orderBy('part_seq')->limit(1)])
            ->when($this->fst, fn ($q) => $q->where('sub_state', $this->fst))
            ->when($this->ftype, fn ($q) => $q->where('inquiry_type', $this->ftype))
            ->when($this->fq, function ($q) {
                $q->where(function ($q) {
                    $q->where('inquiry_no', 'like', "%{$this->fq}%")
                        ->orWhere('product_draft_name', 'like', "%{$this->fq}%")
                        ->orWhere('prospect_name', 'like', "%{$this->fq}%");
                });
            })
            ->orderByDesc('id_inquiry')
            ->limit(200)
            ->get();
    }

    public function statusPillClass(string $state): string
    {
        return match ($state) {
            'DRAFT' => 'draft',
            'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED' => 'submit',
            'PRICE_APPROVED' => 'priceok',
            'QUOTED' => 'quoted',
            'PROVING', 'PROVE_APPROVED' => 'proving',
            'CONVERTED' => 'ok',
            'CANCELLED', 'LOST' => 'cancelled',
            default => 'draft',
        };
    }

    // ============== Wizard Create Inquiry ==============

    public function openWizard(): void
    {
        $this->reset('wizJenis', 'wizSumber', 'wizPickedId', 'wizQuery');
        $this->wizStep = 'jenis';
    }

    public function closeWizard(): void
    {
        $this->wizStep = null;
    }

    public function wizChooseJenis(string $jenis): void
    {
        if (! in_array($jenis, ['NEW', 'RECALC', 'REVISE'], true)) {
            return;
        }

        $this->wizJenis = $jenis;
        $this->wizStep = $jenis === 'NEW' ? 'summary' : 'sumber';
    }

    public function wizChooseSumber(string $sumber): void
    {
        if ($sumber !== 'INQUIRY') {
            return;
        }

        $this->wizSumber = $sumber;
        $this->wizPickedId = null;
        $this->wizQuery = '';
        $this->wizStep = 'picker';
    }

    public function wizPick(int $id): void
    {
        $this->wizPickedId = $id;
    }

    public function wizBack(): void
    {
        $this->wizStep = match ($this->wizStep) {
            'sumber' => 'jenis',
            'picker' => 'sumber',
            'summary' => $this->wizJenis === 'NEW' ? 'jenis' : 'picker',
            default => 'jenis',
        };
    }

    public function wizNext(): void
    {
        if ($this->wizStep === 'picker' && $this->wizPickedId) {
            $this->wizStep = 'summary';
        }
    }

    public function wizConfirm()
    {
        if ($this->wizJenis === 'NEW') {
            return redirect()->route('inquiry.form', ['mode' => 'NEW']);
        }

        if (! $this->wizPickedId) {
            return;
        }

        $route = $this->wizJenis === 'RECALC' ? 'inquiry.recalc' : 'inquiry.revise';

        return redirect()->route($route, ['source_id' => $this->wizPickedId]);
    }

    public function wizJenisOptions(): array
    {
        return [
            'NEW' => ['✨', $this->typeLabel('NEW'), t('Never made before. Set product type, dimensions, material and finishing from scratch. Flow: Sales → Pro-Dev → Pro-Cost.')],
            'RECALC' => ['🔁', $this->typeLabel('RECALC'), t('Same spec & layout, only a new price is needed. Source: an earlier inquiry (including Cancelled/Lost).')],
            'REVISE' => ['✏️', $this->typeLabel('REVISE'), t('Something changed: dimensions, material, color, artwork or process. Source: an earlier inquiry (including Cancelled/Lost).')],
        ];
    }

    public function stateTone(?string $state): string
    {
        return match ($state) {
            'PRICE_APPROVED', 'QUOTED', 'CONVERTED' => 'success',
            'CANCELLED', 'LOST' => 'danger',
            'DRAFT', null => 'muted',
            default => 'info',
        };
    }

    #[Computed]
    public function wizPicked(): ?IqpInquiry
    {
        return $this->wizPickedId ? IqpInquiry::with('customer')->find($this->wizPickedId) : null;
    }

    // ============== Duplicate (spec sama, customer lain) ==============

    public function openDuplicate(int $id): void
    {
        $this->dupSourceId = $id;
        $this->dupCustomerId = null;
        $this->dupCustomerQuery = '';
    }

    public function closeDuplicate(): void
    {
        $this->dupSourceId = null;
    }

    public function pickDupCustomer(int $id): void
    {
        $this->dupCustomerId = $id;
    }

    #[Computed]
    public function dupSource(): ?IqpInquiry
    {
        return $this->dupSourceId ? IqpInquiry::with('customer')->find($this->dupSourceId) : null;
    }

    #[Computed]
    public function dupCustomers()
    {
        $q = trim($this->dupCustomerQuery);

        return CustCustomer::where('activate', true)
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('customer_name', 'like', "%{$q}%")->orWhere('customer_code', 'like', "%{$q}%")))
            ->orderBy('customer_name')
            ->limit(30)
            ->get(['id_customer', 'customer_code', 'customer_name']);
    }

    public function confirmDuplicate()
    {
        if (! $this->dupSource || ! $this->dupCustomerId) {
            return;
        }

        $new = InquiryDuplicator::duplicate($this->dupSource, $this->dupCustomerId, Auth::user()->login);

        return redirect()->route('inquiry.form', ['id' => $new->id_inquiry]);
    }

    #[Computed]
    public function wizCandidates()
    {
        $q = trim($this->wizQuery);

        return IqpInquiry::query()
            ->whereNotNull('inquiry_no')
            ->with(['customer', 'tiers' => fn ($q) => $q->where('is_primary', true), 'parts' => fn ($q) => $q->orderBy('part_seq')->limit(1)])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('inquiry_no', 'like', "%{$q}%")
                        ->orWhere('product_draft_name', 'like', "%{$q}%")
                        ->orWhere('prospect_name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id_inquiry')
            ->limit(50)
            ->get();
    }
};
?>

<div>
    <div class="hub">
        {{-- 3 tombol aksi + 2 kotak statistik (keputusan user 2026-09-28) menggantikan
        3 kartu besar + 8 kotak statistik. Win/Loss pindah ke Quotation. --}}
        <div class="shrink-0 flex flex-col gap-2 mb-2">
            <x-ui.card variant="plain" size="sm">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button icon="fa-plus" wire:click="openWizard">{{ t('Create Inquiry') }}</x-ui.button>
                    <x-ui.button variant="chip" :active="$fst === 'DRAFT'" icon="fa-pen-to-square" wire:click="showDrafts">
                        {{ t('Continue Draft') }}
                        <x-ui.badge tone="warn" size="xs" pill>{{ $this->draftCount }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button variant="chip" :active="$ready" icon="fa-file-invoice-dollar" wire:click="toggleReady">
                        {{ t('Ready to Quote') }}
                        <x-ui.badge tone="success" size="xs" pill>{{ $this->readyToQuoteIds->count() }}</x-ui.badge>
                    </x-ui.button>
                    <span class="text-[11px] text-muted ml-auto">{{ t('Double-click a row to open. DRAFT can be edited; other statuses are view-only.') }}</span>
                </div>
            </x-ui.card>
            <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />
        </div>

        @if ($wizStep)
            <x-ui.modal :title="t('Create Inquiry')" close="closeWizard" size="xl">
                <div class="p-4 flex flex-col gap-3">
                    <p class="text-[10.5px] font-bold uppercase tracking-wide text-muted">
                        @if ($wizStep === 'jenis') {{ t('Step 1 · Choose type') }}
                        @elseif ($wizStep === 'sumber') {{ t('Step 2 · Choose data source') }}
                        @elseif ($wizStep === 'picker') {{ t('Step 3 · Choose source inquiry') }}
                        @else {{ t('Summary') }}
                        @endif
                    </p>

                    @if ($wizStep === 'jenis')
                        @foreach ($this->wizJenisOptions() as $code => $opt)
                            <x-ui.card variant="panel" size="sm" wire:key="wj-{{ $code }}">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">{{ $opt[0] }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[13px] font-bold text-navy">{{ $opt[1] }} <x-ui.badge tone="code" size="xs">{{ $code }}</x-ui.badge></p>
                                        <p class="text-[11px] text-muted">{{ $opt[2] }}</p>
                                    </div>
                                    <x-ui.button size="sm" wire:click="wizChooseJenis('{{ $code }}')">{{ t('Choose') }}</x-ui.button>
                                </div>
                            </x-ui.card>
                        @endforeach
                    @elseif ($wizStep === 'sumber')
                        <x-ui.card variant="panel" size="sm">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">📁</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-bold text-navy">{{ t('From an Earlier Inquiry') }}</p>
                                    <p class="text-[11px] text-muted">{{ t('All statuses included, Cancelled/Lost too -- its spec is reused.') }}</p>
                                </div>
                                <x-ui.button size="sm" wire:click="wizChooseSumber('INQUIRY')">{{ t('Choose') }}</x-ui.button>
                            </div>
                        </x-ui.card>
                        <x-ui.card variant="muted" size="sm">
                            <div class="flex items-center gap-3">
                                <span class="text-xl opacity-50">📦</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-bold text-muted">{{ t('From Master Product') }} <x-ui.badge tone="warn" size="xs">{{ t('Soon') }}</x-ui.badge></p>
                                    <p class="text-[11px] text-muted">{{ t('Not active yet: Master Product does not store the dieline used by the Inquiry form; needs a separate design.') }}</p>
                                </div>
                                <x-ui.button size="sm" variant="secondary" disabled>{{ t('Choose') }}</x-ui.button>
                            </div>
                        </x-ui.card>
                    @elseif ($wizStep === 'picker')
                        <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="wizQuery" placeholder="{{ t('Search inquiry no. / customer / product…') }}" />
                        <div class="max-h-[45vh] overflow-y-auto tm-scroll border border-field-border rounded">
                            <x-ui.table size="sm" sticky hover>
                                <thead>
                                    <tr><th>{{ t('Inquiry No.') }}</th><th>{{ t('Product / Customer') }}</th><th>{{ t('Type') }}</th><th>{{ t('Status') }}</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->wizCandidates as $row)
                                        <tr wire:key="wc-{{ $row->id_inquiry }}" wire:click="wizPick({{ $row->id_inquiry }})"
                                            @class(['cursor-pointer', 'bg-accent-soft font-semibold' => $wizPickedId === $row->id_inquiry])>
                                            <td class="font-mono text-accent">{{ $row->inquiry_no }}</td>
                                            <td>
                                                <div class="text-navy">{{ $row->product_draft_name ?: t('(no product name)') }}</div>
                                                <div class="text-[10.5px] text-muted">{{ $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—') }}</div>
                                            </td>
                                            <td>{{ $row->inquiry_type }}</td>
                                            <td><x-ui.badge :tone="$this->stateTone($row->sub_state)" size="xs" pill>{{ $row->sub_state }}</x-ui.badge></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-6 text-muted">{{ t('No matching product/inquiry.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </x-ui.table>
                        </div>
                    @else
                        <x-ui.card variant="muted" size="sm">
                            <p class="text-xs leading-6">
                                {{ t('Type') }}: <b>{{ $this->typeLabel($wizJenis) }}</b> ({{ $wizJenis }})<br>
                                @if ($wizJenis === 'NEW')
                                    {{ t('An empty form will open.') }}
                                @elseif ($this->wizPicked)
                                    {{ t('Source') }}: <b>{{ $this->wizPicked->inquiry_no }}</b> · {{ $this->wizPicked->product_draft_name }}
                                    <x-ui.badge :tone="$this->stateTone($this->wizPicked->sub_state)" size="xs" pill>{{ $this->wizPicked->sub_state }}</x-ui.badge><br>
                                    {{ t('The form will open, filled automatically from this source.') }}
                                @endif
                            </p>
                        </x-ui.card>
                    @endif
                </div>
                <x-slot:footer>
                    @if ($wizStep !== 'jenis')
                        <x-ui.button variant="secondary" icon="fa-arrow-left" wire:click="wizBack" class="mr-auto">Back</x-ui.button>
                    @endif
                    <x-ui.button preset="cancel" wire:click="closeWizard" />
                    @if ($wizStep === 'picker')
                        <x-ui.button iconRight="fa-arrow-right" wire:click="wizNext" :disabled="! $wizPickedId">{{ t('Next') }}</x-ui.button>
                    @elseif ($wizStep === 'summary')
                        <x-ui.button iconRight="fa-arrow-right" wire:click="wizConfirm">{{ t('Open Form') }}</x-ui.button>
                    @endif
                </x-slot:footer>
            </x-ui.modal>
        @endif

        @if ($this->dupSource)
            <x-ui.modal :title="t('Duplicate Inquiry')" close="closeDuplicate" size="lg">
                <div class="p-4 flex flex-col gap-3">
                    <x-ui.card variant="muted" size="sm">
                        <p class="text-[13px] font-bold text-navy">{{ $this->dupSource->inquiry_no }} — {{ $this->dupSource->product_draft_name ?: t('(no product name)') }}</p>
                        <p class="text-[11px] text-muted">{{ t('Source customer') }}: {{ $this->dupSource->is_prospect ? $this->dupSource->prospect_name : ($this->dupSource->customer?->customer_name ?? '—') }}</p>
                    </x-ui.card>
                    <x-form.input :case-toggle="false" label="{{ t('Target customer') }}" wire:model.live.debounce.300ms="dupCustomerQuery" placeholder="{{ t('Search customer name / code…') }}" />
                    <div class="max-h-[40vh] overflow-y-auto tm-scroll border border-field-border rounded">
                        <x-ui.table size="sm" sticky hover>
                            <tbody>
                                @forelse ($this->dupCustomers as $c)
                                    <tr wire:key="dc-{{ $c->id_customer }}" wire:click="pickDupCustomer({{ $c->id_customer }})"
                                        @class(['cursor-pointer', 'bg-accent-soft font-semibold' => $dupCustomerId === $c->id_customer])>
                                        <td class="font-mono text-accent w-28">{{ $c->customer_code }}</td>
                                        <td>{{ $c->customer_name }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center py-6 text-muted">{{ t('Customer not found.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </x-ui.table>
                    </div>
                    <p class="text-[11px] text-muted">{{ t('Spec (parts, material, process, finishing, ink) is copied as is. Qty & price are not: the new inquiry starts as NEW DRAFT and goes through Pro-Dev → Pro-Cost again.') }}</p>
                </div>
                <x-slot:footer>
                    <x-ui.button preset="cancel" wire:click="closeDuplicate" />
                    <x-ui.button icon="fa-copy" wire:click="confirmDuplicate" :disabled="! $dupCustomerId">{{ t('Duplicate') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endif

        <div class="openbox" id="saved-box" wire:key="saved-box">
            <div class="hd">📂 <span>{{ t('Saved Inquiries') }}{{ $ftype ? ' — '.$this->typeLabel($ftype) : '' }}{{ $ready ? ' — '.t('Ready to Quote') : '' }}</span>
                <span class="cnt">{{ $this->inquiries->count() }} {{ t('inquiries') }}</span></div>
            <div class="bd">
                <div class="filters">
                    <input type="text" wire:model.live.debounce.400ms="fq" placeholder="{{ t('Search inquiry no. / product name / prospect…') }}">
                    <div class="w-[170px] shrink-0">
                        <select wire:model.live="fst">
                            <option value="">{{ t('All statuses') }}</option>
                            <option value="DRAFT">DRAFT</option>
                            <option value="WAITING_COST">WAITING_COST</option>
                            <option value="SUBMITTED">SUBMITTED</option>
                            <option value="PRICE_CALCULATED">PRICE_CALCULATED</option>
                            <option value="PRICE_APPROVED">PRICE_APPROVED</option>
                            <option value="QUOTED">QUOTED</option>
                            <option value="PROVING">PROVING</option>
                            <option value="PROVE_APPROVED">PROVE_APPROVED</option>
                            <option value="CONVERTED">CONVERTED</option>
                            <option value="CANCELLED">CANCELLED</option>
                            <option value="LOST">LOST</option>
                        </select>
                    </div>
                </div>

                <x-ui.tabs :tabs="$this->typeTabs()" :active="$ftype" model="ftype" :badges="$this->typeTabBadges()" class="mb-2" />

                <div class="qwrap">
                    <table class="qtbl">
                        <thead>
                            <tr>
                                <th>{{ t('Product / Customer') }}</th>
                                <th>{{ t('Inquiry No.') }}</th><th>{{ t('Date') }}</th>
                                <th class="hide-sm">{{ t('Product Type') }}</th>
                                <th style="text-align:right">{{ t('Main tier qty') }}</th>
                                <th>{{ t('Status') }}</th><th>{{ t('Type') }}</th>
                                <th style="text-align:center; width:1%">{{ t('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->inquiries as $row)
                                @php($custName = $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—'))
                                <tr wire:key="inq-{{ $row->id_inquiry }}" class="{{ $selectedId === $row->id_inquiry ? 'sel' : '' }}"
                                    wire:click="selectRow({{ $row->id_inquiry }})" wire:dblclick="openRow({{ $row->id_inquiry }})" title="Klik 2x untuk buka">
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($row->id_inquiry) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ \App\Support\Grid\Avatar::initials($row->product_draft_name ?: $custName) }}</div>
                                            <div>
                                                <div class="font-semibold text-[13px] text-navy">{{ $row->product_draft_name ?: t('(no product name)') }}</div>
                                                <div class="text-[11px] text-muted">
                                                    {{ $custName }}
                                                    @if ($row->is_prospect)
                                                        <span class="pill prospect">{{ t('prospect') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="qdoc">{{ $row->inquiry_no ?: t('(draft, no number yet)') }}</span></td>
                                    <td>{{ $row->inquiry_date?->format('Y-m-d') }}</td>
                                    <td class="hide-sm">{{ $row->parts->first()?->part_type ?? '—' }}</td>
                                    <td style="text-align:right">
                                        {{ $row->tiers->first()?->qty ? number_format((float) $row->tiers->first()->qty, 0, ',', '.') : '—' }}
                                    </td>
                                    <td><span class="pill {{ $this->statusPillClass($row->sub_state) }}">{{ $row->sub_state }}</span></td>
                                    <td><span class="pill {{ strtolower($row->inquiry_type) }}">{{ $row->inquiry_type }}</span></td>
                                    <td style="text-align:center" class="whitespace-nowrap">
                                        <button type="button" wire:click.stop="openRow({{ $row->id_inquiry }})"
                                            title="{{ $row->sub_state === 'DRAFT' ? t('Open to edit') : t('Open (view-only)') }}"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">
                                            {{ $row->sub_state === 'DRAFT' ? '✎' : '↗' }}
                                        </button>
                                        <x-grid.action-icon icon="fa-copy" :bordered="false" title="{{ t('Duplicate to another customer') }}" action="openDuplicate({{ $row->id_inquiry }})" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="qempty">{{ t('No matching inquiry yet.') }}</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php($selectedRow = $selectedId ? $this->inquiries->firstWhere('id_inquiry', $selectedId) : null)
                <div class="openact">
                    <div class="hint">
                        @if ($selectedRow)
                            {{ t('Selected') }} <b>{{ $selectedRow->inquiry_no ?: 'id '.$selectedRow->id_inquiry }}</b> —
                            {{ $selectedRow->sub_state === 'DRAFT' ? t('will open for editing') : t('opens view-only (not DRAFT)') }}
                        @else
                            {{ t('Click a row to select it.') }}
                        @endif
                    </div>
                    <div class="sp"></div>
                    <button type="button" class="btn p" wire:click="openSelected" @disabled(! $selectedRow)>
                        {{ $selectedRow && $selectedRow->sub_state !== 'DRAFT' ? t('Open (view-only) →') : t('Open inquiry →') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
