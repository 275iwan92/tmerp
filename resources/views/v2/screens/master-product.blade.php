{{-- Master Product (docs/flow_product_master.md, mockup disetujui user 2026-09-29).
     Tanpa ?id = Grid for Master (entry): product lahir dari Quotation WIN, jadi tidak ada + New Record.
     ?id = detail: identitas (nama diubah Sales), rantai dokumen, pohon rakitan + panel part, ACC, riwayat order & harga, cabang revisi. --}}
@php
    use App\Models\Master\ProdProductAcc;
    use App\Support\V2\Data\ProductMaster;
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;

    $doc = request('id') ? ProductMaster::doc((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $orders = $doc ? ProductMaster::orders($doc['id']) : [];
    $types = $doc ? Sales::dielineTypes() : [];
    $flat = function ($nodes) use (&$flat) {
        $o = [];
        foreach ($nodes as $n) {
            $o[] = $n;
            $o = array_merge($o, $flat($n['children']));
        }

        return $o;
    };
    $lastAcc = fn ($t) => $doc ? collect($doc['accs'])->firstWhere('type', $t) : null;
@endphp

<x-mp::sales.flow-steps current="quotation" note="Master Product is created when a Quotation is WON; Revision and Recalculation start again from Inquiry." />

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $doc)
    <x-dl::grid id="master-product" :rows="ProductMaster::rows()" search="Cari nama product, no. desain, customer..." :new-record="null" actions="worklist"
        :open-url="route('v2.screen', 'master-product').'?id={id}'" :active-key="null"
        source="{{ Src::of('tm2mst.prod_product', 'tm2mst.prod_part', 'tm2mst.prod_part_artwork', 'tm2mst.prod_product_acc', 'tm2mst.cust_customer') }}"
        :columns="[
            ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'customer', 'label' => 'Customer (pemilik)'],
            ['key' => 'parts', 'label' => 'Part', 'type' => 'number'],
            ['key' => 'join', 'label' => 'Sambungan'],
            ['key' => 'revision', 'label' => 'Cabang'],
            ['key' => 'acc_digital', 'label' => 'ACC Digital', 'type' => 'badge', 'tones' => ['ADA' => 'success', 'BELUM' => 'warn']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
            ['key' => 'updated', 'label' => 'Diubah', 'type' => 'date', 'visible' => false],
        ]"
        :stats="[
            ['label' => 'Semua Product'],
            ['label' => 'Resmi (Quotation WIN)', 'where' => ['status' => 'ACTIVE'], 'tone' => 'success'],
            ['label' => 'Draft (ProDev)', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Belum ACC Digital', 'where' => ['acc_digital' => 'BELUM', 'status' => 'ACTIVE'], 'tone' => 'warn'],
        ]"
        :filters="['customer' => 'Customer', 'join' => 'Sambungan', 'status' => 'Status']" />
    <p class="mp-hint">Product lahir otomatis saat Quotation WIN (CO terbentuk) — tidak dibuat manual. Klik 2x baris untuk membuka.</p>
