<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Bingkai dokumen Inquiry Product (tmerp pages/inquiry/form.blade.php — flow SAMA, hanya ditulis ulang sebagai objek):
// bar judul (jenis + no + status + Back) -> catatan -> kartu toolbar (Simpan, Confirm, Batalkan, Tandai Kalah,
// Imposition, Cetak Nota, status) berisi slot "header" -> slot isi (tier, part, tooling, biaya eksternal).
// Aturan (JS InquiryForm): Simpan = cek wajib + beri nomor; Confirm = simpan + SUBMITTED + form terkunci;
// Batalkan/Tandai Kalah hanya setelah Confirm & wajib alasan; Kirim ke ProDev hanya part tersimpan + inkuiri Confirm.
class InquiryForm extends DevlibComponent
{
    public const TITLES = ['NEW' => ['✨', 'Inkuiri Produk Baru'], 'RECALC' => ['🔁', 'Hitung Ulang Harga'], 'REVISE' => ['✏️', 'Ubah Spesifikasi']];

    // Peran Part (sama dengan partRoleOptions() tmerp).
    public const ROLES = [
        'body' => ['Body', '📦', 'Bagian struktural utama'], 'lid' => ['Lid', '🔝', 'Tutup terpisah'], 'base' => ['Base', '⬇️', 'Alas terpisah'],
        'sleeve' => ['Sleeve', '🧦', 'Selongsong luar'], 'tray' => ['Tray', '🍱', 'Baki dalam'], 'insert' => ['Insert', '🧩', 'Fitment/pengganjal dalam'],
        'divider' => ['Divider', '🗂️', 'Sekat antar-kompartemen'], 'left' => ['Left', '◀️', 'Sisi kiri'], 'right' => ['Right', '▶️', 'Sisi kanan'],
        'monoform' => ['MonoForm', '1️⃣', 'Bukan container, 1 lembar (poster/label/hang tag) — istilah dari "Mono Carton"'],
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
    ) {
        $this->doc += ['id' => null, 'inquiry_no' => null, 'sub_state' => 'DRAFT', 'stat_confirm' => false, 'stat_cancel' => false];
    }

    public function tag(): string
    {
        return 'x-mp::sales.inquiry-form mode='.$this->mode;
    }

    public function source(): string
    {
        return \MockupParts\Src::of('tm2sdi.iqp_inquiry');
    }

    public function title(): array
    {
        return self::TITLES[$this->mode] ?? self::TITLES['NEW'];
    }

    public function roles(): array
    {
        return self::ROLES;
    }

    public function config(): array
    {
        return ['mode' => $this->mode, 'doc' => $this->doc, 'readonly' => $this->readonly, 'templates' => self::TEMPLATES,
            'roles' => array_map(fn ($r) => $r[0], self::ROLES), 'prodevUrl' => $this->prodevUrl, 'yy' => date('y')];
    }

