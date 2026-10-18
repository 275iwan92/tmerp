{{--
 REMARK
 Tujuan       : Form Master Raw Material v2 (menggantikan halaman v1 pages::master.raw-material.entry): kategori yang menyusun kode item, spesifikasi dan QC, UoM dan pengadaan, stok dan lokasi, akun GL; Confirm (mengunci kategori dan kode), Delete (atau nonaktif bila masih dipakai).
 Sumber hulu  : tm2mst.mat_raw, mat_category (rantai 1..5), uom_unit, sup_supplier; tm2acc.fin_account, fin_cost_center, fin_profit_center; aturan di App\Services\Master\MaterialMasterService dan MaterialGuardService.
 Input        : Query id (item) atau new=1, tab; session v2_flash dan errors.
 Output       : POST v2.md.raw.save (Save / Confirm / Activate / Deactivate / Delete).
 Dipakai oleh : Dibuka dari grid md-raw (Open dan + New Record).
 Status       : Aktif. Stok dan harga rata-rata hanya tampil (dikelola posting Purchase Receive). Kategori dan kode terkunci sesudah Confirm.
--}}
@php
    use App\Models\Master\MatRaw;
    use App\Services\Master\MaterialCategoryService;
    use App\Services\Master\MaterialMasterService as Ma;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'md-raw');
    $svc = app(Ma::class);
    $cat = app(MaterialCategoryService::class);
    $m = request('id') ? MatRaw::find((int) request('id')) : null;
    abort_if(request('id') && ! $m, 404);
    abort_unless($m || request('new'), 404);
    $new = ! $m;
    $m ??= new MatRaw(['item_type' => 'MATERIAL', 'purchasable' => true, 'qc_required' => false, 'is_fx_sensitive' => false, 'conversion_to_stock' => 1, 'purchase_tax_code' => 'PPN', 'costing_method' => 'AVG', 'activate' => true, 'stat_confirm' => false]);
    $locked = ! $new && $m->stat_confirm;
    $s = fn (string $col) => Src::of('tm2mst.mat_raw.'.$col);
    $v = fn (string $col) => old($col, $m->{$col});
    $yn = fn (string $col) => (string) (int) (old() ? (bool) old($col) : (bool) $m->{$col});
    $yesNo = ['1' => 'Yes', '0' => 'No'];
    $idr = fn ($x) => 'IDR '.Locale::number((float) $x, 2);
    $usage = $new ? [] : $svc->usage($m);
    $tabs = ['category' => 'Category', 'specs' => 'Specs and QC', 'uom' => 'UoM and Procurement', 'inventory' => 'Stock and Location', 'accounting' => 'Accounting'];
    $chain = old('category', $new ? null : $svc->chainId($m));
    $levels = array_map(fn ($i) => 'Category '.$i, range(1, MaterialCategoryService::LEVELS));
    $accounts = $svc->accountOptions();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="md-raw-form" :title="$new ? 'New Raw Material' : raw($m->material_name)" :subtitle="$new ? null : raw($m->material_code.' · '.$m->item_type)"
    panel="top" kind="master" :status="$new ? 'new' : ($m->activate ? 'active' : 'inactive')" :stage="$new ? null : ($m->stat_confirm ? 'Confirmed' : 'Draft')"
    :action="route('v2.md.raw.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $m->material_id]" :tabs="$tabs" :log="$new ? [] : $svc->history($m)"
    :created-by="$m->create_by" :created-at="$m->create_log?->toDateTimeString()" :updated-by="$m->update_by" :updated-at="$m->update_log?->toDateTimeString()">
    <x-slot:special>
        @unless ($new)
            @unless ($locked)
                <x-dl::button type="submit" name="action" value="confirm" variant="secondary" icon="fa-check" label="Confirm" formnovalidate data-dl-confirm="{{ t('Confirm this item? Category and item code will be locked permanently.') }}" />
            @endunless
            <x-dl::button type="submit" name="action" value="delete" variant="secondary" icon="fa-trash" label="Delete" formnovalidate data-dl-confirm="{{ t('Delete this item? If it still has stock or is used by transactions, it will only be deactivated.') }}" />
        @endunless
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Item code" :value="$new ? t('(on Save)') : $m->material_code" />
        <x-dl::strip-kv kind="grand" label="Category" :value="$new ? '—' : raw($m->category1 ?: '—')" />
        <x-dl::strip-kv kind="grand" label="Stock on hand" :value="$new ? '—' : raw(Locale::number((float) $m->cur_stock, 2).' '.$m->uom_stock)" />
        <x-dl::strip-kv kind="grand" label="Average price" :value="$new ? '—' : $idr($m->avg_unitprice)" />
        <x-dl::strip-kv kind="grand" label="Used in" :value="$new ? '—' : array_sum($usage).' '.t('rows')" />
    </x-slot:strip>

    <x-slot:category>
        <x-dl::card title="Item">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="material_name" label="Item name" required placeholder="e.g. Art Carton 210gr 65x100 GOLDEN" :value="$v('material_name')" :source="$s('material_name')" />
                <x-dl::field.select name="item_type" label="Item type" :options="['MATERIAL' => 'MATERIAL (raw material / stock)', 'SERVICE' => 'SERVICE', 'FREE_SAMPLE' => 'FREE_SAMPLE (sample from supplier)']" :value="$v('item_type')" :source="$s('item_type')" />
                <x-dl::field.select name="purchasable" label="Purchasable (PO)" :options="$yesNo" :value="$yn('purchasable')" :source="$s('purchasable')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Hierarchy that builds the item code">
            <x-dl::code-chain name="category" :nodes="$cat->nodes()" :levels="$levels" :value="$chain" code-label="Item code" :disabled="$locked"
                :source="Src::of('tm2mst.mat_category.category_name', 'tm2mst.mat_category.prefix_char')" />
            @if ($locked)
                <x-dl::hint note>Category is locked because this item has been Confirmed (the item code follows it). Contact Admin if reclassification is needed.</x-dl::hint>
            @elseif ($new)
                <x-dl::hint note>The item code is the category prefixes joined; a running number is added if the code already exists.</x-dl::hint>
            @else
                <x-dl::hint note>This item is still Draft: category and code can be changed until Confirm. Keep the chain as it is to keep the current code ({{ $m->material_code }}).</x-dl::hint>
            @endif
        </x-dl::card>
        <x-dl::card title="Customs and legacy code">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="hs_code" label="HS code" placeholder="4810.13.00" :case-toggle="false" :value="$v('hs_code')" :source="$s('hs_code')" />
                <x-dl::field.text name="old_item_code" label="Old code" :case-toggle="false" :value="$v('old_item_code')" :source="$s('old_item_code')" />
                <x-dl::field.text name="old_item_name" label="Old name" :value="$v('old_item_name')" :source="$s('old_item_name')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:category>

    <x-slot:specs>
        <x-dl::card title="Dimensions and physical (leave empty if not paper / board)">
            <x-dl::form.row :cols="3">
                <x-dl::field.decimal name="gram_weight" label="Grammage (gsm)" unit="gsm" :toggle="false" :value="$v('gram_weight')" :source="$s('gram_weight')" />
                <x-dl::field.decimal name="cm_length" label="Plano length (cm)" :decimals="4" unit="cm" :toggle="false" :value="$v('cm_length')" :source="$s('cm_length')" />
                <x-dl::field.decimal name="cm_width" label="Plano width (cm)" :decimals="4" unit="cm" :toggle="false" :value="$v('cm_width')" :source="$s('cm_width')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.decimal name="mm_thickness" label="Thickness (mm)" :decimals="4" unit="mm" :toggle="false" :value="$v('mm_thickness')" :source="$s('mm_thickness')" />
                <x-dl::field.text name="brand" label="Manufacturer brand" :value="$v('brand')" :source="$s('brand')" />
                <x-dl::field.text name="color" label="Color" :value="$v('color')" :source="$s('color')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="QC and shelf life">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="qc_required" label="QC required at receipt" hint="Turns on the quality QC gate on the PV screen." :options="['1' => 'Yes — lab check GSM / moisture', '0' => 'No — straight to stock']" :value="$yn('qc_required')" :source="$s('qc_required')" />
                <x-dl::field.decimal name="shelf_life_days" label="Shelf life (days)" :decimals="0" unit="days" :toggle="false" :value="$v('shelf_life_days')" :source="$s('shelf_life_days')" />
                <x-dl::field.decimal name="moisture_pct" label="Moisture limit (%)" :decimals="1" unit="%" :toggle="false" :value="$v('moisture_pct')" :source="$s('moisture_pct')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:specs>

    <x-slot:uom>
        <x-dl::card title="Units and conversion">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="uom_stock" label="Stock UoM" placeholder="None" :options="$svc->uomOptions()" :value="$v('uom_stock')" :source="$s('uom_stock')" />
                <x-dl::field.select name="uom_purchase" label="Purchase UoM (PO)" placeholder="None" :options="$svc->uomOptions()" :value="$v('uom_purchase')" :source="$s('uom_purchase')" />
                <x-dl::field.select name="uom_usage" label="Usage UoM" placeholder="None" :options="$svc->uomOptions()" :value="$v('uom_usage')" :source="$s('uom_usage')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.decimal name="conversion_to_stock" label="Purchase to stock conversion" :decimals="6" :toggle="false" hint="Starting value only; it can always be overwritten manually at PV (bulk goods)." :value="old('conversion_to_stock', (float) $m->conversion_to_stock)" :source="$s('conversion_to_stock')" />
                <x-dl::field.decimal name="conversion_to_usage" label="Stock to usage conversion" :decimals="6" :toggle="false" :value="$v('conversion_to_usage')" :source="$s('conversion_to_usage')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Procurement parameters">
            <x-dl::form.row :cols="4">
                <x-dl::field.decimal name="lead_time_days" label="Lead time (days)" :decimals="0" unit="days" :toggle="false" :value="$v('lead_time_days')" :source="$s('lead_time_days')" />
                <x-dl::field.decimal name="tol_purchase" label="Receiving tolerance (%)" :decimals="1" unit="%" :toggle="false" :value="$v('tol_purchase')" :source="$s('tol_purchase')" />
                <x-dl::field.select name="purchase_tax_code" label="Input tax" :options="['PPN' => 'PPN (sys_constant)', 'NON_PPN' => 'Non-PPN']" :value="$v('purchase_tax_code')" :source="$s('purchase_tax_code')" />
                <x-dl::field.select name="suggest_supplier_id" label="Suggested supplier" placeholder="None" :options="$svc->supplierOptions()" :value="$v('suggest_supplier_id')" :source="$s('suggest_supplier_id')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Acquisition cost method">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="costing_method" label="Costing method" :options="['AVG' => 'AVG — moving average', 'FIFO' => 'FIFO', 'LIFO' => 'LIFO', 'STANDARD' => 'STANDARD — fixed price']" :value="$v('costing_method')" :source="$s('costing_method')" />
                <x-dl::field.select name="is_fx_sensitive" label="Exchange-rate sensitive" hint="Yes flags the item on the PR / PQ screen: price follows the exchange rate." :options="$yesNo" :value="$yn('is_fx_sensitive')" :source="$s('is_fx_sensitive')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:uom>

    <x-slot:inventory>
        <x-dl::card title="Stock limits">
            <x-dl::form.row :cols="4">
                <x-dl::field.decimal name="min_stock" label="Min stock" :toggle="false" :value="$v('min_stock')" :source="$s('min_stock')" />
                <x-dl::field.decimal name="safety_stock" label="Safety stock" :toggle="false" :value="$v('safety_stock')" :source="$s('safety_stock')" />
                <x-dl::field.decimal name="reorder_point" label="Reorder point" :toggle="false" :value="$v('reorder_point')" :source="$s('reorder_point')" />
                <x-dl::field.decimal name="max_stock" label="Max stock" :toggle="false" :value="$v('max_stock')" :source="$s('max_stock')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Location and balance">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="def_location_id" label="Default warehouse location" hint="Not yet linked to the Warehouse Master (wh_warehouse): free text for now." :case-toggle="false" :value="$v('def_location_id')" :source="$s('def_location_id')" />
                <x-dl::field.display label="Stock on hand" :value="$new ? '—' : raw(Locale::number((float) $m->cur_stock, 2).' '.$m->uom_stock)" :source="$s('cur_stock')" />
                <x-dl::field.display label="Moving average price" :value="$new ? '—' : $idr($m->avg_unitprice)" :source="$s('avg_unitprice')" />
            </x-dl::form.row>
            <x-dl::hint note>Stock and average price are managed by the system (Purchase Receive posting), not edited here.</x-dl::hint>
        </x-dl::card>
    </x-slot:inventory>

    <x-slot:accounting>
        <x-dl::card title="Link to chart of account (directly to fin_account)">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="valuation_class" label="Classification label (optional)" placeholder="e.g. Raw Material - Paper" hint="Report label only; it does not determine the account." :value="$v('valuation_class')" :source="$s('valuation_class')" />
                <x-dl::field.select name="stock_asset_account_id" label="Inventory asset account" placeholder="None" :options="$accounts" :value="$v('stock_asset_account_id')" :source="$s('stock_asset_account_id')" />
                <x-dl::field.select name="cogs_account_id" label="COGS / consumption expense account" placeholder="None" :options="$accounts" :value="$v('cogs_account_id')" :source="$s('cogs_account_id')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="gr_ir_account_id" label="GR/IR clearing account" placeholder="None" hint="Used at PV posting (goods received, no invoice yet)." :options="$accounts" :value="$v('gr_ir_account_id')" :source="$s('gr_ir_account_id')" />
                <x-dl::field.select name="cost_center_id" label="Default cost center" placeholder="None" :options="$svc->costCenterOptions()" :value="$v('cost_center_id')" :source="$s('cost_center_id')" />
                <x-dl::field.select name="profit_center_id" label="Profit center" placeholder="None" :options="$svc->profitCenterOptions()" :value="$v('profit_center_id')" :source="$s('profit_center_id')" />
            </x-dl::form.row>
        </x-dl::card>
        @unless ($new)
            <x-dl::card title="Confirm status">
                @if ($locked)
                    <x-dl::hint note>Confirmed by {{ $m->confirmed_by }} on {{ $m->confirmed_at?->format('d-M-Y H:i') }}. Category and item code are locked.</x-dl::hint>
                @else
                    <x-dl::hint note>This item is still Draft. Fill in the three accounts above (inventory asset, COGS, GR/IR), then press Confirm: the category and item code are locked permanently and the item appears in PR / PQ / PRT search.</x-dl::hint>
                @endif
            </x-dl::card>
        @endunless
    </x-slot:accounting>
</x-dl::form>
