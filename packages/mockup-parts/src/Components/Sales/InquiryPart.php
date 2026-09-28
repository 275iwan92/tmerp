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

    public const COLORS = ['4/4', '4/1', '4/0', '2/0', '1/1', '1/0'];

    public function __construct(
        public string $index = '0',
        public array $part = [],
        public array $types = [],
        public ?string $typeCode = null,
        public array $finishingTypes = [],
    ) {
        $this->part += ['part_name' => '', 'part_role' => '', 'part_type' => 'DC', 'qty_per_sku' => 1, 'pack_qty' => '', 'dims' => [], 'cfg_color' => '4/4',
            'cfg_ink' => '', 'materials' => [], 'finishings' => [], 'sent' => false, 'saved' => false, 'suggestions' => []];
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

    public function colors(): array
    {
        return self::COLORS;
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
                    <x-dl::button size="sm" variant="secondary" icon="🏭" label="Kirim ke ProDev" data-inq-send data-inq-keep :hidden="true" />
                    @if ($p['sent'])<x-dl::badge label="sudah di ProDev" tone="success" />@endif
                    <x-dl::button size="sm" variant="danger" icon="✕" label="hapus part" data-inq-remove />
                </header>
                <div class="mp-part__body">
                    <x-dl::form.row cols="5">
                        <div class="mp-part__name">
                            <x-dl::field.text :name="$n('part_name')" label="Nama Part" placeholder="mis. Body" :value="$p['part_name']" :source="$src('part_name')" data-inq-name />
                            <x-dl::icon-button icon="fa-tag" title="Pilih peran part (nama terisi otomatis)" data-inq-role-open />
                            <input type="hidden" name="{{ $n('part_role') }}" value="{{ $p['part_role'] }}" data-inq-role>
                        </div>
                        <x-dl::field.select :name="$n('part_type')" label="Tipe Part" :options="$partTypes()" :value="$p['part_type']" :placeholder="null" :source="$src('part_type')" />
                        <x-dl::field.decimal :name="$n('qty_per_sku')" label="Qty per Set" :value="$p['qty_per_sku']" :source="$src('qty_per_sku')" />
                        <x-dl::field.number :name="$n('pack_qty')" label="Isi per Pack (pcs)" placeholder="mis. 400" :min="1" :value="$p['pack_qty']" :source="$src('pack_qty')" />
                        {{-- data-inq-keep: artwork sering datang belakangan dari customer -> tetap bisa diunggah setelah Confirm (keputusan user 2026-09-28). --}}
                        <x-dl::field.upload :name="$n('artwork')" label="Artwork / Desain" accept=".jpg,.jpeg,.png" data-inq-keep
                            :source="\MockupParts\Src::of('tm2sdi.iqp_part.artwork_path', 'tm2sdi.iqp_part.artwork_original_filename', 'tm2sdi.iqp_part.artwork_colors')" />
                    </x-dl::form.row>

                    <x-dl::alert tone="warn" data-inq-gate="type">📋 Pilih <b>Tipe Produk</b> dulu di bagian atas (dekat Kategori Produk) sebelum mengisi part ini.</x-dl::alert>
                    <x-dl::alert tone="info" data-inq-gate="role">🏷️ Pilih <b>Peran Part</b> ini dulu (ikon 🏷️ di sebelah Nama Part) — dimensi &amp; preview dieline aktif setelah peran ditentukan.
                        <x-dl::button size="sm" variant="secondary" label="Pilih Peran Part" data-inq-role-open /></x-dl::alert>

                    <div class="mp-part__main" data-inq-body>
                        @foreach ($p['suggestions'] as $sug)
                            <x-dl::alert :tone="$sug['tone'] ?? 'info'">{{ $sug['text'] }}<x-dl::button size="sm" variant="secondary" :label="$sug['action'] ?? 'Pakai'" data-inq-suggest="{{ $sug['apply'] ?? '' }}" /></x-dl::alert>
                        @endforeach
                        <p class="mp-hint" data-inq-dieline-name hidden></p>
                        <x-mp::dieline-v3 :types="$types" :type="$typeCode ?: array_key_first($types)" :dims="$p['dims']" :name="$n('dieline')" :picker="false" height="400px"
                            :source="\MockupParts\Src::of('tm2mst.prod_type.dieline_spec', 'tm2sdi.iqp_part.dieline_spec', 'tm2sdi.iqp_part.flat_w', 'tm2sdi.iqp_part.flat_h', 'tm2sdi.iqp_part.flat_area')" />

                        <x-dl::divider label="Bahan (Material)" />
                        <p class="mp-hint">Pilih dari master bahan baku — brand &amp; jenis bahan otomatis terisi.</p>
                        <x-dl::lines :name="$n('materials')" title="Bahan" add-label="+ Tambah Bahan" :rows="$p['materials']" :source="\MockupParts\Src::of('tm2sdi.iqp_part_material')"
                            :columns="[
                                ['key' => 'material', 'label' => 'Bahan (ketik untuk cari)', 'type' => 'lookup', 'lookup' => 'paper', 'required' => true, 'src' => \MockupParts\Src::of('tm2sdi.iqp_part_material.rawmat_id', 'tm2mst.mat_raw.material_name')],
                                ['key' => 'brand', 'label' => 'Brand', 'type' => 'display', 'width' => '120px', 'src' => \MockupParts\Src::of('tm2mst.mat_raw.brand')],
                                ['key' => 'category', 'label' => 'Jenis Bahan', 'type' => 'display', 'width' => '120px', 'src' => \MockupParts\Src::of('tm2mst.mat_raw.category1')],
                                ['key' => 'is_primary', 'label' => 'Utama', 'type' => 'primary', 'labels' => ['✓', '—'], 'width' => '80px', 'src' => \MockupParts\Src::of('tm2sdi.iqp_part_material.is_primary')],
                            ]" />

                        <x-dl::divider label="Cetak / Warna" />
                        <x-dl::form.row cols="3">
                            <x-dl::field.select :name="$n('cfg_color')" label="Notasi Warna" :options="$colors()" :value="$p['cfg_color']" :placeholder="null" hint="Format sisiA/sisiB (4/4, 4/0, 4/1)" :source="$src('cfg_color')" data-inq-color />
                            <x-dl::field.display label="Turunan (otomatis)" :value="$cb ? 'front: '.$cb['front'].' · back: '.$cb['back'].' · total: '.$cb['total'].' · side: '.$cb['side'] : ''"
                                :source="\MockupParts\Src::of('tm2sdi.iqp_part.cfg_color').' (dihitung dari Notasi Warna)'" data-inq-derived />
                            <x-dl::field.text :name="$n('cfg_ink')" label="Catatan Warna (Pantone)" placeholder="mis. Pantone 485 C" :value="$p['cfg_ink']" :source="$src('cfg_ink')" />
                        </x-dl::form.row>

                        <x-dl::divider label="Finishing Tambahan" />
                        <p class="mp-hint">Opsional — proses dekoratif tambahan (hot print, UV spot, emboss, laminating, dll), boleh lebih dari satu.</p>
                        <x-dl::lines :name="$n('finishings')" title="Finishing" add-label="+ Tambah Finishing" :min-rows="0" :rows="$p['finishings']" :source="\MockupParts\Src::of('tm2sdi.iqp_part_finishing')"
                            :columns="[
                                ['key' => 'finishing_type_id', 'label' => 'Jenis Finishing', 'type' => 'select', 'options' => $finishingTypes, 'required' => true, 'src' => \MockupParts\Src::of('tm2sdi.iqp_part_finishing.finishing_type_id', 'tm2mst.ref_finishing_type.finishing_name')],
                                ['key' => 'attribute', 'label' => 'Atribut / Varian', 'placeholder' => 'mis. Gold Puyeh, area logo', 'src' => \MockupParts\Src::of('tm2sdi.iqp_part_finishing.attribute')],
                            ]" />
                    </div>
                </div>
            </section>
            BLADE;
    }
}
