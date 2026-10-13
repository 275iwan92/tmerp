<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Satu Part di Inquiry Product (tmerp pages/inquiry/form.blade.php, blok "Part & Dieline" — flow & field SAMA).
// Gerbang: Tipe Produk (header) belum dipilih -> catatan; tipe multi-part & Peran Part belum dipilih -> catatan;
// selain itu isi part tampil: Dieline v3 (dimensi + ukuran pasaran + flat + preview), Bahan, Cetak/Warna, Finishing.
// Perilaku (gerbang, peran, warna turunan, hapus) dijalankan JS InquiryForm di induknya.
class InquiryPart extends DevlibComponent
{
    public const PART_TYPES = ['DC' => 'Paper', 'DC+SHEET' => 'Paper + Sheet', 'DC+SF' => 'Paper + SF', 'SHEET' => 'Sheet', 'KRAFT' => 'Kraft'];

    public function __construct(
        public string $index = '0',
        public array $part = [],
        public array $types = [],
        public ?string $typeCode = null,
        public array $finishingTypes = [],
        public array $materialChains = [],
    ) {
        $this->part += ['id' => null, 'artwork_url' => null, 'artwork_name' => null, 'artwork_inner_url' => null, 'artwork_inner_name' => null, 'part_name' => '', 'part_role' => '', 'part_type' => 'DC', 'qty_per_sku' => 1, 'pack_qty' => '', 'dims' => [], 'cfg_color' => '4/4',
            'cfg_ink' => '', 'material_rows' => [], 'finishings' => [], 'sent' => false, 'saved' => false, 'suggestions' => []];
    }

    // Semua kombinasi sisiA/sisiB 1/0 .. 6/6 (user 2026-09-29).
    public function colors(): array
    {
        $out = [];
        foreach (range(1, 6) as $front) {
            foreach (range(0, 6) as $back) {
                $out[] = "$front/$back";
            }
        }

        return $out;
    }

    // Baris bahan: #1 Paper & #2 Sheet/SF selalu ada (baris bawaan), sisanya baris tambahan (mis. Paper 2 lapis).
    public function rows(): array
    {
        $rows = array_values($this->part['material_rows']);
        foreach ([0, 1] as $c) {
            $rows[$c] = ['chain' => $c] + ($rows[$c] ?? []);
        }

        return $rows;
    }

    public function tag(): string
    {
        return 'x-mp::sales.inquiry-part index='.$this->index;
    }

    public function source(): string
    {
        return Src::of('tm2sdi.iqp_part');
    }

    public function n(string $key): string
    {
        return 'parts['.$this->index.']['.$key.']';
    }

    public function src(string $col): string
    {
        return Src::of('tm2sdi.iqp_part.'.$col);
    }

    // "front: 4 · back: 4 · total: 8 · side: 2SIDE" dari notasi sisiA/sisiB (sama dengan colorBreakdown() tmerp).
    public static function colorBreakdown(?string $code): ?array
    {
        if (! $code || ! preg_match('/^(\d+)\/(\d+)$/', trim($code), $m)) {
            return null;
        }

        return ['front' => (int) $m[1], 'back' => (int) $m[2], 'total' => (int) $m[1] + (int) $m[2], 'side' => (int) $m[2] > 0 ? '2SIDE' : '1SIDE'];
    }

    public function partTypes(): array
    {
        return self::PART_TYPES;
    }

    public function breakdown(): ?array
    {
        return self::colorBreakdown($this->part['cfg_color']);
    }

