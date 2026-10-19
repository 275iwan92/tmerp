{{--
 REMARK
 Tujuan       : Form Tipe Scrap v2 (menggantikan halaman v1 pages::production.scrap-type): kategori (Reject / Waste), kode, nama, urutan tampil, aktif / nonaktif, jumlah pemakaian di catatan output produksi, tab History.
 Sumber hulu  : tm2prd.prd_scrap_type dan prd_wo_operation_output_defect (jumlah pemakaian); aturan di App\Services\Production\ScrapTypeService.
 Input        : Query id (tipe) atau new=1; session v2_flash dan errors.
 Output       : POST v2.prod.scrap.save (Save / Activate / Deactivate).
 Dipakai oleh : Dibuka dari grid prod-scrap (Open dan + New Record).
 Status       : Aktif (Master T6b). Kategori dan kode tampil sebagai teks (tidak bisa diubah) bila tipe sudah dipakai di catatan output produksi.
--}}
@php
    use App\Models\Prd\PrdScrapType;
    use App\Services\Production\ScrapTypeService as Sc;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'prod-scrap');
    $svc = app(Sc::class);
    $t = request('id') ? PrdScrapType::find((int) request('id')) : null;
    abort_if(request('id') && ! $t, 404);
    abort_unless($t || request('new'), 404);
    $new = ! $t;
    $t ??= new PrdScrapType(['activate' => true, 'category' => 'REJECT', 'sort_order' => 500]);
    $used = $new ? 0 : $svc->usage($t);
    $src = fn (string $col) => Src::of('tm2prd.prd_scrap_type.'.$col);
    $v = fn (string $col) => old($col, $t->{$col});
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="prod-scrap-form" :title="$new ? 'New Scrap Type' : raw($t->type_name)" :subtitle="$new ? null : raw($t->type_code)"
    panel="top" kind="master" :status="$new ? 'new' : ($t->activate ? 'active' : 'inactive')"
    :action="route('v2.prod.scrap.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $t->type_id]" :tabs="['type' => t('Scrap Type')]" :log="$new ? [] : $svc->history($t)"
    :created-by="$t->created_by" :created-at="$t->created_at?->toDateTimeString()" :updated-by="$t->updated_by" :updated-at="$t->updated_at?->toDateTimeString()">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Code" :value="$new ? t('(on Save)') : raw($t->type_code)" />
        <x-dl::strip-kv kind="grand" label="Category" :value="$new ? '—' : raw(t(ucfirst(strtolower($t->category))))" />
        <x-dl::strip-kv kind="grand" label="Sort order" :value="$new ? '—' : (string) $t->sort_order" />
        <x-dl::strip-kv kind="grand" label="Used in output records" :value="$new ? '—' : Locale::number((float) $used, 0)" />
    </x-slot:strip>
    <x-slot:type>
        <x-dl::card title="Scrap Type">
            <x-dl::form.row :cols="4">
                @if ($used)
                    <x-dl::field.display label="Category" :value="raw(t(Sc::CATEGORIES[$t->category] ?? $t->category))" :source="$src('category')" />
                    <x-dl::field.display label="Code" :value="raw($t->type_code)" :source="$src('type_code')" />
                @else
                    <x-dl::field.select name="category" label="Category" required :options="array_map('t', Sc::CATEGORIES)" :value="$v('category')" :source="$src('category')" />
                    <x-dl::field.text name="type_code" label="Code" required placeholder="e.g. REGISTER_ERROR" hint="Unique within the category. Letters, digits, underscore or hyphen; spaces become underscores." :value="$v('type_code')" :source="$src('type_code')" />
                @endif
                <x-dl::field.text name="type_name" label="Type name" required :value="$v('type_name')" :source="$src('type_name')" />
                <x-dl::field.number name="sort_order" label="Sort order" hint="Lower numbers appear first. Empty = 500 for a new type." :value="$v('sort_order')" :source="$src('sort_order')" />
            </x-dl::form.row>
            @if ($used)
                <x-dl::hint note>This type is used in {{ $used }} production output record(s), so its category and code are locked. To change them, deactivate this type and add a new one.</x-dl::hint>
            @endif
        </x-dl::card>
    </x-slot:type>
</x-dl::form>
