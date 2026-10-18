{{--
 REMARK
 Tujuan       : Form Master Product Market v2 (menggantikan halaman v1 pages::lookup.product-market-entry): identitas dan foto, default di Inquiry New (Tipe Produk, warna, jenjang qty, bahan #1 Paper dan #2 Sheet), dan Ukuran Standar per Detail Kategori (dialog tambah, ubah, hapus).
 Sumber hulu  : tm2mst.prod_market, prod_type, prod_type_param, prod_type_size, prod_type_size_val, mat_category (rantai bahan); aturan di App\Services\Master\MarketMasterService.
 Input        : Query id (market) atau new=1, tab; session v2_flash dan errors.
 Output       : POST v2.sales.market.save (Save / Activate / Deactivate / Remove Photo), v2.sales.market.size dan size.delete (dialog ukuran).
 Dipakai oleh : Dibuka dari grid sales-product-market (Open dan + New Record).
 Status       : Aktif. Ukuran muncul sesudah Detail Kategori disimpan dengan Tipe Produk; kolom dimensi mengikuti Tipe Produk. Master ini dibaca Inquiry New (Kategori Produk).
--}}
@php
    use App\Models\Master\ProdMarket;
    use App\Services\Master\MarketMasterService as Mk;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'sales-product-market');
    $svc = app(Mk::class);
    $m = request('id') ? ProdMarket::with(['parent', 'type', 'sizes.values'])->withCount('children')->find((int) request('id')) : null;
    abort_if(request('id') && ! $m, 404);
    abort_unless($m || request('new'), 404);
    $new = ! $m;
    $m ??= new ProdMarket(['activate' => true, 'sort_order' => $svc->nextSortOrder()]);
    $isDetail = ! $new && $m->parent_id !== null;
    $s = fn (string $col) => Src::of('tm2mst.prod_market.'.$col);
    $v = fn (string $col) => old($col, $m->{$col});
    $hasSizes = $isDetail && $m->type_code;
    $tabs = ['identity' => 'Identity', 'defaults' => 'Inquiry defaults'] + ($hasSizes ? ['sizes' => 'Standard sizes'] : []);
    $params = $hasSizes ? $svc->dimParams($m->type_code) : collect();
    $levels = ['Category', 'Type', 'Grammage/Spec', 'Size (optional)', 'Brand (optional)'];
    $dim = fn ($size, string $key) => $size?->values->firstWhere('param_key', $key)?->value_num;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="sales-market-form" :title="$new ? 'New Product Market' : raw($m->market_label)" :subtitle="$new ? null : raw($m->market_code.' · '.($isDetail ? t('Category Detail') : t('Product Category')))"
    panel="top" kind="master" :status="$new ? 'new' : ($m->activate ? 'active' : 'inactive')"
    :action="route('v2.sales.market.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" enctype="multipart/form-data"
    :hidden="$new ? [] : ['id' => $m->id_market]" :tabs="$tabs" :log="$new ? [] : $svc->history($m)"
    :created-by="$m->created_by" :created-at="$m->created_at?->toDateTimeString()" :updated-by="$m->updated_by" :updated-at="$m->updated_at?->toDateTimeString()">
    @if ($m->photo_path)
        <x-slot:special>
            <x-dl::button size="sm" preset="delete" label="Remove Photo" data-f-act="remove_photo" data-dl-confirm="{{ t('Remove the photo of this product market?') }}" />
        </x-slot:special>
    @endif
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Code" :value="$new ? t('(on Save)') : raw($m->market_code)" />
        <x-dl::strip-kv kind="grand" label="Level" :value="$new ? '—' : ($isDetail ? t('Category Detail') : t('Product Category'))" />
        <x-dl::strip-kv kind="grand" :label="$isDetail ? 'Product Category' : 'Details'" :value="$new ? '—' : ($isDetail ? raw($m->parent?->market_label ?: '—') : (string) $m->children_count)" />
        <x-dl::strip-kv kind="grand" label="Product Type" :value="$new ? '—' : raw($m->type?->type_name ?: '—')" />
        <x-dl::strip-kv kind="grand" label="Standard sizes" :value="$new ? '—' : (string) $m->sizes->count()" />
    </x-slot:strip>

    <x-slot:identity>
        <x-dl::card title="Identity">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="parent_id" label="Product Category (parent)" placeholder="None: this record is a Product Category" hint="Empty = this record becomes a Category (e.g. Food Box). Filled = Category Detail (e.g. PPL)."
                    :options="$svc->groupOptions($m->id_market)" :value="$v('parent_id')" :source="$s('parent_id')" />
                <x-dl::field.text name="market_label" label="Name (sales wording)" required placeholder="e.g. Food Box / Paper Lunch Box (PPL)" :value="$v('market_label')" :source="$s('market_label')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="market_code" label="Code" placeholder="Automatic from the name when empty" :case-toggle="false" :value="$v('market_code')" :source="$s('market_code')" />
                <x-dl::field.decimal name="sort_order" label="Display order" :decimals="0" :toggle="false" hint="Automatic after the last order; can be changed." :value="$v('sort_order')" :source="$s('sort_order')" />
            </x-dl::form.row>
            <x-dl::field.text name="notes" label="Notes for sales" multiline placeholder="e.g. for oily food use PE-coated kraft" :value="$v('notes')" :source="$s('notes')" />
        </x-dl::card>
        <x-dl::card title="Photo">
            <x-dl::photo-box name="photo" :label="raw($m->market_label ?: '')" :photo="$m->photo_path ? '/storage/'.$m->photo_path : null" />
        </x-dl::card>
    </x-slot:identity>

    <x-slot:defaults>
        @unless ($isDetail || $new)
            <x-dl::alert tone="info" message="This is a Product Category (grouping only): the fields below are ignored. Choose a Product Category as parent on the Identity tab to make it a Category Detail." />
        @endunless
        @if ($new)
            <x-dl::alert tone="info" message="Fill this tab only when a Product Category is chosen as parent on the Identity tab (a Category Detail)." />
        @endif
        <x-dl::card title="Defaults in Inquiry New">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="type_code" label="Product Type (dieline)" placeholder="Choose" :options="$svc->typeOptions()" :value="$v('type_code')" :source="Src::of('tm2mst.prod_market.type_code', 'tm2mst.prod_type')" />
                <x-dl::field.select name="default_color" label="Print colour" placeholder="None" :options="Mk::COLOURS" :value="$v('default_color')" :source="$s('default_color')" />
                <x-dl::field.text name="default_tiers" label="Qty tiers (comma separated)" placeholder="e.g. 1000,3000,5000" :case-toggle="false" :value="$v('default_tiers')" :source="$s('default_tiers')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Material #1 Paper: hierarchy of raw material categories">
            <x-dl::code-chain name="paper" :nodes="$svc->chainNodes('paper')" :levels="$levels" :value="old('paper', $m->paper_category_id ?: $svc->chainRoot('paper'))" code-label="Category code"
                :source="Src::of('tm2mst.prod_market.paper_category_id', 'tm2mst.mat_category')" />
        </x-dl::card>
        <x-dl::card title="Material #2 Sheet / SF: hierarchy of raw material categories">
            <x-dl::code-chain name="sheet" :nodes="$svc->chainNodes('sheet')" :levels="$levels" :value="old('sheet', $m->sheet_category_id ?: $svc->chainRoot('sheet'))" code-label="Category code"
                :source="Src::of('tm2mst.prod_market.sheet_category_id', 'tm2mst.mat_category')" />
            <x-dl::hint note>Leave at Category (or choose a Type) as needed: Sheet counts as filled only when its Type is chosen.</x-dl::hint>
        </x-dl::card>
    </x-slot:defaults>

    @if ($hasSizes)
        <x-slot:sizes>
            <x-dl::action-bar>
                <x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add size" data-dl-open="mk-size-new" />
            </x-dl::action-bar>
            <x-dl::table :columns="['name' => 'Size name'] + $params->mapWithKeys(fn ($p) => [$p->param_key => $p->param_label.' ('.($p->unit ?: 'mm').')'])->all()"
                :rows="$m->sizes->map(fn ($z) => ['name' => raw($z->size_label)] + $params->mapWithKeys(fn ($p) => [$p->param_key => $dim($z, $p->param_key) !== null ? Locale::number((float) $dim($z, $p->param_key), 1) : '—'])->all())->values()->all()"
                empty="No standard size yet." :source="Src::of('tm2mst.prod_type_size', 'tm2mst.prod_type_size_val')" />
            @foreach ($m->sizes as $z)
                <x-dl::button type="button" size="sm" variant="secondary" icon="fa-pen" :label="raw($z->size_label)" :data-dl-open="'mk-size-'.$z->id_type_size" />
            @endforeach
        </x-slot:sizes>
    @endif