    public function render()
    {
        return <<<'BLADE'
            @php($p = $part)
            @php($cb = $breakdown())
            <section {{ $attributes->class('mp-part') }} {!! $dl() !!} data-inq-part data-role="{{ $p['part_role'] }}" data-saved="{{ $p['saved'] ? 1 : '' }}">
                <header class="mp-part__head">
                    <b data-inq-part-title>{{ $p['part_name'] !== '' ? $p['part_name'] : 'Part' }}</b>
                    <span class="mp-grow"></span>
                    @if ($p['sent'])<x-dl::badge label="In ProDev" tone="success" />@endif
                    <x-dl::button size="sm" variant="danger" icon="✕" label="Delete part" data-inq-remove />
                </header>
                <div class="mp-part__body">
                    <x-dl::form.row cols="5">
                        <div class="mp-part__name">
                            <x-dl::field.text :name="$n('part_name')" label="Part Name" placeholder="e.g. Body" :value="$p['part_name']" :source="$src('part_name')" data-inq-name />
                            <x-dl::icon-button icon="fa-tag" title="Choose the part role (the name is filled in automatically)" data-inq-role-open />
                            <input type="hidden" name="{{ $n('part_role') }}" value="{{ $p['part_role'] }}" data-inq-role>
                            <input type="hidden" name="{{ $n('id') }}" value="{{ $p['id'] }}" data-inq-part-id>
                        </div>
                        <x-dl::field.select :name="$n('part_type')" label="Part Type" :options="$partTypes()" :value="$p['part_type']" :placeholder="null" :source="$src('part_type')" />
                        <x-dl::field.decimal :name="$n('qty_per_sku')" label="Qty per Set" :value="$p['qty_per_sku']" :source="$src('qty_per_sku')" />
                        <x-dl::field.number :name="$n('pack_qty')" label="Qty per Pack (pcs)" placeholder="e.g. 400" :min="1" :value="$p['pack_qty']" :source="$src('pack_qty')" />
                        {{-- data-inq-keep: artwork sering datang belakangan dari customer -> tetap bisa diunggah setelah Confirm (keputusan user 2026-09-28). --}}
                        <div class="mp-part__art">
                            <x-dl::field.upload :name="$n('artwork')" label="Artwork / Design (outer)" accept=".jpg,.jpeg,.png" data-inq-keep data-inq-artwork
                                :value="$p['artwork_url'] ? ['url' => $p['artwork_url'], 'name' => $p['artwork_name']] : null"
                                :source="\MockupParts\Src::of('tm2sdi.iqp_part.artwork_path', 'tm2sdi.iqp_part.artwork_original_filename', 'tm2sdi.iqp_part.artwork_colors')" />
                            {{-- Sisi dalam (cetak 2 sisi, mis. mailer box) — tampil di tab "Sisi Dalam" pratinjau dieline (tidak di 3D). --}}
                            <x-dl::field.upload :name="$n('artwork_inner')" label="Inner Side Artwork (optional)" accept=".jpg,.jpeg,.png" data-inq-keep data-inq-artwork-inner
                                :value="$p['artwork_inner_url'] ? ['url' => $p['artwork_inner_url'], 'name' => $p['artwork_inner_name']] : null"
                                :source="\MockupParts\Src::of('tm2sdi.iqp_part.artwork_inner_path', 'tm2sdi.iqp_part.artwork_inner_original_filename')" />
                        </div>
                    </x-dl::form.row>

                    <x-dl::alert tone="warn" data-inq-gate="type">📋 {!! \Devlib\Lang::text('Choose the <b>Product Type</b> first at the top (next to Product Category) before filling in this part.') !!}</x-dl::alert>
                    <x-dl::alert tone="info" data-inq-gate="role">🏷️ {!! \Devlib\Lang::text('Choose the <b>Part Role</b> of this part first (the 🏷️ icon next to Part Name) — dimensions & the dieline preview become active once the role is set.') !!}
                        <x-dl::button size="sm" variant="secondary" label="Choose Part Role" data-inq-role-open /></x-dl::alert>

                    <div class="mp-part__main" data-inq-body>
                        @foreach ($p['suggestions'] as $sug)
                            <x-dl::alert :tone="$sug['tone'] ?? 'info'">{{ $sug['text'] }}<x-dl::button size="sm" variant="secondary" :label="$sug['action'] ?? 'Apply'" data-inq-suggest="{{ $sug['apply'] ?? '' }}" /></x-dl::alert>
                        @endforeach
                        <x-dl::hint note data-inq-dieline-name hidden></x-dl::hint>
                        <x-mp::dieline-v3 :data-artwork="$p['artwork_url']" :data-artwork-inner="$p['artwork_inner_url']" :types="$types" :type="$typeCode ?: array_key_first($types)" :dims="$p['dims']" :name="$n('dieline')" :picker="false" height="400px"
                            :source="\MockupParts\Src::of('tm2mst.prod_type.dieline_spec', 'tm2sdi.iqp_part.dieline_spec', 'tm2sdi.iqp_part.flat_w', 'tm2sdi.iqp_part.flat_h', 'tm2sdi.iqp_part.flat_area')" />

                        <x-dl::divider label="Materials" />
                        <x-dl::hint note>{{ \Devlib\Lang::text('Choose only the Type & Grammage — the brand & plano size are decided by Purchasing/ProDev.') }}</x-dl::hint>
                        <div data-inq-mats data-seq="{{ count($rows()) }}">
                            @foreach ($rows() as $m => $row)
                                <x-mp::sales.inquiry-material :name="$n('materials')" :index="(string) $m" :row="$row" :chains="$materialChains" :fixed="$m < 2" />
                            @endforeach
                        </div>
                        <template data-inq-mat-tpl><x-mp::sales.inquiry-material :name="$n('materials')" index="__M__" :chains="$materialChains" /></template>
                        <div><x-dl::button size="sm" variant="secondary" label="+ Add Material" :title="\Devlib\Lang::text('E.g. 2-layer Paper or 2-layer Sheet')" data-inq-mat-add /></div>

                        <x-dl::divider label="Print / Colour" />
                        <x-dl::form.row cols="3">
                            <x-dl::field.select :name="$n('cfg_color')" label="Colour Notation" :options="$colors()" :value="$p['cfg_color']" :placeholder="null" hint="Format sideA/sideB (1/0 to 6/6)" :source="$src('cfg_color')" data-inq-color />
                            <x-dl::field.display label="Derived (automatic)" :value="$cb ? 'front: '.$cb['front'].' · back: '.$cb['back'].' · total: '.$cb['total'].' · side: '.$cb['side'] : ''"
                                :source="\MockupParts\Src::of('tm2sdi.iqp_part.cfg_color').' (dihitung dari Notasi Warna)'" data-inq-derived />
                            <x-dl::field.text :name="$n('cfg_ink')" label="Colour Note (Pantone)" placeholder="e.g. Pantone 485 C" :value="$p['cfg_ink']" :source="$src('cfg_ink')" />
                        </x-dl::form.row>

                        <x-dl::divider label="Additional Finishing" />
                        <x-dl::hint note>{{ \Devlib\Lang::text('Optional — additional decorative processes (hot print, UV spot, emboss, laminating, etc.), more than one is allowed.') }}</x-dl::hint>
                        <x-dl::lines :name="$n('finishings')" title="Finishing" add-label="+ Add Finishing" :min-rows="0" :rows="$p['finishings']" :source="\MockupParts\Src::of('tm2sdi.iqp_part_finishing')"
                            :columns="[
                                ['key' => 'finishing_type_id', 'label' => 'Finishing Type', 'type' => 'select', 'options' => $finishingTypes, 'required' => true, 'src' => \MockupParts\Src::of('tm2sdi.iqp_part_finishing.finishing_type_id', 'tm2mst.ref_finishing_type.finishing_name')],
                                ['key' => 'attribute', 'label' => 'Attribute / Variant', 'placeholder' => \Devlib\Lang::text('e.g. Gold Puyeh, logo area'), 'src' => \MockupParts\Src::of('tm2sdi.iqp_part_finishing.attribute')],
                            ]" />
                    </div>
                </div>
            </section>
            BLADE;
    }
}
