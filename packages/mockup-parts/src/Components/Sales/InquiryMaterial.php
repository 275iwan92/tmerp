<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Satu baris bahan Inquiry: Kategori (Paper / Sheet-SF) -> Jenis -> Gramatur/Spec (mat_category level 1 -> 2 -> 3).
// Tanpa merek & ukuran plano (keputusan user 2026-09-29, urusan Purchasing/ProDev).
// fixed = baris bawaan #1 Paper / #2 Sheet-SF (kategori terkunci, tak bisa dihapus); baris tambahan (mis. Paper 2 lapis) bebas.
class InquiryMaterial extends DevlibComponent
{
    public function __construct(
        public string $name = 'materials',
        public string $index = '0',
        public array $row = [],
        public array $chains = [],
        public bool $fixed = false,
    ) {
        $this->row += ['id' => null, 'chain' => 0, 'jenis' => '', 'gramatur' => '', 'label' => ''];
    }

    public function tag(): string
    {
        return 'x-mp::sales.inquiry-material';
    }

    public function source(): string
    {
        return Src::of('tm2sdi.iqp_part_material');
    }

    public function f(string $key): string
    {
        return $this->name.'['.$this->index.']['.$key.']';
    }

    // Kode kategori di form: P = Paper (rantai 0), S = Sheet/SF (rantai 1) -- bukan 0/1 (daftar 0/1 dianggap list oleh select).
    private const CODES = ['P', 'S'];

    public function chain(): int
    {
        return in_array($this->row['chain'], [1, '1', 'S'], true) ? 1 : 0;
    }

    public function code(): string
    {
        return self::CODES[$this->chain()];
    }

    public function kategoriOptions(): array
    {
        return array_combine(self::CODES, array_map(fn ($c) => $c['label'], $this->chains));
    }

    public function jenisOptions(): array
    {
        return array_column($this->chains[$this->chain()]['jenis'] ?? [], 1, 0);
    }

    public function gramOptions(): array
    {
        return array_column($this->chains[$this->chain()]['gram'][$this->row['jenis']] ?? [], 1, 0);
    }

    public function title(): string
    {
        return $this->fixed ? 'Material #'.\Devlib\Lang::raw((int) $this->index + 1) : 'Additional material';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-mat') }} {!! $dl() !!} data-inq-mat="{{ $index }}">
                <input type="hidden" name="{{ $f('id') }}" value="{{ $row['id'] }}">
                <div class="mp-mat__cells">
                    <x-dl::form.row cols="3">
                        @if ($fixed)
                            <input type="hidden" name="{{ $f('chain') }}" value="{{ $code() }}">
                            <x-dl::field.display :label="$title()" :value="$chains[$chain()]['label'] ?? ''"
                                :hint="$row['label'] !== '' && $row['jenis'] === '' ? \Devlib\Lang::raw(strtr(\Devlib\Lang::text('Old data: :label — choose the Type again'), [':label' => $row['label']])) : null" :source="\MockupParts\Src::of('tm2mst.mat_category (level 1)')" />
                        @else
                            <x-dl::field.select :name="$f('chain')" :label="$title()" :options="$kategoriOptions()" :value="$code()" :placeholder="null"
                                :hint="$row['label'] !== '' && $row['jenis'] === '' ? \Devlib\Lang::raw(strtr(\Devlib\Lang::text('Old data: :label — choose the Type again'), [':label' => $row['label']])) : null" :source="\MockupParts\Src::of('tm2mst.mat_category (level 1)')" />
                        @endif
                        <x-dl::field.select :name="$f('jenis')" label="Type" placeholder="— choose a type —" :options="$jenisOptions()" :value="$row['jenis']"
                            :source="\MockupParts\Src::of('tm2sdi.iqp_part_material.category_id', 'tm2mst.mat_category (level 2)')" />
                        <x-dl::field.select :name="$f('gramatur')" label="Grammage / Spec" placeholder="— all —" :options="$gramOptions()" :value="$row['gramatur']" :disabled="$row['jenis'] === ''"
                            :source="\MockupParts\Src::of('tm2sdi.iqp_part_material.category_id', 'tm2sdi.iqp_part_material.gram_weight', 'tm2mst.mat_category (level 3)')" />
                    </x-dl::form.row>
                </div>
                <x-dl::icon-button icon="delete" variant="danger" title="Delete material row" data-inq-mat-del :class="$fixed ? 'mp-mat__del is-off' : 'mp-mat__del'" />
            </div>
            BLADE;
    }
}