</x-dl::form>

@if ($hasSizes)
    @foreach ([null, ...$m->sizes->all()] as $z)
        <x-dl::modal :id="'mk-size-'.($z?->id_type_size ?? 'new')" :title="$z ? 'Edit Size' : 'Add Size'" icon="fa-ruler-combined" size="md">
            <x-dl::action-form action="{{ $z ? route('v2.sales.market.size', [$m->id_market, $z->id_type_size]) : route('v2.sales.market.size', $m->id_market) }}">
                <x-dl::field.text name="size_label" label="Size name" required placeholder="e.g. R8 (18 x 18 x 6.7 cm)" :value="$z?->size_label" :source="Src::of('tm2mst.prod_type_size.size_label')" />
                <x-dl::form.row :cols="2">
                    @foreach ($params as $p)
                        <x-dl::field.decimal :name="'dim_'.$p->param_key" :label="$p->param_label.' ('.($p->unit ?: 'mm').')'" :decimals="1" :toggle="false" :value="$dim($z, $p->param_key)" :source="Src::of('tm2mst.prod_type_size_val.value_num')" />
                    @endforeach
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" />
                </x-dl::action-bar>
            </x-dl::action-form>
            @if ($z)
                <x-dl::action-form action="{{ route('v2.sales.market.size.delete', [$m->id_market, $z->id_type_size]) }}">
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" size="sm" icon="fa-trash" label="Remove size" data-dl-confirm="{{ t('Remove this size?') }}" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @endif
        </x-dl::modal>
    @endforeach
@endif
