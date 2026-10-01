{{-- Inquiry Baru (NEW). Flow & field: tmerp pages/inquiry/form.blade.php (JANGAN diubah); ditulis ulang dari objek DevLib + x-mp::sales.*. --}}
@php
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;
    use MockupParts\Components\Sales\InquiryForm;

    $doc = request('id') ? Sales::inquiryDoc((int) request('id')) : null;
    $readonly = $doc && ($doc['stat_confirm_sales'] || request('view'));
    $types = Sales::dielineTypes();
    $typeCode = $doc['type_code'] ?? '';
    $finishing = array_column(Sales::masters()['finishing_types'], 'finishing_name', 'id_finishing_type');
    $blankPart = [];
    $chains = Sales::materialChains();
    $parts = $doc['parts'] ?? [$blankPart];
    $tiers = $doc['tiers'] ?? [['qty' => 10000, 'is_primary' => true], ['qty' => 50000, 'is_primary' => false], ['qty' => 200000, 'is_primary' => false]];
    // Kategori Produk -> Detail Kategori -> Ukuran Pasaran = master Lookup Product Market (tm2mst.prod_market, diisi officer ProDev).
    $markets = Sales::productMarkets();
    $i = fn (string $col) => Src::of('tm2sdi.iqp_inquiry.'.$col);
@endphp

@if ($doc)
    <x-dl::action-bar>
        <x-dl::doc-share target="[data-mp-inquiry]" type="inquiry-new" :doc-id="$doc['id']" :number="$doc['inquiry_no'] ?? null" title="New Inquiry" />
    </x-dl::action-bar>
@endif

