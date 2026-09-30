<?php

namespace Devlib\Components;

// Induk form (mockup UIUX_components_part1.html): judul + isi (kolom/baris field) + kaki berisi tombol tambahan opsional dan tombol split aksi.
// Save: isian wajib dicek (tab berisi error dibuka & diberi tanda), x-dl::lines ikut divalidasi; di dalam modal/drawer ditutup setelah sukses.
// mode="view": tampil sebagai teks (fieldset disabled), tombol Edit -> bisa diubah, Cancel mengembalikan nilai semula. Event dl:form-submit.
// panel="top" (Single Form, user 2026-09-30): panel aksi menempel di atas -- judul, badge status, lalu tombol, Back di ujung kanan; tombol baku menurut kind+status;
// layar TIDAK menyusun tombol sendiri. kind=master: Deactivate/Activate + New + Undo + Save. kind=document: draft = Delete + New + Undo + Confirm + Save,
// confirmed = Cancel + Back to Draft (hanya :can-reopen) + New + Email + Print, cancelled = New + Duplicate + Print, closed = New + Print. status=new (form sama tapi kosong) = Undo + Save. Tombol khusus layar lewat slot 'special' (sebelum tombol utama).
// Isi diusahakan muat 1 layar; lebih dari itu -> :tabs ['key' => 'Judul'] + slot per key (tanpa tabs = isi $slot di tab 'Data').
// Master-Detail: slot strip = ringkasan kunci di atas tab (No., tanggal, pihak, Grand Total besar); tab = field header;
// slot detail = baris item di tengah (x-dl::lines, footer total ikut di dalamnya).
// Approval: :approval-levels (1-3), :approval-at (tingkat yang menunggu), :can-approve (user = pemeriksa tingkat itu).
//   status approval (pemeriksa: Approve/Reject/Hold; pembuat hanya Cancel), hold, rejected (berhenti; Duplicate), approved;
//   tanpa Back to Draft setelah masuk approval; Closed otomatis saat dokumen diteruskan ke modul berikutnya (mis. PR -> PO).
// :action-field = nama field aksi yang dikirim (default _action; layar lama mis. 'action'); :hide = aksi panel yang disembunyikan.
// :subtitle = baris kecil di bawah judul (mis. supplier); :pipeline = bar tahapan Draft > Confirmed > Closed (Master-Detail).
// Tab read-only 'History' SELALU ditambahkan objek: created by/at, last updated by/at + :log = [['at','by','action','note'], ...]
// (log riwayat: Created, Saved, Confirmed, Back to Draft, Cancelled, Closed -- terbaru di atas) (user 2026-09-30).
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
        public bool $canReopen = false,
        public bool $canInput = true,
        public ?string $subtitle = null,
        public bool $pipeline = false,
        public array $log = [],
        public int $approvalLevels = 0,
        public int $approvalAt = 0,
        public bool $canApprove = false,
        public string $actionField = '_action',
        public array $hide = [],
        public array $hidden = [],
    ) {}

    public function panelTabs(): array
    {
        return ($this->tabs ?: ['main' => 'Data']) + ['audit' => ['History', 'fa-clock-rotate-left']];
    }

    // dd-Mmm-yyyy HH:mm, sama dgn tanggal grid.
    public function stamp(?string $at): string
    {
        return $at ? \Illuminate\Support\Carbon::parse($at)->format('d-M-Y H:i') : '—';
    }

    // [preset|null, label|null, variant|null (null = warna preset), icon|null, act] urut kiri -> kanan (user 2026-09-30):
    // ... + New Record, Confirm, Save paling kanan; Back di ujung kanan terpisah (dirender di luar daftar ini).
    // canInput=false (user 2026-09-30): pemeriksa -- level approve / Back to Draft (manager ke atas, admin-app) -- TIDAK boleh
    // input: isi selalu terkunci, tanpa Save/Delete/Confirm/New; tetap boleh Cancel, Back to Draft, Email, Print. super-admin (UAT) bebas.
    public function panelActions(): array
    {
        // :hide = aksi yang tidak didukung layar/backend-nya (mis. Cancel belum ada di modul itu) -- disembunyikan dari panel.
        $all = array_values(array_filter($this->baseActions(), fn ($a) => ! in_array($a[4], $this->hide, true)));

        return $this->canInput ? $all : array_values(array_filter($all, fn ($a) => ! in_array($a[4], ['save', 'undo', 'delete', 'confirm', 'new', 'duplicate', 'cancel'], true)));
    }

    // Istilah tombol & status SELALU English (user 2026-09-30, supaya sama bagi user & developer):
    // tombol New, Save, Undo, Delete, Confirm, Cancel, Duplicate, Back to Draft, Back; status Draft, Confirmed, Cancelled, Closed.
    private function baseActions(): array
    {
        $b = fn (string $act, ?string $preset, string $label, ?string $variant = null, ?string $icon = null) => [$preset, $label, $variant, $icon, $act];
        $new = $b('new', 'new', 'New', 'secondary', 'fa-plus');
        $save = $b('save', 'save', 'Save');
        $undo = $b('undo', null, 'Undo', 'secondary', 'undo');
        $dup = $b('duplicate', null, 'Duplicate', 'secondary', 'fa-copy');
        $print = $b('print', null, 'Print', 'primary', 'print');

        return match ($this->kind.'.'.$this->status) {
            // Data baru (belum pernah disimpan): Undo + Save saja -- Delete/Confirm/Print belum ada artinya.
            'master.new', 'document.new' => [$undo, $save],
            'master.active' => [$b('deactivate', null, 'Deactivate', 'secondary', 'fa-toggle-off'), $new, $undo, $save],
            'master.inactive' => [$b('activate', null, 'Activate', 'secondary', 'fa-toggle-on'), $new, $undo, $save],
            'document.draft' => [$b('delete', 'delete', 'Delete'), $new, $undo, $b('confirm', 'confirm', 'Confirm'), $save],
            // Back to Draft tepat di sebelah Cancel, HANYA bila layar memberi :can-reopen (super-admin, admin-app, level manager
            // ke atas) -- server tetap wajib cek hak yang sama saat aksi diterima.
            // Approval berjenjang (user 2026-09-30): Approve/Reject hanya dari pemeriksa tingkat berjalan; Cancel hanya dari user pembuat.
            // Keputusan pemeriksa: Approve / Reject / Hold (Hold = tunda di tingkat itu, lalu tetap diputuskan Approve/Reject).
            // Setelah masuk approval TIDAK ada Back to Draft. Rejected = berhenti (pembuat pakai Duplicate).
            'document.approval' => $this->canApprove ? [$b('reject', 'reject', 'Reject', 'danger'), $b('hold', 'hold', 'Hold', 'secondary'), $b('approve', 'approve', 'Approve')] : [$b('cancel', 'cancel', 'Cancel', 'danger'), $new, $print],
            'document.hold' => $this->canApprove ? [$b('reject', 'reject', 'Reject', 'danger'), $b('approve', 'approve', 'Approve')] : [$b('cancel', 'cancel', 'Cancel', 'danger'), $new, $print],
            'document.rejected' => [$new, $dup, $print],
            'document.approved' => [$b('cancel', 'cancel', 'Cancel', 'danger'), $new, $b('email', 'email', 'Email'), $print],
            'document.confirmed' => array_values(array_filter([$b('cancel', 'cancel', 'Cancel', 'danger'), $this->canReopen ? $b('draft', null, 'Back to Draft', 'secondary', 'fa-rotate-left') : null, $new, $b('email', 'email', 'Email'), $print])),
            // Cancelled: Duplicate = Draft baru berisi salinan isi (tanpa nomor) supaya tidak mengetik ulang (user 2026-09-30).
            'document.cancelled' => [$new, $dup, $print],
            default => [$new, $print],  // closed
        };
    }

    // Sesudah Confirm / Cancel / Close isi terkunci (tampil sebagai teks); ubah lagi hanya lewat Back to Draft.
    public function locked(): bool
    {
        return $this->panel && (! $this->canInput || in_array($this->status, ['confirmed', 'approval', 'hold', 'approved', 'rejected', 'cancelled', 'closed'], true));
    }

    // Bar tahapan (pola form PO Master-Detail yang disetujui user): Draft > Confirmed > Closed; Cancelled = tahap merah.
    public function steps(): array
    {
        if ($this->approvalLevels > 0) {
            return $this->approvalSteps();
        }
        $order = ['draft', 'confirmed', 'closed'];
        $at = $this->status === 'new' ? 0 : array_search($this->status === 'cancelled' ? 'confirmed' : $this->status, $order, true);
        $steps = [];
        foreach ($order as $i => $k) {
            $steps[] = [ucfirst($k), $i < $at ? 'is-done' : ($i === $at ? ($this->status === 'cancelled' ? 'is-rejected' : 'is-active') : '')];
        }
        if ($this->status === 'cancelled') {
            $steps[1][0] = 'Cancelled';
        }

        return $steps;
    }

    // Draft > Confirmed > Approval 1..n > Approved > Closed. Rejected / Cancelled = tahap merah di posisi terjadinya.
    private function approvalSteps(): array
    {
        $n = $this->approvalLevels;
        $labels = ['Draft', 'Confirmed', ...array_map(fn ($i) => 'Approval '.$i, range(1, $n)), 'Approved', 'Closed'];
        $lv = max(1, min($n, $this->approvalAt));
        $at = match ($this->status) {
            'new', 'draft' => 0, 'confirmed' => 1, 'approval', 'hold', 'rejected' => 1 + $lv, 'approved' => $n + 2, 'closed' => $n + 3, 'cancelled' => $this->approvalAt ? 1 + $lv : 1, default => 0,
        };
        $red = in_array($this->status, ['rejected', 'cancelled'], true);
        $hold = $this->status === 'hold';
        $steps = [];
        foreach ($labels as $i => $l) {
            $name = $i === $at && ($red || $hold) ? ucfirst($this->status).($this->status === 'cancelled' ? '' : ' L'.$lv) : $l;
            $steps[] = [$name, $i < $at ? 'is-done' : ($i === $at ? ($red ? 'is-rejected' : ($hold ? 'is-hold' : ($this->status === 'closed' ? 'is-done' : 'is-active'))) : '')];
        }

        return $steps;
    }

    public function statusBadge(): array
    {
        return ['new' => ['Draft', 'neutral'], 'draft' => ['Draft', 'neutral'], 'confirmed' => ['Confirmed', 'success'], 'cancelled' => ['Cancelled', 'danger'], 'closed' => ['Closed', 'neutral'], 'approval' => ['Waiting Approval '.$this->approvalAt, 'warn'], 'hold' => ['Hold L'.$this->approvalAt, 'warn'], 'approved' => ['Approved', 'success'], 'rejected' => ['Rejected', 'danger'], 'active' => ['Active', 'success'], 'inactive' => ['Inactive', 'danger']][$this->status] ?? [$this->status, 'neutral'];
    }

    public function tag(): string
    {
        return 'x-dl::form';
    }

    public function render()
    {
        return <<<'BLADE'
            <form {{ $attributes->class(['dl-form', 'dl-form--panel' => $panel, 'is-view' => $panel && $locked()]) }} {!! $dl() !!} method="{{ $method === 'GET' ? 'GET' : 'POST' }}" @if ($action) action="{{ $action }}" @endif data-dl-form data-mode="{{ $mode }}" data-action-field="{{ $actionField }}" @if ($guard) data-dl-guard @endif novalidate>
                @if ($method !== 'GET')
                    @csrf
                @endif
                @if ($panel)
                    <div class="dl-form__panel">
                        <div class="dl-form__heading"><div class="dl-form__title">{{ $title }}</div>@if ($subtitle)<small>{{ $subtitle }}</small>@endif</div>
                        {{-- :pipeline = tahapan ringkas di panel (menggantikan badge), bukan baris penuh (user 2026-09-30: compact). --}}
                        @if ($pipeline)
                            <div class="dl-form__steps" aria-label="Status">@foreach ($steps() as [$sl, $sc])<span class="dl-form__step {{ $sc }}">{{ $sl }}</span>@endforeach</div>
                        @else
                            <x-dl::badge :label="$statusBadge()[0]" :tone="$statusBadge()[1]" />
                        @endif
                        <span class="dl-print__spacer"></span>
                        @foreach ($panelActions() as $a)
                            @php [$pre, $lbl, $var, $ico, $act] = $a; $cls = $a[5] ?? ''; @endphp
                            @if ($loop->last && isset($special)){{ $special }}@endif
                            @if ($act === 'save')
                                <x-dl::button size="sm" :preset="$pre" :label="$lbl" :variant="$var ?? 'primary'" type="submit" :name="$actionField" value="save" data-f-act="save" />
                            @else
                                <x-dl::button size="sm" :preset="$pre" :label="$lbl ?? ''" :variant="$var ?? 'primary'" :icon="$ico" :href="$act === 'new' ? $newUrl : null" :class="$cls" data-f-act="{{ $act }}" />
                            @endif
                        @endforeach
                        @if (! $panelActions() && isset($special)){{ $special }}@endif
                        <span class="dl-form__sep"></span>
                        <x-dl::button variant="secondary" size="sm" icon="back" label="Back" :href="$back" data-f-act="back" />
                    </div>
                @elseif ($title !== '')
                    <div class="dl-form__head"><div class="dl-form__title">{{ $title }}</div>@if ($mode === 'view')<x-dl::button variant="secondary" size="sm" icon="edit" label="Edit" data-f-edit />@endif</div>
                @endif
                @foreach ($hidden as $hk => $hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
                @if ($panel)
                    {{-- Terkunci (Confirmed/Cancelled/Closed/read-only) = hanya ISIAN yang dimatikan (fieldset per bagian);
                         tombol tab header & footer TIDAK di dalam fieldset, jadi tetap bisa diklik di status apa pun. --}}
                    @isset($strip)<fieldset class="dl-form__fs" data-f-fs @disabled($mode === 'view' || $locked())><div class="dl-form__strip">{{ $strip }}</div></fieldset>@endisset
                    <div class="dl-form__body dl-tabs" data-dl-tabs>
                        <div class="dl-tabs__bar" role="tablist">
                            @foreach ($panelTabs() as $key => $t)
                                <button type="button" class="dl-tabs__tab {{ $loop->first ? 'is-active' : '' }}" data-tab="{{ $key }}">@if (is_array($t))<i class="fa-solid {{ $t[1] }}"></i> {{ $t[0] }}@else{{ $t }}@endif</button>
                            @endforeach
                        </div>
                        @foreach ($panelTabs() as $key => $t)
                            <div class="dl-tabs__panel {{ $loop->first ? 'is-active' : '' }}" data-panel="{{ $key }}"><fieldset class="dl-form__fs" data-f-fs @disabled($mode === 'view' || $locked())>
                                @if ($key === 'audit')
                                    <div class="dl-form__audit">
                                        <x-dl::field.display label="Created by" :value="$createdBy" />
                                        <x-dl::field.display label="Created at" :value="$stamp($createdAt)" />
                                        <x-dl::field.display label="Last updated by" :value="$updatedBy" />
                                        <x-dl::field.display label="Last updated at" :value="$stamp($updatedAt)" />
                                    </div>
                                    @if ($log)
                                        <div class="dl-form__logwrap"><table class="dl-form__log">
                                            <thead><tr><th>Date &amp; time</th><th>By</th><th>Action</th><th>Note</th></tr></thead>
                                            <tbody>@foreach ($log as $e)<tr><td>{{ $stamp($e['at'] ?? null) }}</td><td>{{ $e['by'] ?? '' }}</td><td><span class="dl-form__logact dl-form__logact--{{ strtolower(str_replace(' ', '-', $e['action'] ?? '')) }}">{{ $e['action'] ?? '' }}</span></td><td>{{ $e['note'] ?? '' }}</td></tr>@endforeach</tbody>
                                        </table></div>
                                    @endif
                                @elseif ($key === 'main' && ! $tabs){{ $slot }}@else{{ ${$key} ?? '' }}@endif
                            </fieldset></div>
                        @endforeach
                    </div>
                    {{-- Master-Detail: detail selalu tampil di tengah (tidak ikut tab header), footer total di bawahnya. --}}
                    @isset($detail)<div class="dl-form__detail">{{ $detail }}</div>@endisset
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