    public function render()
    {
        return <<<'BLADE'
            @php([$emoji, $title] = $title())
            @php($d = $doc)
            <div {{ $attributes->class('mp-inq') }} {!! $dl() !!} data-mp-inquiry data-config="{{ json_encode($config()) }}">
                <div class="mp-docbar">
                    <b>{{ $emoji }} {{ $title }}</b>
                    <x-dl::badge :label="$mode" :tone="['NEW' => 'info', 'RECALC' => 'accent', 'REVISE' => 'warn'][$mode] ?? 'info'" />
                    <span class="mp-docbar__sub"><span data-inq-no>{{ $d['inquiry_no'] ?: 'belum tersimpan' }}</span> · <span data-inq-sub>{{ $d['sub_state'] }}</span></span>
                    <span class="mp-grow"></span>
                    @if ($backUrl)<x-dl::button variant="secondary" size="sm" icon="back" label="Back" :href="$backUrl" data-inq-keep />@endif
                </div>

                <x-dl::alert tone="success" data-inq-flash />
                @if ($readonly)
                    <x-dl::alert tone="warn">🔒 Inkuiri ini sudah dikonfirmasi (status <b>{{ $d['sub_state'] }}</b>) — dibuka lihat-saja, tidak bisa diedit.</x-dl::alert>
                @else
                    <x-dl::alert tone="warn" data-inq-officer>👤 <b>Sales Officer</b> — isi header, tier qty, part &amp; dieline, tooling, diskon, dan biaya eksternal di bawah. Costing (HPP) &amp; estimasi kecepatan akan menyusul di iterasi berikutnya.</x-dl::alert>
                @endif

                <x-dl::card class="mp-inq__main">
                    <div class="mp-toolbar">
                        <x-dl::button preset="save" type="button" data-inq-save />
                        <x-dl::button preset="confirm" data-inq-confirm :hidden="$d['stat_confirm']" />
                        <x-dl::button variant="danger" icon="✖" label="Batalkan" data-dl-open="inq-cancel" data-inq-keep data-inq-when="cancel" :hidden="! ($d['stat_confirm'] && ! $d['stat_cancel'])" />
                        <x-dl::button variant="danger" icon="❌" label="Tandai Kalah" data-dl-open="inq-lost" data-inq-keep data-inq-when="lost"
                            :hidden="! ($d['stat_confirm'] && ! in_array($d['sub_state'], ['CANCELLED', 'CONVERTED', 'LOST'], true))" />
                        <span class="mp-grow"></span>
                        @foreach ($links as $label => $url)
                            <x-dl::button variant="secondary" size="sm" :label="$label" :href="$url" data-inq-keep data-inq-saved-only :hidden="! $d['id']" />
                        @endforeach
                        <x-dl::badge :label="$d['sub_state']" tone="neutral" data-inq-state />
                    </div>
                    {{ $header ?? '' }}
                </x-dl::card>

                {{ $slot }}

                <x-dl::modal id="inq-role" title="Pilih Peran Part" icon="fa-tag" size="lg">
                    <p class="mp-hint">Nama part terisi otomatis: nama-produk-{peran}</p>
                    <div class="mp-roles">
                        @foreach ($roles() as $key => [$label, $icon, $desc])
                            <button type="button" class="mp-role" data-inq-role-pick="{{ $key }}"><span class="mp-role__icon">{{ $icon }}</span><b>{{ $label }}</b><small>{{ $desc }}</small></button>
                        @endforeach
                    </div>
                </x-dl::modal>

                @foreach (['inq-cancel' => ['Batalkan Inkuiri', 'cancel_reason', 'Alasan Pembatalan', 'Wajib diisi — tercatat di riwayat', 'Ya, batalkan inkuiri'],
                           'inq-lost' => ['Tandai Kalah', 'lost_reason', 'Alasan Kalah', 'mis. Harga kompetitor lebih rendah, customer batal order — wajib diisi, tercatat di riwayat & laporan win-rate', 'Ya, tandai kalah']] as $id => [$mt, $field, $fl, $ph, $ok])
                    <x-dl::modal :id="$id" :title="$mt" icon="warning">
                        <x-dl::field.text :name="$field" :label="$fl" :placeholder="$ph" multiline required data-inq-keep :source="\MockupParts\Src::of('tm2sdi.iqp_inquiry.cancel_reason')" />
                        <x-slot:footer>
                            <x-dl::button preset="cancel" data-dl-close-modal data-inq-keep />
                            <x-dl::button variant="danger" :label="$ok" data-inq-end="{{ $id === 'inq-cancel' ? 'CANCELLED' : 'LOST' }}" data-inq-keep />
                        </x-slot:footer>
                    </x-dl::modal>
                @endforeach

                <x-dl::modal id="inq-newcust" title="Buat Customer Baru" icon="add">
                    <x-dl::alert tone="info">Customer dibuat status <b>DRAFT</b> — Finance/Credit tetap perlu review lengkap di Master Customer sebelum bisa transaksi penuh.</x-dl::alert>
                    <x-dl::field.text name="new_customer_name" label="Nama Customer" placeholder="Nama perusahaan/customer" required :source="\MockupParts\Src::of('tm2mst.cust_customer.customer_name')" />
                    <x-dl::field.toggle-group name="new_customer_type" label="Tipe" :options="['local' => 'Lokal', 'import' => 'Import']" value="local" :source="\MockupParts\Src::of('tm2mst.cust_customer.customer_type')" />
                    <x-dl::field.text name="new_customer_phone" label="No. Telp" placeholder="0812xxxxxxxx" :source="\MockupParts\Src::of('tm2mst.cust_customer.phone')" />
                    <x-dl::field.text name="new_customer_email" label="Email" placeholder="opsional" :case-toggle="false" :source="\MockupParts\Src::of('tm2mst.cust_customer.email')" />
                    <x-dl::field.text name="new_customer_address" label="Alamat" placeholder="opsional" multiline :source="\MockupParts\Src::of('tm2mst.cust_customer.default_address')" />
                    <x-slot:footer>
                        <x-dl::button preset="cancel" data-dl-close-modal />
                        <x-dl::button preset="save" type="button" data-inq-newcust-save />
                    </x-slot:footer>
                </x-dl::modal>
            </div>
            BLADE;
    }
}