<x-mp::sales.inquiry-form mode="NEW" :doc="$doc ?? []" :readonly="(bool) $readonly" :back-url="route('v2.screen', 'inquiry')"
    :links="['🔀 Imposition' => route('v2.screen', 'prodev'), '🖨 Cetak Nota' => route('v2.screen', 'nota-inquiry').($doc ? '?id='.$doc['id'] : '')]"
    :prodev-url="route('v2.screen', 'prodev').'?part={part}&inquiry={inquiry}'"
    :urls="['save' => route('v2.inquiry.save'), 'end' => route('v2.inquiry.end', '__ID__'), 'artwork' => route('v2.inquiry.artwork', '__ID__'),
        'customer' => route('v2.inquiry.customer')]">

    <x-slot:header>
        <x-dl::divider label="Header Inkuiri" />
        <x-dl::form.row cols="5">
            <x-dl::field.display label="No. Inkuiri" :value="$doc['inquiry_no'] ?? 'Otomatis saat disimpan'" format="mono" :source="$i('inquiry_no')" />
            <x-dl::field.date name="inquiry_date" label="Date" required :value="$doc['inquiry_date'] ?? date('Y-m-d')" :source="$i('inquiry_date')" />
            <x-dl::field.date name="deadline_quotation" label="Customer Need Date" :value="$doc['deadline_quotation'] ?? date('Y-m-d', strtotime('+14 days'))" :source="$i('deadline_quotation')" />
            <x-dl::field.lookup name="pic_sales" label="PIC Sales" lookup="sales_person" :value="$doc['pic_sales'] ?? auth()->user()->name" :source="$i('pic_sales').' ← tm2mst.sales_person'" />
            <x-dl::field.select name="source" label="Sumber" required :options="['EMAIL', 'WA', 'PHONE', 'VISIT', 'MARKETPLACE']" :value="$doc['source'] ?? 'EMAIL'" :source="$i('source')" />
        </x-dl::form.row>
        <x-dl::form.row cols="3">
            <x-dl::field.lookup name="customer_id" label="Customer" lookup="customer" create required placeholder="Type to search, or scroll to pick…" :value="$doc['customer_id'] ?? null"
                :source="Src::of('tm2sdi.iqp_inquiry.customer_id', 'tm2mst.cust_customer.customer_name')" />
            <x-dl::field.text name="product_draft_name" label="Product Name (draft)" multiline required :maxlength="150" placeholder="mis. Box Kosmetik Serum 30ml"
                :hint="t('Temporary name during the quotation. Special characters (/@#%'.chr(34).chr(39).';) are not allowed.')" :value="$doc['product_draft_name'] ?? ''" :source="$i('product_draft_name')" />
        </x-dl::form.row>
        {{-- Kategori -> Detail -> Ukuran bersambung (user 2026-09-26); Tipe Produk = nama dieline internasional, terisi otomatis
             dari Detail & terkunci (tetap bisa dipilih manual bila tidak memakai kategori, mis. produk custom). --}}
        <script type="application/json" data-inq-markets>@json($markets)</script>
        <x-dl::form.row cols="4">
            {{-- Wajib diisi sampai Ukuran (user 2026-09-26); produk yang belum ada di daftar lewat pilihan Custom. --}}
            <x-dl::field.select name="product_group" label="Product Category" required placeholder="— choose a category —"
                :options="collect($markets)->pluck('label', 'code')->all() + ['__CUSTOM__' => '➕ Custom (belum ada di daftar)']"
                hint="Required — choose Custom if the product is not in the list." :source="Src::of('tm2mst.prod_market (induk), tidak disimpan')" />
            <x-dl::field.select name="product_detail" label="Category Detail" required placeholder="— choose a category first —" :options="[]" disabled
                :source="Src::of('tm2mst.prod_market (detail) → Tipe Produk, tidak disimpan')" />
            <x-dl::field.select name="market_size" label="Standard Size" required placeholder="— choose a detail first —" :options="[]" disabled
                :source="Src::of('tm2mst.prod_type_size (milik detail) → dimensi semua Part')" />
            <x-dl::field.select name="type_code" label="Tipe Produk (dieline internasional)" placeholder="— choose a product type —" :value="$typeCode"
                :options="collect($types)->map(fn ($t) => $t['label'])->all()" hint="Automatic from Category Detail — this is the name searched on Pacdora. Custom: choose the closest dieline."
                :source="Src::of('tm2sdi.iqp_part.part_type', 'tm2mst.prod_type.type_code')" />
        </x-dl::form.row>
        <div data-inq-custom hidden>
            <x-dl::form.row cols="2">
                <x-dl::field.text name="product_detail_custom" label="Custom Product Name" required placeholder="mis. Stand Smartphone / Dus Telur / Dus Pot Tanaman"
                    :source="'catatan detail custom (belum ada di Master Product Market)'" />
                <x-dl::alert tone="info">Produk belum ada di daftar: pilih <b>Tipe Produk</b> (dieline) yang paling mirip dan isi dimensi manual di part.
                    Bila sering dipesan, minta officer ProDev menambahkannya di <b>Master Product Market</b>.</x-dl::alert>
            </x-dl::form.row>
        </div>
        <x-dl::form.row cols="2">
            <x-dl::alert tone="info" data-inq-template-note><span data-inq-template-text></span><x-dl::button size="sm" variant="secondary" label="Tambahkan Otomatis" data-inq-template /></x-dl::alert>
        </x-dl::form.row>
        <x-dl::form.row cols="2">
            <x-dl::field.text name="sales_note" label="Sales Notes" placeholder="opsional" multiline :value="$doc['sales_note'] ?? ''" :source="$i('sales_note')" />
            <x-dl::field.checkbox name="material_source" label="Material Source" text="Customer supplies its own raw material" :value="($doc['material_source'] ?? 'OWN') === 'CUSTOMER'"
                hint="Unticked → material from the company, the order is PRODUCT." :source="Src::of('tm2sdi.iqp_inquiry.material_source', 'tm2sdi.iqp_inquiry.job_nature', 'tm2sdi.iqp_inquiry.pph_applicable')" />
        </x-dl::form.row>
        <div data-inq-custmat hidden>
            <x-dl::divider label="Customer Material Specification (required when the customer supplies material)" />
            <x-dl::form.row cols="2">
                <x-dl::field.text name="cust_material_spec" label="Customer material description" required placeholder="mis. Art Carton 260gsm, 65×100cm, brand APP, 50 rim" :source="$i('cust_material_spec')" />
                <x-dl::field.date name="cust_material_eta" label="Material ETA" :source="$i('cust_material_eta')" />
            </x-dl::form.row>
            <x-dl::alert tone="info">Bahan customer → HPP material = 0; masuk via Receiving non-costed (consignment). Handling &amp; spoilage dihitung Costing.</x-dl::alert>
        </div>
    </x-slot:header>

    <x-dl::card title="Qty Order Berjenjang" description="Costing hitung HPP per tier — tepat 1 UTAMA">
        <x-dl::alert tone="warn" data-inq-tier-err />
        <div class="mp-narrow">
            <x-dl::lines name="tiers" title="Tier" add-label="+ Add Tier" :rows="$tiers" :source="Src::of('tm2sdi.iqp_tier')"
                :columns="[
                    ['key' => 'qty', 'label' => 'Qty (pcs)', 'type' => 'number', 'required' => true, 'placeholder' => 'mis. 10000', 'src' => Src::of('tm2sdi.iqp_tier.qty')],
                    ['key' => 'is_primary', 'label' => 'Utama', 'type' => 'primary', 'width' => '100px', 'src' => Src::of('tm2sdi.iqp_tier.is_primary')],
                ]" />
        </div>
    </x-dl::card>

    <x-dl::card title="Part & Dieline" description="dimensi produk & preview dieline live">
        <div data-inq-parts>
            @foreach ($parts as $n => $part)
                <x-mp::sales.inquiry-part :index="(string) $n" :part="$part + ['saved' => (bool) $doc]" :types="$types" :type-code="$typeCode" :finishing-types="$finishing" :material-chains="$chains" />
            @endforeach
        </div>
        <template data-inq-part-tpl><x-mp::sales.inquiry-part index="__I__" :part="$blankPart" :types="$types" :type-code="$typeCode" :finishing-types="$finishing" :material-chains="$chains" /></template>
        <script type="application/json" data-inq-matchains>@json($chains)</script>
        <div><x-dl::button variant="secondary" icon="＋" :label="t('Add Part')" data-inq-add-part /></div>
    </x-dl::card>

    <x-dl::card title="Status Tooling" description="plate/die/film — check wear on repeat orders">
        <x-dl::alert tone="info">🔧 Tooling baru (plat cetak, pisau/matras, film) → biaya masuk sebagai line terpisah. Master aset tooling (cek otomatis aus) menyusul di iterasi berikutnya — isi manual dulu.</x-dl::alert>
        <x-dl::lines name="toolings" title="Tooling" add-label="+ Add Tooling" :min-rows="0" :rows="$doc['toolings'] ?? []" :source="Src::of('tm2sdi.iqp_tooling')"
            :columns="[
                ['key' => 'tooling_kind', 'label' => t('Type'), 'type' => 'select', 'options' => ['PLATE' => 'Plat cetak', 'PISAU' => 'Pisau/matras', 'MATRAS' => 'Matras', 'FILM' => 'Film'], 'default' => 'PLATE', 'width' => '150px', 'src' => Src::of('tm2sdi.iqp_tooling.tooling_kind')],
                ['key' => 'tooling_status', 'label' => 'Status', 'type' => 'select', 'options' => ['ADA' => 'Ada', 'ADA_WARNING' => 'Ada (warning)', 'AUS' => 'Aus', 'TIDAK_ADA' => 'Tidak ada'], 'default' => 'ADA', 'width' => '160px', 'src' => Src::of('tm2sdi.iqp_tooling.tooling_status')],
                ['key' => 'is_new_charge', 'label' => t('New Cost?'), 'type' => 'bool', 'width' => '110px', 'src' => Src::of('tm2sdi.iqp_tooling.is_new_charge')],
                ['key' => 'cost', 'label' => t('Cost (Rp)'), 'type' => 'money', 'readonlyUnless' => 'is_new_charge', 'width' => '150px', 'src' => Src::of('tm2sdi.iqp_tooling.cost')],
                ['key' => 'remark', 'label' => t('Notes'), 'placeholder' => 'opsional', 'src' => Src::of('tm2sdi.iqp_tooling.remark')],
            ]" />
    </x-dl::card>

    <x-dl::card title="External / Third-Party Costs" description="if already set from the start">
        <x-dl::alert tone="info">🚚 Biaya pihak ketiga yang sudah pasti (trucking, ekspedisi, jasa luar) dicatat sejak inkuiri → mengalir ke quotation &amp; CO.</x-dl::alert>
        <x-dl::lines name="external_costs" title="Cost" add-label="+ Add Cost" :min-rows="0" :rows="$doc['external_costs'] ?? []" :source="Src::of('tm2sdi.iqp_external_cost')"
            :columns="[
                ['key' => 'description', 'label' => 'Deskripsi', 'placeholder' => t('e.g. trucking to the customer\'s warehouse'), 'src' => Src::of('tm2sdi.iqp_external_cost.description')],
                ['key' => 'cost_kind', 'label' => t('Type'), 'type' => 'select', 'options' => ['TRUCKING' => 'Trucking', 'EKSPEDISI' => 'Ekspedisi', 'JASA_LUAR' => 'Jasa Luar', 'LAINNYA' => 'Lainnya'], 'default' => 'TRUCKING', 'width' => '150px', 'src' => Src::of('tm2sdi.iqp_external_cost.cost_kind')],
                ['key' => 'amount', 'label' => t('Amount (Rp)'), 'type' => 'money', 'width' => '150px', 'src' => Src::of('tm2sdi.iqp_external_cost.amount')],
                ['key' => 'tax_treatment', 'label' => t('Tax Treatment'), 'type' => 'select', 'options' => ['TAXABLE' => 'Taxable (masuk DPP)', 'REIMBURSEMENT' => 'Reimbursement'], 'default' => 'TAXABLE', 'width' => '180px', 'src' => Src::of('tm2sdi.iqp_external_cost.tax_treatment')],
            ]" />
    </x-dl::card>
