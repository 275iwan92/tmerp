{{-- Formasi Harian: bentuk sheet formasi HR (NO / POSISI / JAM KERJA / tanggal).
     Di-include dari shift-roster.blade.php saat $view === 'formasi'. --}}
@php
    $fmRows = $this->formasi;
    $fmDates = $this->weekDates;
    $fmLocked = $this->isLocked();
    $fmUnitNo = 0;
@endphp

<div class="px-4 pb-7 pt-4">
    <section class="bg-[var(--rs-surface)] border border-[var(--rs-border)] rounded-lg overflow-hidden">
        <div class="flex items-center gap-3 px-3.5 py-2.5 border-b border-[var(--rs-border)] flex-wrap">
            <div>
                <div class="text-[13px] font-bold uppercase tracking-wide">Formasi Minggu {{ $this->weekNumber() }}</div>
                <div class="text-[11px] text-[var(--rs-muted)]">Tanggal {{ $this->weekStart->translatedFormat('d') }} – {{ $this->weekEnd->translatedFormat('d F Y') }} · klik sel untuk isi / ubah nama</div>
            </div>
            <label class="ml-auto flex items-center gap-1.5 text-[11px] text-[var(--rs-ink-2)] cursor-pointer">
                <input type="checkbox" wire:model.live="formasiAllUnits" class="cursor-pointer">
                Tampilkan semua unit kerja jadwal ini
            </label>
            @unless ($fmLocked)
                <button type="button" wire:click="copyPrevWeek" wire:confirm="Salin seluruh formasi minggu lalu ke minggu ini?"
                    class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">⧉ Salin minggu lalu</button>
            @endunless
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] border-collapse text-[11.5px]">
                <thead>
                    <tr class="bg-[var(--rs-surface-alt)] text-[var(--rs-muted)] uppercase text-[10.5px] tracking-wide">
                        <th class="border border-[var(--rs-border)] px-2 py-2 w-[38px]">No</th>
                        <th class="border border-[var(--rs-border)] px-2 py-2 w-[150px] text-left">Posisi</th>
                        <th class="border border-[var(--rs-border)] px-2 py-2 w-[150px] text-left">Jam Kerja</th>
                        @foreach ($fmDates as $d)
                            <th class="border border-[var(--rs-border)] px-2 py-2 {{ $d->isSunday() ? 'text-[var(--rs-bad)]' : '' }}">
                                {{ \App\Models\Master\EmpShift::DAY_LABELS[$d->format('l')] }}<br><span class="font-mono normal-case">{{ $d->format('d/m/Y') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fmRows as $row)
                        <tr wire:key="fm-{{ $row['wc']->work_center_id }}-{{ $row['shift']?->shift_id ?? 0 }}">
                            @if ($row['first'])
                                <td rowspan="{{ $row['span'] }}" class="border border-[var(--rs-border)] px-2 py-1.5 text-center font-mono text-[var(--rs-muted)] align-middle">{{ ++$fmUnitNo }}</td>
                                <td rowspan="{{ $row['span'] }}" class="border border-[var(--rs-border)] px-2 py-1.5 font-bold uppercase align-middle">{{ $row['wc']->work_center_name }}</td>
                            @endif
                            <td class="border border-[var(--rs-border)] px-2 py-1.5 align-middle">
                                @if ($row['shift'])
                                    <div class="font-semibold uppercase">{{ preg_replace('/^TM Production /i', '', $row['shift']->shift_name) }}</div>
                                    <div class="text-[10px] font-mono text-[var(--rs-muted)]">{{ implode(' · ', $row['shift']->summaryLines()) }}</div>
                                @else
                                    <span class="text-[var(--rs-muted)]">—</span>
                                @endif
                            </td>
                            @foreach ($fmDates as $d)
                                <td wire:click="openCell({{ $row['wc']->work_center_id }}, {{ $row['shift']?->shift_id ?? 'null' }}, '{{ $d->toDateString() }}')"
                                    class="border border-[var(--rs-border)] px-2 py-1.5 text-center align-middle cursor-pointer hover:bg-[var(--rs-accent-soft)] {{ $d->isSunday() ? 'bg-[var(--rs-surface-alt)]' : '' }}">
                                    @forelse ($row['cells'][$d->toDateString()] ?? [] as $p)
                                        <span title="{{ $p['name'] }} · {{ $p['code'] }}{{ $p['multi'] ? ' · juga di titik lain hari ini' : '' }}"
                                            class="whitespace-nowrap uppercase">{{ $p['label'] }}@if ($p['multi'])<sup class="text-[var(--rs-warn)]">⇄</sup>@endif{{ $loop->last ? '' : ',' }}</span>
                                    @empty
                                        <span class="text-[var(--rs-muted)] opacity-50">{{ $row['shift'] ? '·' : '+' }}</span>
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 3 + count($fmDates) }}" class="border border-[var(--rs-border)] px-3 py-6 text-center text-[var(--rs-muted)]">
                                Minggu ini belum ada formasi. Centang "Tampilkan semua unit kerja" lalu klik sel untuk mengisi, atau pakai "Salin minggu lalu".
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-3.5 py-2 border-t border-[var(--rs-border)] text-[11px] text-[var(--rs-muted)] flex gap-4 flex-wrap">
            <span>Nama = nama panggilan (arahkan kursor untuk nama lengkap)</span>
            <span><sup class="text-[var(--rs-warn)]">⇄</sup> = di hari yang sama juga bertugas di titik lain (mis. borongan yang dipanggil saat kerja berlebih)</span>
            <span class="ml-auto">Data yang sama dipakai papan Roster Mingguan, presensi & payroll</span>
        </div>
    </section>