@else
    <x-dl::card id="mp-doc">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'master-product')" />
            <x-dl::doc-share target="#mp-doc" type="master-product" :doc-id="$doc['id']" :number="$doc['code']" title="Master Product" />
            <span class="mp-chips">
                <x-dl::badge :label="$doc['status'] === 'ACTIVE' ? 'Resmi · Quotation WIN' : 'DRAFT (ProDev)'" :tone="$doc['status'] === 'ACTIVE' ? 'success' : 'neutral'" />
                @if ($doc['join'])<x-dl::badge :label="$doc['join']" tone="warn" />@endif
                <x-dl::badge :label="$doc['parent'] ? 'Turunan Rev.'.$doc['revision_no'] : 'Produk induk'" />
            </span>
        </x-dl::action-bar>
        <form method="post" action="{{ route('v2.product.name', $doc['id']) }}" data-dl-guard>
            @csrf
            <x-dl::form.row cols="3">
                <x-dl::field.text name="product_name" label="Nama product" required :value="old('product_name', $doc['name'])" hint="Diubah Sales supaya sama dengan PO customer; tercetak di CO Detail & Surat Jalan." :source="Src::of('tm2mst.prod_product.product_name')" />
                <x-dl::field.display label="Customer (pemilik product)" :value="$doc['customer']" :source="Src::of('tm2mst.prod_product.customer_id', 'tm2mst.cust_customer.customer_name')" />
                <x-dl::field.display label="Kode · No. Desain" :value="$doc['code'].' · '.($doc['design_no'] ?? '—')" :source="Src::of('tm2mst.prod_product.product_code', 'tm2mst.prod_product.design_no')" />
            </x-dl::form.row>
            <x-dl::form.row cols="3">
                <x-dl::field.display label="Ukuran jadi (P × L × T)" :value="collect($doc['dims'])->map(fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ','))->implode(' × ').' cm'" :source="Src::of('tm2mst.prod_product.cm_length', 'cm_width', 'cm_height')" />
                <x-dl::field.display label="Induk (bila turunan revisi)" :value="$doc['parent']['label'] ?? '—'" :source="Src::of('tm2mst.prod_product.parent_product_id')" />
                <x-dl::field.display label="Order terakhir" :value="$orders ? ($orders[0]['co'] ?? $orders[0]['quotation']).' · '.$num($orders[0]['qty']).' pcs · '.$orders[0]['date'] : '—'" :source="Src::of('tm2sdi.co_order_item.product_id')" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
        </form>
    </x-dl::card>

    <x-mp::sales.product-tree :tree="$doc['tree']" :product="$doc['name']">
        <div data-pm-panel="product">
            <x-dl::card :title="$doc['name']" description="Product · semua part lahir dari Inquiry saat Quotation WIN">
                <div class="mp-thumbs">
                    @foreach ($doc['components'] as $c)
                        <x-mp::image-thumb :label="$c['name']" :src="$c['artwork']" empty="belum ada artwork" />
                    @endforeach
                </div>
                @if ($doc['components'])
                    <x-mp::box3d :dims="$doc['dims']" :front="$doc['components'][0]['artwork']" :back="$doc['components'][1]['artwork'] ?? null" class="mp-mt" />
                @endif
            </x-dl::card>
        </div>
        @foreach ($flat($doc['tree']) as $n)
            <div data-pm-panel="{{ $n['id'] }}" hidden>
                <x-dl::card :title="$n['name']" :description="$n['kind'] === 'ASSEMBLY' ? 'Rakitan · '.($n['join'] ?? 'sambungan belum diisi').' · '.count($n['children']).' komponen' : 'Komponen · '.($n['code'] ?? '').($n['flat'] ? ' · bentangan '.$n['flat'] : '')">
                    @if ($n['kind'] === 'ASSEMBLY')
                        <x-dl::alert tone="warn" :message="str_contains((string) $n['join_code'], 'DOUBLE') ? 'Direkatkan 2 sisi — setelah menyatu TIDAK bisa dibentangkan lagi (dampak ke packing & volume kirim).' : 'Direkatkan 1 sisi — masih bisa dibentangkan.'" />
                        <div class="mp-thumbs">@foreach ($n['children'] as $c)<x-mp::image-thumb :label="$c['name']" :src="$c['artwork']" empty="belum ada artwork" />@endforeach</div>
                        <x-mp::box3d :dims="$doc['dims']" :front="$n['children'][0]['artwork'] ?? null" :back="$n['children'][1]['artwork'] ?? null" class="mp-mt" />
                        <x-dl::divider label="BOP rakitan" />
                        <ol class="mp-steps">@forelse ($n['bop'] as $s)<li><span><b>{{ $s['name'] }}</b>@if ($s['note'])<small>{{ $s['note'] }}</small>@endif</span></li>@empty<li><span>Belum ada BOP.</span></li>@endforelse</ol>
                    @else
                        <x-dl::tabs :tabs="['view' => 'Dieline & Mockup', 'bom' => 'Bahan & BOM', 'bop' => 'BOP', 'spec' => 'Warna & Finishing']">
                            <x-slot:view><x-mp::sales.part-visual :part="$n" :types="$types" /></x-slot:view>
                            <x-slot:bom>
                                <x-dl::table :columns="['seq' => '#', 'role' => 'Peran', 'jenis' => 'Jenis / Gramatur', 'brand' => 'Merek (acuan)']" :rows="array_map(fn ($m) => $m + ['brand' => $m['brand'] ?? '—'], $n['materials'])"
                                    empty="Belum ada bahan." source="{{ Src::of('tm2mst.prod_part_material', 'tm2mst.mat_category', 'tm2mst.mat_raw.brand') }}" />
                                <x-dl::divider label="Tinta" />
                                <div class="mp-chips">@forelse ($n['inks'] as $k)<x-dl::badge :label="'Unit '.$k['unit'].' · '.$k['ink']" />@empty<span class="mp-hint">Belum ada tinta.</span>@endforelse</div>
                            </x-slot:bom>
                            <x-slot:bop>
                                <ol class="mp-steps">@forelse ($n['bop'] as $s)<li><span><b>{{ $s['name'] }}</b><small>{{ trim(($s['machine'] ? 'Mesin '.$s['machine'].' · ' : '').($s['note'] ?? ''), ' ·') }}</small></span></li>@empty<li><span>Belum ada BOP.</span></li>@endforelse</ol>
                            </x-slot:bop>
                            <x-slot:spec>
                                <x-dl::form.row cols="3">
                                    <x-dl::field.display label="Notasi warna" :value="$n['color'] ?? '—'" :source="Src::of('tm2mst.prod_part.cfg_color')" />
                                    <x-dl::field.display label="Tinta" :value="$n['ink'] ?? '—'" :source="Src::of('tm2mst.prod_part.cfg_ink')" />
                                    <x-dl::field.display label="Bentangan" :value="$n['flat'] ?? '—'" :source="Src::of('tm2mst.prod_part.flat_w', 'flat_h')" />
                                </x-dl::form.row>
                                <div class="mp-chips">@forelse ($n['finishings'] as $f)<x-dl::badge :label="$f" tone="info" />@empty<span class="mp-hint">Tanpa finishing.</span>@endforelse</div>
                            </x-slot:spec>
                        </x-dl::tabs>
                    @endif
                </x-dl::card>
            </div>
        @endforeach
    </x-mp::sales.product-tree>

    <x-dl::card>
        <x-dl::tabs :tabs="['acc' => 'ACC Customer', 'orders' => 'Riwayat Order & Harga Jual', 'branch' => 'Cabang Revisi']">
            <x-slot:acc>
                <x-dl::form.row cols="3">
                    @foreach (ProdProductAcc::TYPES as $t => $label)
                        <x-dl::field.display :label="$label" :value="$lastAcc($t) ? $lastAcc($t)['at'].' · '.$lastAcc($t)['by'].' ('.$lastAcc($t)['channel'].')' : ($t === 'DIGITAL' ? 'BELUM — wajib sebelum proses Cetak' : 'belum dicatat')" :source="Src::of('tm2mst.prod_product_acc')" />
                    @endforeach
                </x-dl::form.row>
                <p class="mp-hint">ACC dicatat &amp; Proof Digital dicetak dari form ProDev (tombol khusus Print ACC / Proof Digital).</p>
            </x-slot:acc>
            <x-slot:orders>
                <x-dl::table :columns="['date' => 'Tanggal', 'quotation' => 'Quotation', 'co' => 'Customer Order', 'qty' => 'Qty (pcs)', 'price' => 'Harga jual /pcs', 'result' => 'Hasil']"
                    :rows="array_map(fn ($o) => ['date' => $o['date'], 'quotation' => $o['quotation'] ?? '—', 'co' => $o['co'] ?? '—', 'qty' => $num($o['qty']), 'price' => $rp($o['price']), 'result' => ['WIN' => 'WIN · terjual', 'LOST' => 'LOST', 'OPEN' => 'Terbuka'][$o['result']]], $orders)"
                    empty="Belum ada order." source="{{ Src::of('tm2sdi.quo_quotation_item', 'tm2sdi.co_order_item.product_id') }}" />
            </x-slot:orders>
            <x-slot:branch>
                <x-dl::table :columns="['rel' => 'Hubungan', 'label' => 'Product', 'status' => 'Status']"
                    :rows="array_merge($doc['parent'] ? [['rel' => 'Induk', 'label' => $doc['parent']['label'], 'status' => '']] : [], [['rel' => 'Product ini', 'label' => $doc['name'].' · '.$doc['design_no'], 'status' => $doc['status']]],
                        array_map(fn ($r) => ['rel' => 'Turunan', 'label' => $r['label'], 'status' => $r['status']], $doc['revisions']))"
                    source="{{ Src::of('tm2mst.prod_product.parent_product_id') }}" />
                <p class="mp-hint">Setiap Revisi membuka cabang baru (product turunan); product lama tetap utuh.</p>
            </x-slot:branch>
        </x-dl::tabs>
    </x-dl::card>
@endif
