{{-- Material Category v2 = PEMBENTUK KODE ITEM (user 2026-10-03, docs/globaluserrules.md B.16; rinci docs/md_for_systems/matcategory_delta.md §5).
     Dropdown saling terkait Category 1 -> 5 (objek DevLib x-dl::code-chain): tiap memilih, form baris itu terbuka dan grid di
     bawahnya berisi tingkat berikutnya; Add = entri baru di bawah rantai yang sedang dipilih. Aturan rantai dan kode ada di
     App\Services\Master\MaterialCategoryService; layar ini hanya menampilkan. --}}
@php
    use App\Models\Acc\FinAccount;
    use App\Models\Master\MatCategory;
    use App\Services\Master\MaterialCategoryService;
    use App\Support\V2\Src;

    $grid = $screen['grid'];
    $svc = $grid->service();
    $home = route('v2.screen', 'md-matcat');
    $node = request('id') ? $svc->all()->get((int) request('id')) : null;
    abort_if(request('id') && ! $node, 404);
    $newLevel = ! $node && request('new') ? (int) request('new') : 0;
    abort_if(request('new') && ! $node && ($newLevel < 1 || $newLevel > MaterialCategoryService::LEVELS), 404);
    $parent = $newLevel > 1 ? $svc->all()->get((int) request('parent')) : null;
    abort_if($newLevel > 1 && (! $parent || (int) $parent->level !== $newLevel - 1 || ! $svc->complete($parent)), 404);
    $levels = array_map(fn ($i) => 'Category '.$i, range(1, MaterialCategoryService::LEVELS));
    $problems = $svc->problems();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
@if ($problems && ! $node && ! $newLevel)
    <x-dl::alert tone="warn" :message="raw(tf('Entries with a broken chain: :n. They cannot build an item code and are marked red in the list.', ['n' => count($problems)]))" />
@endif

<x-dl::code-chain name="category" :nodes="$svc->nodes()" :levels="$levels" :value="$node?->id_category ?? $parent?->id_category" code-label="Item Code"
    :navigate="$home.'?id={id}'" :add-url="$home.'?new={level}&parent={parent}'" inactive
    :source="Src::of('tm2mst.mat_category.category_name', 'tm2mst.mat_category.prefix_char')" />
<br>

@if ($node || $newLevel)
    @php
        $new = ! $node;
        $lv = $new ? $newLevel : (int) $node->level;
        $above = $new ? $parent : ($node->parent_id ? $svc->all()->get($node->parent_id) : null);
        $path = implode(' › ', $svc->names($above));
        $broken = ! $new && isset($problems[$node->id_category]);
        $used = $new ? 0 : $svc->usage($node);
        $below = $new ? 0 : $svc->children($node->id_category)->count();
        $locked = ! $new && ($used > 0 || $below > 0);
        $max = MaterialCategoryService::prefixLength($lv);
        $v = $node ?? new MatCategory(['stock_class' => 'RAW', 'activate' => true]);
        $accounts = $lv === 1 ? FinAccount::query()->where('is_postable', true)->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn (FinAccount $a) => [$a->account_id => $a->account_code.' - '.$a->account_name])->all() : [];
        $accountOptions = ['' => t('No default')] + $accounts;
    @endphp
    @if ($broken)
        <x-dl::alert tone="danger" :message="raw(tf('Broken chain (:why): this entry does not sit under a complete chain, so it cannot build an item code and cannot be picked.', ['why' => t($problems[$node->id_category])]))" />
    @endif
    <x-dl::form id="matcat-form" :title="'Category '.$lv.' · '.($new ? 'New' : raw($node->category_name))" :subtitle="raw($path !== '' ? $path : t('Top of the chain'))"
        panel="top" kind="master" :status="$new ? 'new' : ($node->activate ? 'active' : 'inactive')" :action="route('v2.matcat.save')" action-field="action"
        :back="$home.($above ? '?id='.$above->id_category : '')" :new-url="$home.'?new='.$lv.($above ? '&parent='.$above->id_category : '')"
        :hidden="$new ? ['level' => $lv, 'parent_id' => $above?->id_category] : ['id' => $node->id_category]"
        :created-by="$v->created_by" :created-at="$v->created_at?->toDateTimeString()" :updated-by="$v->updated_by" :updated-at="$v->updated_at?->toDateTimeString()">
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Under" :value="raw($path !== '' ? $path : t('Top of the chain'))" hint="The position is locked after saving."
                :source="Src::of('tm2mst.mat_category.parent_id')" />
            <x-dl::field.text name="category_name" label="Name" required :maxlength="100" :value="old('category_name', $v->category_name)"
                :hint="$used > 0 ? raw(tf('Renaming also renames the category on :n materials.', ['n' => $used])) : null"
                :source="Src::of('tm2mst.mat_category.category_name')" />
            <x-dl::field.text name="prefix_char" label="Prefix" required code :maxlength="$max" :readonly="$locked" :value="old('prefix_char', $v->prefix_char)"
                :hint="raw($locked ? tf('Locked: :below entries below and :used materials build their item code from it.', ['below' => $below, 'used' => $used])
                    : ($max === 1 ? t('1 character (a letter or a digit).') : t('1 to 3 characters (letters or digits).')))"
                :source="Src::of('tm2mst.mat_category.prefix_char')" />
            <x-dl::field.display label="Item Code" format="mono" :value="raw($new ? $svc->code($above).'…' : ($broken ? '—' : $svc->code($node)))"
                hint="Prefix 1 + 2 + 3 + 4 + 5 along the chain." :source="Src::calc('prefix chain', 'tm2mst.mat_category.prefix_char')" />
        </x-dl::form.row>
        @if ($lv === 1)
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="stock_class" label="Stock Class" required :options="array_combine(MaterialCategoryService::STOCK_CLASSES, MaterialCategoryService::STOCK_CLASSES)"
                    :value="old('stock_class', $v->stock_class ?: 'RAW')" :source="Src::of('tm2mst.mat_category.stock_class')" />
                <x-dl::field.select name="default_coa_account_id" label="COGS / Expense Account" :options="$accountOptions" :value="old('default_coa_account_id', $v->default_coa_account_id)"
                    hint="Used when the material has no account of its own." :source="Src::of('tm2mst.mat_category.default_coa_account_id', 'tm2acc.fin_account.account_code')" />
                <x-dl::field.select name="default_stock_asset_account_id" label="Stock Asset Account" :options="$accountOptions" :value="old('default_stock_asset_account_id', $v->default_stock_asset_account_id)"
                    :source="Src::of('tm2mst.mat_category.default_stock_asset_account_id', 'tm2acc.fin_account.account_code')" />
                <x-dl::field.select name="default_gr_ir_account_id" label="GR/IR Clearing Account" :options="$accountOptions" :value="old('default_gr_ir_account_id', $v->default_gr_ir_account_id)"
                    :source="Src::of('tm2mst.mat_category.default_gr_ir_account_id', 'tm2acc.fin_account.account_code')" />
            </x-dl::form.row>
        @endif
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Entries Below" format="number" :value="$below" :source="Src::calc('rows with this parent', 'tm2mst.mat_category.parent_id')" />
            <x-dl::field.display label="Materials Using It" format="number" :value="$used" :source="Src::calc('materials whose category 1..5 follow this chain', 'tm2mst.mat_raw.category1')" />
            <x-dl::field.text name="note" label="Note" multiline :maxlength="2000" :value="old('note', $v->note)" :source="Src::of('tm2mst.mat_category.note')" />
        </x-dl::form.row>
    </x-dl::form>
    <br>
@endif

@include('v2.screens._dbgrid', ['screen' => $screen])
