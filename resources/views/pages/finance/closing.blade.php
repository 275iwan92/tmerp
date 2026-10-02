<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinCloseTask;
use App\Models\Acc\FinPeriod;
use App\Services\Acc\CloseService;
use App\Services\Acc\FinPeriodGuard;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Period Closing -- mockup blueprint (Month-End Close Flow) + spec LOCKED
// financeaccounting_delta_period_close.md: status OPEN/ON_HOLD/CLOSED,
// CLOSED IREVERSIBEL, gate wajib ke tm2inv.inv_period, checklist & kontrol
// harus lulus, dan voucher penutup tahun dibuat DRAFT (tidak auto-post).
new #[Title('Period Closing — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public int $year;

    public ?int $periodId = null;

    public string $reason = '';

    public string $newStatus = 'ON_HOLD';

    public string $taskNote = '';

    public ?int $taskId = null;

    public function mount(CloseService $close): void
    {
        $this->year = (int) now()->format('Y');
        $this->periodId = $close->currentPeriod()?->period_id;
    }

    #[Computed]
    public function periods()
    {
        return FinPeriod::where('fiscal_year', $this->year)->orderBy('period_no')->get();
    }

    #[Computed]
    public function period(): ?FinPeriod
    {
        return $this->periodId ? FinPeriod::with('tasks')->find($this->periodId) : null;
    }

    #[Computed]
    public function tasks()
    {
        return $this->period ? $this->period->tasks()->orderBy('step_no')->get() : collect();
    }

    #[Computed]
    public function controls(): array
    {
        return $this->period ? app(CloseService::class)->controls($this->period) : [];
    }

    #[Computed]
    public function blockers(): array
    {
        return $this->period ? app(FinPeriodGuard::class)->closeBlockers($this->period) : [];
    }

    #[Computed]
    public function lastClosed(): ?FinPeriod
    {
        return app(CloseService::class)->lastClosed();
    }

    #[Computed]
    public function completion(): array
    {
        $t = $this->tasks;
        $done = $t->where('status', 'COMPLETE')->count();
        $auto = collect($this->controls);

        return [
            'pct' => $t->count() ? (int) round($done / $t->count() * 100) : 0,
            'done' => $done, 'total' => $t->count(),
            'exceptions' => $auto->filter(fn ($c) => $c[3] === 'Exception')->count() + $t->where('status', 'EXCEPTION')->count(),
        ];
    }

    #[Computed]
    public function yearOptions(): array
    {
        return FinPeriod::select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year', 'fiscal_year')->all();
    }

    public function updatedYear(): void
    {
        $this->periodId = FinPeriod::where('fiscal_year', $this->year)->where('is_adjustment', false)
            ->where('status', '<>', 'CLOSED')->orderBy('period_no')->value('period_id');
        unset($this->period, $this->tasks, $this->controls, $this->blockers, $this->completion, $this->periods);
    }

    public function selectPeriod(int $id): void
    {
        $this->periodId = $id;
        unset($this->period, $this->tasks, $this->controls, $this->blockers, $this->completion);
    }

    public function startClosing(CloseService $close): void
    {
        if (! $this->period) {
            return;
        }
        $this->attempt(fn () => $close->start($this->period, $this->login()), 'Checklist tutup buku '.$this->period->period_name.' dibuat.');
        unset($this->tasks, $this->completion, $this->blockers);
    }

    public function completeTask(int $id, CloseService $close): void
    {
        $this->attempt(fn () => $close->setTask(FinCloseTask::findOrFail($id), 'COMPLETE', $this->login()), 'Kontrol ditandai selesai.');
        unset($this->tasks, $this->completion, $this->blockers);
    }

    public function reopenTask(int $id, CloseService $close): void
    {
        $this->attempt(fn () => $close->setTask(FinCloseTask::findOrFail($id), 'IN_PROGRESS', $this->login()), 'Kontrol dibuka kembali.');
        unset($this->tasks, $this->completion, $this->blockers);
    }

    public function openStatus(string $status): void
    {
        $this->newStatus = $status;
        $this->reason = '';
        $this->modal = 'status';
    }

    public function changeStatus(FinPeriodGuard $guard): void
    {
        $this->validate(['reason' => 'required|string|min:5|max:255'], [], ['reason' => 'Reason']);
        if ($this->attempt(fn () => $guard->changeStatus($this->period, $this->newStatus, $this->reason, $this->login()),
            $this->newStatus === 'CLOSED' ? 'Periode DITUTUP permanen — tidak ada posting baru yang diterima.' : 'Status periode diubah.')) {
            $this->closeModal();
            unset($this->period, $this->periods, $this->blockers, $this->lastClosed, $this->controls);
        }
    }

    public function yearEnd(CloseService $close): void
    {
        $v = null;
        if ($this->attempt(function () use ($close, &$v) {
            $v = $close->yearEndVoucher($this->year, $this->login());
        })) {
            session()->flash('flash', 'Voucher penutup tahun '.$this->year.' dibuat sebagai DRAFT ('.$v->voucher_number.'). Review & posting manual di Journal Entries sebelum periode 12 ditutup.');
        }
    }

    public function exportChecklist()
    {
        return $this->csvDownload('closing-checklist-'.($this->period?->period_name ?? '').'.csv',
            ['Step', 'Control', 'Description', 'Status', 'Completed by', 'Completed at'],
            $this->tasks->map(fn ($t) => [$t->step_no, $t->title, $t->description, $t->status, $t->completed_by, $t->completed_at?->format('Y-m-d H:i')]));
    }

    public function statusTone(string $s): string
    {
        return ['OPEN' => 'good', 'ON_HOLD' => 'warn', 'CLOSED' => 'mute'][$s] ?? 'mute';
    }
};
?>

