<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Bingkai dokumen Inquiry Product (tmerp pages/inquiry/form.blade.php — flow SAMA, hanya ditulis ulang sebagai objek):
// bar judul (jenis + no + status + Back) -> catatan -> kartu toolbar (Simpan, Confirm, Batalkan, Tandai Kalah,
// Imposition, Cetak Nota, status) berisi slot "header" -> slot isi (tier, part, tooling, biaya eksternal).
// Aturan (JS InquiryForm): Simpan = cek wajib + beri nomor; Confirm = simpan + SUBMITTED + form terkunci;
// Batalkan/Tandai Kalah hanya setelah Confirm & wajib alasan; Kirim ke ProDev hanya part tersimpan + inkuiri Confirm.
class InquiryForm extends DevlibComponent
{
    // Peran Part (sama dengan partRoleOptions() tmerp).
    public const ROLES = [
        'body' => ['Body', '📦', 'Main structural part'], 'lid' => ['Lid', '🔝', 'Separate lid'], 'base' => ['Base', '⬇️', 'Separate base'],
        'sleeve' => ['Sleeve', '🧦', 'Outer sleeve'], 'tray' => ['Tray', '🍱', 'Inner tray'], 'insert' => ['Insert', '🧩', 'Inner fitment/padding'],
        'divider' => ['Divider', '🗂️', 'Partition between compartments'], 'left' => ['Left', '◀️', 'Left side'], 'right' => ['Right', '▶️', 'Right side'],
        'monoform' => ['MonoForm', '1️⃣', 'Not a container, 1 sheet (poster/label/hang tag) — term from "Mono Carton"'],
    ];

    // Tipe yang wajib memilih Peran Part + templatnya (partTemplateMap() ∩ prod_type.has_multi_part di tmerp).
    public const TEMPLATES = ['BOX_WITH_LID' => ['body', 'lid'], 'ROUND_BOX' => ['body', 'lid'], 'SHALLOW_BOX' => ['body', 'lid']];

    public function __construct(
        public string $mode = 'NEW',
        public array $doc = [],
        public bool $readonly = false,
        public ?string $backUrl = null,
        public array $links = [],
        public ?string $prodevUrl = null,
        public array $urls = [],
    ) {
        $this->doc += ['id' => null, 'inquiry_no' => null, 'sub_state' => 'DRAFT', 'stat_confirm_sales' => false, 'stat_cancel' => false];
    }

    public function tag(): string
    {
        return 'x-mp::sales.inquiry-form mode='.$this->mode;
    }

    public function source(): string
    {
        return Src::of('tm2sdi.iqp_inquiry');
    }

    public function roles(): array
    {
        return self::ROLES;
    }

    public function config(): array
    {
        return ['mode' => $this->mode, 'doc' => $this->doc, 'readonly' => $this->readonly, 'templates' => self::TEMPLATES,
            'roles' => array_map(fn ($r) => $r[0], self::ROLES), 'prodevUrl' => $this->prodevUrl, 'yy' => date('y'),
            'urls' => $this->urls + ['csrf' => csrf_token()]];
    }

