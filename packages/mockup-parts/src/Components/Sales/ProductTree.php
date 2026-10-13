<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Struktur product (BOM bertingkat): pohon Product > Rakitan > Komponen di kiri, panel node di kanan (slot).
// Klik node -> panel [data-pm-panel=<id>] tampil (JS ProductTree). $tree = ProductMaster::doc()['tree'].
class ProductTree extends DevlibComponent
{
    public function __construct(
        public array $tree = [],
        public string $product = '',
        public ?int $active = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.product-tree';
    }

    public function source(): string
    {
        return Src::of('tm2mst.prod_part.parent_part_id', 'tm2mst.prod_part.part_kind', 'tm2mst.prod_part.join_type_id');
    }

    public function jsClass(): ?string
    {
        return 'ProductTree';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-pm') }} {!! $dl() !!}>
                <x-dl::card title="Product structure (BOM)">
                    <ul class="mp-ptree mp-ptree--root">
                        <li><button type="button" class="mp-ptree__n {{ $active ? '' : 'is-on' }}" data-pm-node="product"><span class="mp-ptree__ic">P</span><span>{{ $product }}<small>Product</small></span></button>
                            @include('mp::sales.product-tree-nodes', ['nodes' => $tree, 'active' => $active])
                        </li>
                    </ul>
                    <x-dl::hint note>{{ \Devlib\Lang::text('Assemblies can be nested.') }} {{ \Devlib\Lang::text('Click a node to see its dieline, material, BOM and BOP.') }}</x-dl::hint>
                </x-dl::card>
                <div>{{ $slot }}</div>
            </div>
            BLADE;
    }
}
