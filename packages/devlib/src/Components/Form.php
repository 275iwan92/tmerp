<?php

namespace Devlib\Components;

// Induk form (mockup UIUX_components_part1.html): judul + isi (kolom/baris field) + kaki berisi tombol tambahan opsional dan tombol split aksi.
// Simpan: isian wajib dicek (tab berisi error dibuka & diberi tanda), x-dl::lines ikut divalidasi; di dalam modal/drawer ditutup setelah sukses.
// mode="view": tampil sebagai teks (fieldset disabled), tombol Edit -> bisa diubah, Batal mengembalikan nilai semula. Event dl:form-submit.
// panel="top" (Single Form, user 2026-09-30): panel aksi menempel di atas -- judul, badge status, lalu tombol, Back di ujung kanan; tombol baku menurut kind+status;
// layar TIDAK menyusun tombol sendiri. kind=master: Nonaktifkan/Aktifkan + New + Save. kind=document: draft = Delete + New + Confirm + Save,
// confirmed = Cancel + New + Email + Print, cancelled = New + Print. Tombol khusus layar lewat slot 'special' (sebelum tombol utama).
// Isi diusahakan muat 1 layar; lebih dari itu -> :tabs ['key' => 'Judul'] + slot per key (tanpa tabs = isi $slot di tab 'Data').
// Tab read-only 'Riwayat' SELALU ditambahkan objek: dibuat oleh + tanggal-jam, terakhir diubah oleh + tanggal-jam (user 2026-09-30).
class Form extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public ?string $action = null,
        public string $method = 'POST',
        public array $actions = ['Save' => '💾', 'Download' => '📥', 'Export' => '📤'],
        public ?string $extraAction = null,
        public string $extraIcon = '📱',
        public bool $footer = true,
        public bool $guard = true,
        public string $mode = 'edit',
        public ?string $panel = null,
        public ?string $back = null,
        public ?string $newUrl = null,
        public string $kind = 'master',
        public string $status = 'active',
        public array $tabs = [],
        public ?string $createdBy = null,
        public ?string $createdAt = null,
        public ?string $updatedBy = null,
        public ?string $updatedAt = null,
    ) {}

    public function panelTabs(): array
    {
        return ($this->tabs ?: ['main' => 'Data']) + ['audit' => ['Riwayat', 'fa-clock-rotate-left']];
    }

    // dd-Mmm-yyyy HH:mm, sama dgn tanggal grid.
    public function stamp(?string $at): string
    {
        return $at ? \Illuminate\Support\Carbon::parse($at)->format('d-M-Y H:i') : '—';
    }

    // [preset|null, label|null, variant|null (null = warna preset), icon|null, act] urut kiri -> kanan (user 2026-09-30):
    // ... + New Record, Confirm, Save paling kanan; Back di ujung kanan terpisah (dirender di luar daftar ini).
    public function panelActions(): array
    {
        $new = ['new', null, 'secondary', null, 'new'];
        $print = [null, \Devlib\Locale::lang() === 'en' ? 'Print' : 'Cetak', 'primary', 'print', 'print'];

        return match ($this->kind.'.'.$this->status) {
            'master.active' => [[null, 'Nonaktifkan', 'secondary', 'fa-toggle-off', 'deactivate'], $new, ['save', null, null, null, 'save']],
            'master.inactive' => [[null, 'Aktifkan', 'secondary', 'fa-toggle-on', 'activate'], $new, ['save', null, null, null, 'save']],
            'document.draft' => [['delete', null, null, null, 'delete'], $new, ['confirm', null, null, null, 'confirm'], ['save', null, null, null, 'save']],
            'document.confirmed' => [['cancel', null, 'danger', null, 'cancel'], $new, ['email', null, null, null, 'email'], $print],
            default => [$new, $print],
        };
    }

    public function statusBadge(): array
    {
        return ['active' => ['Aktif', 'success'], 'inactive' => ['Nonaktif', 'danger'], 'draft' => ['Draft', 'neutral'], 'confirmed' => ['Confirmed', 'success'], 'cancelled' => ['Cancelled', 'danger']][$this->status] ?? [$this->status, 'neutral'];
    }

    public function tag(): string
    {
        return 'x-dl::form';
    }

    public function render()
    {
        return <<<'BLADE'
            <form {{ $attributes->class(['dl-form', 'dl-form--panel' => $panel]) }} {!! $dl() !!} method="{{ $method === 'GET' ? 'GET' : 'POST' }}" @if ($action) action="{{ $action }}" @endif data-dl-form data-mode="{{ $mode }}" @if ($guard) data-dl-guard @endif novalidate>
                @if ($method !== 'GET')
                    @csrf
                @endif
                @if ($panel)
                    <div class="dl-form__panel">
                        <div class="dl-form__title">{{ $title }}</div>
                        <x-dl::badge :label="$statusBadge()[0]" :tone="$statusBadge()[1]" />
                        <span class="dl-print__spacer"></span>
                        @foreach ($panelActions() as [$pre, $lbl, $var, $ico, $act])
                            @if ($loop->last && isset($special)){{ $special }}@endif
                            <x-dl::button size="sm" :preset="$pre" :label="$lbl ?? ''" :variant="$var ?? 'primary'" :icon="$ico" :type="$act === 'save' ? 'submit' : 'button'" :href="$act === 'new' ? $newUrl : null" data-f-act="{{ $act }}" />
                        @endforeach
                        <span class="dl-form__sep"></span>
                        <x-dl::button variant="secondary" size="sm" icon="back" label="Back" :href="$back" data-f-act="back" />
                    </div>
                @elseif ($title !== '')
                    <div class="dl-form__head"><div class="dl-form__title">{{ $title }}</div>@if ($mode === 'view')<x-dl::button variant="secondary" size="sm" icon="edit" label="Edit" data-f-edit />@endif</div>
                @endif
                @if ($panel)
                    <fieldset class="dl-form__fs" data-f-fs @disabled($mode === 'view')><div class="dl-form__body dl-tabs" data-dl-tabs>
                        <div class="dl-tabs__bar" role="tablist">
                            @foreach ($panelTabs() as $key => $t)
                                <button type="button" class="dl-tabs__tab {{ $loop->first ? 'is-active' : '' }}" data-tab="{{ $key }}">@if (is_array($t))<i class="fa-solid {{ $t[1] }}"></i> {{ $t[0] }}@else{{ $t }}@endif</button>
                            @endforeach
                        </div>
                        @foreach ($panelTabs() as $key => $t)
                            <div class="dl-tabs__panel {{ $loop->first ? 'is-active' : '' }}" data-panel="{{ $key }}">
                                @if ($key === 'audit')
                                    <div class="dl-form__audit">
                                        <x-dl::field.display label="Dibuat oleh" :value="$createdBy" />
                                        <x-dl::field.display label="Dibuat pada" :value="$stamp($createdAt)" />
                                        <x-dl::field.display label="Terakhir diubah oleh" :value="$updatedBy" />
                                        <x-dl::field.display label="Terakhir diubah pada" :value="$stamp($updatedAt)" />
                                    </div>
                                @elseif ($key === 'main' && ! $tabs){{ $slot }}@else{{ ${$key} ?? '' }}@endif
                            </div>
                        @endforeach
                    </div></fieldset>
                @else
                    <fieldset class="dl-form__fs" data-f-fs @disabled($mode === 'view')><div class="dl-form__body">{{ $slot }}</div></fieldset>
                @endif
                @if ($footer && ! $panel)
                    <div class="dl-form__foot {{ $extraAction ? '' : 'is-single' }}">
                        @if ($extraAction)
                            <x-dl::button variant="success" :icon="$extraIcon" :label="$extraAction" block data-dl-form-extra />
                        @endif
                        @if ($mode === 'view')<x-dl::button preset="cancel" data-f-cancel />@endif
                        <x-dl::split-button :actions="$actions" />
                    </div>
                @endif
            </form>
            BLADE;
    }
}