</div>

@if ($showCellModal)
    <div class="fixed inset-0 bg-black/50 z-[70] p-5 overflow-auto" wire:click.self="$set('showCellModal', false)">
        <div class="max-w-[560px] mx-auto rounded-xl border border-[var(--rs-border)] bg-[var(--rs-surface)] text-[var(--rs-ink)]">
            <div class="flex items-center gap-2.5 px-4 py-3 border-b border-[var(--rs-border)]">
                <div>
                    <h3 class="m-0 text-base font-bold uppercase tracking-wide">{{ $this->currentCellCenter()?->work_center_name }}</h3>
                    <div class="text-[11px] text-[var(--rs-muted)]">{{ \Carbon\Carbon::parse($cell_date)->translatedFormat('l, d F Y') }}</div>
                </div>
                <button type="button" wire:click="$set('showCellModal', false)" class="ml-auto text-xl leading-none cursor-pointer text-[var(--rs-muted)]">×</button>
            </div>

            <div class="p-4 flex flex-col gap-3">
                <x-form.select label="Jam Kerja" wire:model.live="cell_shift" liveSync :selected="$cell_shift" :placeholder="'— pilih shift —'"
                    :options="$this->shifts->pluck('shift_name', 'shift_id')" />

                <div>
                    <div class="text-[11px] font-semibold text-[var(--rs-muted)] mb-1">Yang bertugas ({{ count($cell_ids) }})</div>
                    <div class="flex flex-wrap gap-1.5 min-h-[28px]">
                        @forelse ($this->cellPeople as $e)
                            <span wire:key="cell-p-{{ $e->employee_id }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] bg-[var(--rs-accent-soft)] border border-[var(--rs-border)]">
                                <b class="uppercase">{{ $e->nickname ?: $e->full_name }}</b>
                                <span class="text-[var(--rs-muted)]">{{ $e->employee_code }}</span>
                                <button type="button" wire:click="removeFromCell({{ $e->employee_id }})" class="cursor-pointer text-[var(--rs-bad)] leading-none" title="Keluarkan">×</button>
                            </span>
                        @empty
                            <span class="text-[11px] text-[var(--rs-muted)]">Belum ada — cari nama di bawah.</span>
                        @endforelse
                    </div>
                </div>

                <div>
                    <x-form.input label="Tambah orang" wire:model.live.debounce.250ms="cell_search" placeholder="Cari nama / panggilan / kode…" />
                    <div class="mt-1.5 max-h-[210px] overflow-auto border border-[var(--rs-border)] rounded-md">
                        @foreach ($this->cellCandidates as $e)
                            <button type="button" wire:key="cell-c-{{ $e->employee_id }}" wire:click="addToCell({{ $e->employee_id }})"
                                class="w-full text-left px-2.5 py-1.5 text-[11.5px] flex gap-2 items-center cursor-pointer hover:bg-[var(--rs-accent-soft)] border-b border-[var(--rs-border)] last:border-b-0">
                                <b>{{ $e->full_name }}</b>
                                @if ($e->nickname)<span class="text-[var(--rs-muted)]">({{ $e->nickname }})</span>@endif
                                <span class="ml-auto text-[10.5px] text-[var(--rs-muted)]">{{ $e->position?->position_name }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <x-form.toggle-group label="Berlaku untuk">
                    <x-form.toggle-option wire:click="$set('cell_scope', 'day')" :active="$cell_scope === 'day'">Hari ini saja</x-form.toggle-option>
                    <x-form.toggle-option wire:click="$set('cell_scope', 'rest')" :active="$cell_scope === 'rest'">Sampai akhir minggu</x-form.toggle-option>
                    <x-form.toggle-option wire:click="$set('cell_scope', 'week')" :active="$cell_scope === 'week'">Seluruh minggu</x-form.toggle-option>
                </x-form.toggle-group>
                <p class="m-0 text-[10.5px] text-[var(--rs-muted)]">Hari libur shift (mis. Minggu) dilewati otomatis. Isi sel pada hari-hari itu diganti persis dengan daftar di atas.</p>
            </div>

            <div class="flex gap-2 justify-end px-4 py-3 border-t border-[var(--rs-border)]">
                <button type="button" wire:click="$set('showCellModal', false)" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Batal</button>
                @unless ($fmLocked)
                    <button type="button" wire:click="saveCell" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-accent text-white">Simpan</button>
                @endunless
            </div>
        </div>
    </div>
@endif