<x-ledger.page title="Period Closing" subtitle="Month-end controls, accounting cut-off, approval, and period-lock workflow.">
    <x-slot:actions>
        <select class="lg-period" wire:model.live="year" x-data x-effect="$el.value = @js((string) $year)">
            @foreach ($this->yearOptions as $y)<option value="{{ $y }}">▣ FY {{ $y }}</option>@endforeach
        </select>
        <button type="button" class="lg-btn light" wire:click="exportChecklist">⇩ Checklist</button>
        @if ($this->period && $this->tasks->isEmpty())
            <button type="button" class="lg-btn primary" wire:click="startClosing">▶ Start Closing</button>
        @elseif ($this->period)
            <button type="button" class="lg-btn primary" wire:click="openStatus('CLOSED')" @disabled($this->period->status === 'CLOSED')>🔒 Approve & Lock</button>
        @endif
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="Closing Period" icon="▣" :value="$this->period?->period_name ?? '—'" :pill="$this->period ? ucfirst(strtolower(str_replace('_', ' ', $this->period->status))) : 'No open period'" :tone="$this->period ? $this->statusTone($this->period->status) : 'mute'" />
        <x-ledger.metric label="Completion" icon="✓" :value="$this->completion['pct'].'%'" :sub="$this->completion['done'].' of '.$this->completion['total'].' controls'" />
        <x-ledger.metric label="Open Exceptions" icon="!" :value="$this->completion['exceptions']" :pill="$this->completion['exceptions'] ? 'Need resolution' : 'None'" :tone="$this->completion['exceptions'] ? 'bad' : 'good'" />
        <x-ledger.metric label="Previous Period" icon="🔒" :value="$this->lastClosed?->period_name ?? '—'" :pill="$this->lastClosed ? 'Locked' : 'Nothing closed yet'" :tone="$this->lastClosed ? 'good' : 'warn'" />
    </div>

    <x-ledger.panel class="lg-stack" title="Month-End Close Flow" subtitle="Sequenced control framework before period lock.">
        <x-ledger.flow :nodes="[
            ['Transaction Cut-off', 'Complete operational invoices, bills, receipts and bank entries.'],
            ['Reconcile', 'Bank, AR, AP, tax and asset balances.'],
            ['Run Schedules', 'Depreciation, amortization, accruals and allocations.'],
            ['Review TB', 'Balance, variance, reasonableness and analytical controls.'],
            ['Approve & Lock', 'Controller approval; CLOSED is irreversible — no posting afterwards.'],
        ]" />
    </x-ledger.panel>

    <div class="lg-two">
        <x-ledger.panel :title="($this->period?->period_name ?? '—').' Closing Checklist'" subtitle="Each control has an owner, evidence, and completion status.">
            <div class="lg-steps">
                @forelse ($this->tasks as $t)
                    <x-ledger.step :num="$t->step_no" :done="$t->status === 'COMPLETE'" :title="$t->title" :text="$t->description">
                        @if ($t->status === 'COMPLETE')
                            <x-ledger.tag tone="good">Complete</x-ledger.tag>
                            <button type="button" class="lg-link" wire:click="reopenTask({{ $t->task_id }})">reopen</button>
                        @else
                            <x-ledger.tag tone="warn">{{ $t->status === 'IN_PROGRESS' ? 'In progress' : 'Pending' }}</x-ledger.tag>
                            <button type="button" class="lg-btn light sm" wire:click="completeTask({{ $t->task_id }})">Mark complete</button>
                        @endif
                    </x-ledger.step>
                @empty
                    <div class="lg-empty">Checklist belum dibuat — klik <b>Start Closing</b>.</div>
                @endforelse
            </div>
        </x-ledger.panel>

        <div>
            <x-ledger.panel class="lg-stack" title="Close Controls" subtitle="Automatic control status for this period.">
                <div class="lg-list">
                    @foreach ($this->controls as $c)
                        <x-ledger.row :icon="$c[0]" :tone="$c[3] === 'Passed' ? 'bl' : ($c[3] === 'Exception' ? 're' : 'or')" :title="$c[1]" :text="$c[2]">
                            <x-ledger.tag :tone="$c[3] === 'Passed' ? 'good' : ($c[3] === 'Exception' ? 'bad' : 'warn')">{{ $c[3] }}</x-ledger.tag>
                        </x-ledger.row>
                    @endforeach
                </div>
            </x-ledger.panel>

            <x-ledger.panel title="Period Manager" subtitle="13 periods per fiscal year (12 months + adjustment).">
                <x-slot:action>
                    @if ($this->period?->period_no === 12)
                        <button type="button" class="lg-link" wire:click="yearEnd">Buat Voucher Penutup Tahun</button>
                    @endif
                </x-slot:action>
                <div class="lg-tablewrap">
                    <table>
                        <thead><tr><th>Period</th><th>Range</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($this->periods as $p)
                                <tr wire:key="pr-{{ $p->period_id }}" @class(['lg-info' => $p->period_id === $periodId])>
                                    <td><span class="lg-name">{{ $p->period_name }}</span><span class="lg-code">{{ $p->is_adjustment ? 'Adjustment (period 13)' : 'Period '.$p->period_no }}</span></td>
                                    <td>{{ Fmt::date($p->start_date) }} — {{ Fmt::date($p->end_date) }}</td>
                                    <td><x-ledger.tag :tone="$this->statusTone($p->status)">{{ $p->status === 'CLOSED' ? '🔒 Closed' : ucfirst(strtolower(str_replace('_', ' ', $p->status))) }}</x-ledger.tag></td>
                                    <td class="r">
                                        @if ($p->status !== 'CLOSED')
                                            <div class="lg-rowmenu" x-data="{ o: false }" @click.outside="o = false">
                                                <button type="button" class="lg-dots" @click="o = !o">⋮</button>
                                                <div class="m" x-show="o" x-cloak>
                                                    <button type="button" wire:click="selectPeriod({{ $p->period_id }})">Select for closing</button>
                                                    @if ($p->status === 'OPEN')
                                                        <button type="button" wire:click="selectPeriod({{ $p->period_id }}); openStatus('ON_HOLD')">Set On hold</button>
                                                    @else
                                                        <button type="button" wire:click="selectPeriod({{ $p->period_id }}); openStatus('OPEN')">Reopen (to Open)</button>
                                                    @endif
                                                    <button type="button" wire:click="selectPeriod({{ $p->period_id }}); openStatus('CLOSED')">Lock permanently</button>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ledger.panel>
        </div>
    </div>

    @if ($this->blockers)
        <x-ledger.panel title="Lock Blockers" subtitle="Semua harus beres sebelum periode boleh ditutup permanen.">
            <div class="lg-list">
                @foreach ($this->blockers as $b)
                    <x-ledger.row icon="!" tone="re" :title="$b" />
                @endforeach
            </div>
        </x-ledger.panel>
    @endif

    @if ($modal === 'status')
        <x-ledger.modal :title="$newStatus === 'CLOSED' ? 'Lock Period Permanently' : 'Change Period Status'">
            <div class="lg-mb one">
                @if ($errorMessage)<div class="lg-alert err">{{ $errorMessage }}</div>@endif
                @if ($newStatus === 'CLOSED')
                    <div class="lg-alert warn">CLOSED bersifat <b>ireversibel</b>: tidak ada tombol, permission, atau endpoint apa pun yang bisa membukanya lagi. Posting ke {{ $this->period?->period_name }} akan ditolak selamanya.</div>
                @endif
                <x-ledger.field label="Period"><b>{{ $this->period?->period_name }} — {{ $this->period?->status }} → {{ $newStatus }}</b></x-ledger.field>
                <x-ledger.field label="Reason (wajib, tercatat di audit trail)" name="reason"><input wire:model="reason" placeholder="e.g. Tutup buku Agustus 2026 disetujui controller"></x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn {{ $newStatus === 'CLOSED' ? 'danger' : 'primary' }}" wire:click="changeStatus">{{ $newStatus === 'CLOSED' ? 'Lock Permanently' : 'Change Status' }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif
</x-ledger.page>