    public function render()
    {
        return <<<'BLADE'
            @php($d = $doc)
            <div {{ $attributes->class('mp-inq') }} {!! $dl() !!} data-mp-inquiry data-dl-guard data-config="{{ json_encode($config()) }}">
                <x-mp::sales.doc-bar :mode="$mode" :no="$d['inquiry_no']" :state="$d['sub_state']" :back-url="$backUrl" />

                <x-dl::alert tone="success" data-inq-flash />
                @if ($readonly)
                    <x-dl::alert tone="warn">🔒 {!! strtr(e(\Devlib\Lang::text('This inquiry is already confirmed (status :status) — opened view-only, it cannot be edited.')), [':status' => '<b>'.e($d['sub_state']).'</b>']) !!}</x-dl::alert>
                @else
                    <x-dl::alert tone="warn" data-inq-officer>👤 <b>Sales Officer</b> — {{ \Devlib\Lang::text('fill in the header, qty tiers, part & dieline, tooling, discount and external costs below.') }} {{ \Devlib\Lang::text('Costing (COGS) & the speed estimate will follow in the next iteration.') }}</x-dl::alert>
                @endif

                <x-dl::card class="mp-inq__main">
                    <div class="mp-toolbar">
                        <x-dl::button preset="save" type="button" data-inq-save />
                        <x-dl::button preset="confirm" data-inq-confirm :hidden="$d['stat_confirm_sales']" />
                        <x-dl::button variant="danger" icon="✖" label="Cancel" data-dl-open="inq-cancel" data-inq-keep data-inq-when="cancel" :hidden="! ($d['stat_confirm_sales'] && ! $d['stat_cancel'])" />
                        <x-dl::button variant="danger" icon="❌" label="Mark Lost" data-dl-open="inq-lost" data-inq-keep data-inq-when="lost"
                            :hidden="! ($d['stat_confirm_sales'] && ! in_array($d['sub_state'], ['CANCELLED', 'CONVERTED', 'LOST'], true))" />
                        {{-- Duplicate ke customer lain (user 2026-10-01: pindah dari baris grid); modal dup-modal dipasang layar. --}}
                        <x-dl::button variant="secondary" icon="copy" label="Duplicate" type="button" data-dl-open="dup-modal" data-inq-keep data-inq-saved-only :hidden="! $d['id']" />
                        <span class="mp-grow"></span>
                        @foreach ($links as $label => $url)
                            <x-dl::button variant="secondary" size="sm" :label="$label" :href="$url" data-inq-keep data-inq-saved-only :hidden="! $d['id']" />
                        @endforeach
                        <x-dl::badge :label="$d['sub_state']" tone="neutral" data-inq-state />
                    </div>
                    {{ $header ?? '' }}
                </x-dl::card>

                {{ $slot }}

                <x-dl::modal id="inq-role" title="Choose Part Role" icon="fa-tag" size="lg">
                    <x-dl::hint note>{{ \Devlib\Lang::text('The part name is filled in automatically: product-name-{role}') }}</x-dl::hint>
                    <div class="mp-roles">
                        @foreach ($roles() as $key => [$label, $icon, $desc])
                            <button type="button" class="mp-role" data-inq-role-pick="{{ $key }}"><span class="mp-role__icon">{{ $icon }}</span><b>{{ $label }}</b><small>{{ \Devlib\Lang::text($desc) }}</small></button>
                        @endforeach
                    </div>
                </x-dl::modal>

                @foreach (['inq-cancel' => ['Cancel Inquiry', 'cancel_reason', 'Cancellation Reason', 'Required — recorded in the history', 'Yes, cancel the inquiry'],
                           'inq-lost' => ['Mark Lost', 'lost_reason', 'Lost Reason', 'e.g. Competitor price is lower, customer cancelled the order — required, recorded in the history & the win-rate report', 'Yes, mark as lost']] as $id => [$mt, $field, $fl, $ph, $ok])
                    <x-dl::modal :id="$id" :title="$mt" icon="warning">
                        <x-dl::field.text :name="$field" :label="$fl" :placeholder="$ph" multiline required data-inq-keep :source="\MockupParts\Src::of('tm2sdi.iqp_inquiry.cancel_reason')" />
                        <x-slot:footer>
                            <x-dl::button preset="cancel" data-dl-close-modal data-inq-keep />
                            <x-dl::button variant="danger" :label="$ok" data-inq-end="{{ $id === 'inq-cancel' ? 'CANCELLED' : 'LOST' }}" data-inq-keep />
                        </x-slot:footer>
                    </x-dl::modal>
                @endforeach

                <x-dl::modal id="inq-newcust" title="Create New Customer" icon="add">
                    <x-dl::alert tone="info">{!! \Devlib\Lang::text('The customer is created with status <b>DRAFT</b> — Finance/Credit still needs to do a full review in Master Customer before full transactions are possible.') !!}</x-dl::alert>
                    <x-dl::field.text name="new_customer_name" label="Customer Name" placeholder="Company/customer name" required :source="\MockupParts\Src::of('tm2mst.cust_customer.customer_name')" />
                    <x-dl::field.toggle-group name="new_customer_type" label="Customer Type" :options="['local' => \Devlib\Lang::text('Local'), 'import' => \Devlib\Lang::text('Import')]" value="local" :source="\MockupParts\Src::of('tm2mst.cust_customer.customer_type')" />
                    <x-dl::field.text name="new_customer_phone" label="Phone No." placeholder="0812xxxxxxxx" :source="\MockupParts\Src::of('tm2mst.cust_customer.phone')" />
                    <x-dl::field.text name="new_customer_email" label="Email" placeholder="optional" :case-toggle="false" :source="\MockupParts\Src::of('tm2mst.cust_customer.email')" />
                    <x-dl::field.text name="new_customer_address" label="Address" placeholder="optional" multiline :source="\MockupParts\Src::of('tm2mst.cust_customer.default_address')" />
                    <x-slot:footer>
                        <x-dl::button preset="cancel" data-dl-close-modal />
                        <x-dl::button preset="save" type="button" data-inq-newcust-save />
                    </x-slot:footer>
                </x-dl::modal>
            </div>
            BLADE;
    }
}
