<?php

use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\VacVacancy;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// L2 -- docs/recruitment_tier4.md SECTION 7. "Buat Lowongan" dari requisition
// APPROVED = publish langsung (channel+judul+deskripsi dalam 1 modal), sesuai
// alur spec ("pilih channel(s), isi judul+deskripsi -> publish" 1 aksi).
// Tutup Lowongan = aksi terpisah sesudahnya.
new #[Title('Posting Lowongan — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $id_requisition = null;

    public string $channel = 'PORTAL_SENDIRI';

    public string $title = '';

    public string $description = '';

    protected const CHANNELS = [
        'PORTAL_SENDIRI' => 'Portal Sendiri', 'JOBSTREET' => 'Jobstreet',
        'LINKEDIN' => 'LinkedIn', 'REFERRAL' => 'Referral', 'LAINNYA' => 'Lainnya',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function channels(): array
    {
        return self::CHANNELS;
    }

    #[Computed]
    public function approvedRequisitions()
    {
        return RcrRequisition::where('status', 'APPROVED')->orderByDesc('id_requisition')->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => VacVacancy::count(),
            'published' => VacVacancy::where('is_published', true)->whereNull('closed_at')->count(),
            'applicants' => \App\Models\Hrr\AppApplication::count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return VacVacancy::query()
            ->with(['requisition', 'applications'])
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('title', 'like', "%{$this->search}%")->orWhere('vacancy_code', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('id_vacancy')
            ->paginate(20);
    }

    public function openNew(): void
    {
        $this->reset(['id_requisition', 'channel', 'title', 'description']);
        $this->channel = 'PORTAL_SENDIRI';
        $this->showModal = true;
    }

    public function publish(): void
    {
        if (! $this->id_requisition || trim($this->title) === '') {
            $this->addError('form', 'Requisition dan judul lowongan wajib diisi.');

            return;
        }

        $year = now()->format('y');
        $seq = VacVacancy::whereYear('created_at', now()->year)->count() + 1;

        VacVacancy::create([
            'vacancy_code' => "VAC{$year}".str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            'id_requisition' => $this->id_requisition,
            'channel' => $this->channel,
            'title' => $this->title,
            'description' => $this->description,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->showModal = false;
        unset($this->rows, $this->stats);
    }

    public function closeVacancy(int $id): void
    {
        VacVacancy::where('id_vacancy', $id)->update(['closed_at' => now()]);
        unset($this->rows);
    }
};
?>

<div class="max-w-[1400px] mx-auto my-5 px-4">
    <div class="grid three mb-3.5">
        <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
            <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Lowongan</p>
            <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
            <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Aktif Terbuka</p>
            <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->stats['published'] }}</p>
        </div>
        <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
            <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Total Pelamar Masuk</p>
            <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->stats['applicants'] }}</p>
        </div>
    </div>

    <div class="flex items-center gap-2 bg-white border border-[#d7dbe2] rounded-md px-2.5 py-2 overflow-x-auto mb-3">
        <div class="flex items-center gap-1.5 shrink-0">
            <span class="text-sm">🔍</span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari judul atau kode lowongan…"
                class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
        </div>
        <span class="flex-1"></span>
        <button type="button" wire:click="openNew"
            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
    </div>

    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
        <table class="w-full text-xs">
            <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5] bg-[#f4f6fa]">
                <tr>
                    <th class="text-left px-3 py-2">Lowongan</th>
                    <th class="text-left px-3 py-2">Channel</th>
                    <th class="text-left px-3 py-2">Requisition</th>
                    <th class="text-right px-3 py-2">Pelamar</th>
                    <th class="text-left px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right w-[80px]">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->rows as $v)
                    <tr wire:key="vac-{{ $v->id_vacancy }}">
                        <td class="px-3 py-2">
                            <div class="font-semibold text-[13px]">{{ $v->title }}</div>
                            <div class="text-[11px] text-muted font-mono">{{ $v->vacancy_code }}</div>
                        </td>
                        <td class="px-3 py-2">{{ self::CHANNELS[$v->channel] ?? $v->channel }}</td>
                        <td class="px-3 py-2">{{ $v->requisition->position_title ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <span class="inline-flex items-center justify-center min-w-6 h-5 px-1.5 rounded-full bg-[#eaf2ff] text-[#1d4ed8] text-[11px] font-bold">{{ $v->applications->count() }}</span>
                        </td>
                        <td class="px-3 py-2">
                            @if ($v->closed_at)
                                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full bg-[#eef1f5] text-[#4b5568]">DITUTUP</span>
                            @elseif ($v->is_published)
                                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full bg-[#eaf7ee] text-[#1f8a4c]">TERBUKA</span>
                            @else
                                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full bg-[#fff5e0] text-[#8a5a00]">DRAFT</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if ($v->is_published && ! $v->closed_at)
                                <button type="button" wire:click.stop="closeVacancy({{ $v->id_vacancy }})" wire:confirm="Tutup lowongan '{{ $v->title }}'? Kandidat tidak bisa apply lagi."
                                    title="Tutup Lowongan" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[#c0392b] hover:bg-[#fdeceb] cursor-pointer">⛔</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-muted text-xs">Belum ada lowongan diposting.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $this->rows->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl w-full max-w-[560px] p-5">
                <h3 class="text-sm font-bold text-navy mb-3">Buat &amp; Publish Lowongan</h3>
                @error('form') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <div class="space-y-3">
                    <x-form.select wire:model="id_requisition" :options="$this->approvedRequisitions->pluck('position_title', 'id_requisition')->all()" placeholder="— pilih requisition APPROVED —">
                        <x-slot:label>Requisition</x-slot:label>
                    </x-form.select>
                    <x-form.select wire:model="channel" :options="$this->channels" :placeholder="false">
                        <x-slot:label>Channel</x-slot:label>
                    </x-form.select>
                    <x-form.input label="Judul Lowongan" wire:model="title" placeholder="mis. Operator Cetak Offset" />
                    <x-form.textarea wire:model="description" rows="3"><x-slot:label>Deskripsi Publik</x-slot:label></x-form.textarea>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="$set('showModal', false)" class="border border-[#d7dbe2] text-xs font-bold px-4 py-2 rounded-md">Batal</button>
                    <button type="button" wire:click="publish" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Publish</button>
                </div>
            </div>
        </div>
    @endif
</div>
