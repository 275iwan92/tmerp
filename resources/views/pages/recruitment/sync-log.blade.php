<?php

use App\Models\Hrr\RcrSyncLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// L8 -- Rekap Sync Log (troubleshooting), docs/recruitment_tier4.md §L8. Murni
// read-only: PortalSyncService (jadwal tiap 2 menit) menulis 1 baris ke sini
// tiap kali pull dari portal (Firebase/MariaDB) berhasil/gagal. Tak ada Aksi
// buka/edit -- sengaja tidak link ke layar Offer (mount()-nya bikin AppOffer
// baru kalau belum ada, efek samping yang tak diinginkan cuma dari klik log).
new #[Title('Rekap Sync Log Rekrutmen — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    public string $result = '';

    public string $backend = '';

    #[Computed]
    public function rows()
    {
        return RcrSyncLog::with(['application.candidate', 'application.vacancy'])
            ->when($this->result !== '', fn ($q) => $q->where('result', $this->result))
            ->when($this->backend !== '', fn ($q) => $q->where('backend_used', $this->backend))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w
                    ->where('submission_uuid', 'like', $term)
                    ->orWhereHas('application.candidate', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->orderByDesc('fetched_at')
            ->limit(300)
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return RcrSyncLog::count();
    }

    #[Computed]
    public function statOk(): int
    {
        return RcrSyncLog::where('result', 'OK')->count();
    }

    #[Computed]
    public function statError(): int
    {
        return RcrSyncLog::where('result', 'ERROR')->count();
    }

    public function resetFilter(): void
    {
        $this->search = '';
        $this->result = '';
        $this->backend = '';
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" :label="t('Total Sync')" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" :label="t('Succeeded (OK)')" :value="$this->statOk" />
            <x-grid.stat-box tone="critical" :label="t('Failed (ERROR)')" :value="$this->statError" />
        </x-grid.stat-row>

        <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#e8f4ff] border border-[#b8d9f5] text-[#1560a8]">
            {{ t('History of sync pulls from the Recruitment Portal (Firebase/MariaDB) to internal (`PortalSyncService`, scheduled every 2 minutes).') }} {{ t('This screen is for troubleshooting only — no data-changing actions here.') }}
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar class="flex-wrap">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search candidate name / submission UUID…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <select wire:model.live="result" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        <option value="">{{ t('All Results') }}</option>
                        <option value="OK">OK</option>
                        <option value="ERROR">ERROR</option>
                    </select>
                    <select wire:model.live="backend" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        <option value="">{{ t('All Backends') }}</option>
                        <option value="FIREBASE">FIREBASE</option>
                        <option value="MARIADB">MARIADB</option>
                    </select>
                </div>
                @if ($search !== '' || $result !== '' || $backend !== '')
                    <x-slot:tools>
                        <button type="button" wire:click="resetFilter" class="shrink-0 text-[11px] font-semibold text-muted hover:text-navy underline">{{ t('Reset filter') }}</button>
                    </x-slot:tools>
                @endif
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Candidate / Submission UUID') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Vacancy') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ t('Backend') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">{{ t('Result') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Sync Time') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Error Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="synclog-{{ $r->id_log }}">
                                <x-grid.identity-cell :id="$r->id_log" :name="$r->application?->candidate?->name ?? t('— candidate not found —')" :code="$r->submission_uuid" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->application?->vacancy?->title ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block bg-[#eaf2ff] text-[#1d4ed8]">{{ $r->backend_used }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $r->result === 'OK' ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fde8e8] text-[#c0392b]' }}">{{ $r->result }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->fetched_at?->translatedFormat('d M Y H:i:s') ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[#8a94a6]">{{ $r->error_note ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" :message="t('No sync history yet.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