</x-mp::sales.inquiry-form>

@if ($doc)
    @include('v2.screens._inquiry-duplicate', ['source' => $doc['id'], 'label' => ($doc['inquiry_no'] ?: '(draft)').' — '.$doc['product_draft_name'].' · '.$doc['customer']])
    {{-- Rakitan (docs/flow_product_master.md §3): di luar form part supaya alur isian part tidak berubah; tersimpan langsung. --}}
    @php
        $inqModel = \App\Models\Sdi\IqpInquiry::find($doc['id']);
        $tree = \App\Services\Sales\InquiryAssembly::tree($inqModel);
        $flat = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$flat) {
            foreach ($nodes as $n) {
                $flat[] = $n + ['depth' => $depth];
                $walk($n['children'], $depth + 1);
            }
        };
        $walk($tree, 0);
        $assemblies = array_values(array_filter($flat, fn ($n) => $n['kind'] === 'ASSEMBLY'));
    @endphp
    <x-dl::card id="rakitan" title="Rakitan (part gabungan)" description="Combine 2 or more parts into an assembly, e.g. Body = Part A + Part B glued on 2 sides (Double Join). Assemblies may be nested. Materials stay with the parts; the assembly carries the join type.">
        <x-dl::table :columns="['name' => 'Struktur', 'kind' => 'Jenis', 'join' => 'Sambungan', 'qty' => 'Qty dalam induk']"
            :rows="array_map(fn ($n) => ['name' => str_repeat('— ', $n['depth']).$n['name'], 'kind' => $n['kind'] === 'ASSEMBLY' ? 'Rakitan' : 'Komponen',
                'join' => $n['join'] ?? '—', 'qty' => $n['depth'] ? \Devlib\Locale::decimal($n['qty'], 4) : '—'], $flat)"
            empty="Belum ada part tersimpan." source="{{ \App\Support\V2\Src::of('tm2sdi.iqp_part.part_kind', 'parent_part_id', 'qty_in_parent', 'join_type_id') }}" />
        @if ($errors->hasAny(['children', 'assembly_name', 'join_type_id', 'qty_in_parent']))<x-dl::alert tone="danger" :message="$errors->first()" />@endif
        @if (! $readonly)
            @foreach ($assemblies as $a)
                <form method="post" action="{{ route('v2.inquiry.assembly.delete', [$doc['id'], $a['id']]) }}" style="display:inline">
                    @csrf
                    <x-dl::button variant="secondary" size="sm" icon="delete" :label="'Bongkar '.$a['name']" type="submit" onclick="return confirm('Bongkar rakitan {{ $a['name'] }}? Part-nya tetap ada.')" />
                </form>
            @endforeach
            @if (count($tree) >= 2)
                <form method="post" action="{{ route('v2.inquiry.assembly.store', $doc['id']) }}">
                    @csrf
                    <x-dl::divider label="+ Add Assembly" />
                    <x-dl::form.row cols="3">
                        <x-dl::field.text name="assembly_name" label="Assembly name" required placeholder="Body" :value="old('assembly_name')" :source="\App\Support\V2\Src::of('tm2sdi.iqp_part.part_name')" />
                        <x-dl::field.select name="join_type_id" label="Join type" required :options="\App\Models\Master\MatJoinType::where('activate', true)->orderBy('sort_order')->pluck('label', 'id_join_type')->all()" :source="\App\Support\V2\Src::of('tm2mst.mat_join_type')" />
                        <x-dl::field.decimal name="qty_in_parent" label="Qty of each part in the assembly" :decimals="0" value="1" :source="\App\Support\V2\Src::of('tm2sdi.iqp_part.qty_in_parent')" />
                    </x-dl::form.row>
                    <x-dl::field.checkboxes name="children" label="Parts to combine (at least 2)" required :options="collect($tree)->mapWithKeys(fn ($n) => [$n['id'] => $n['name'].($n['kind'] === 'ASSEMBLY' ? ' (rakitan)' : '')])->all()" />
                    <x-dl::action-bar><x-dl::button preset="save" label="Buat Rakitan" /></x-dl::action-bar>
                </form>
            @else
                <p class="mp-hint">Simpan minimal 2 part dulu untuk membuat rakitan.</p>
            @endif
        @endif
    </x-dl::card>
@endif
