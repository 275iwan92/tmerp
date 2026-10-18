{{--
 REMARK
 Tujuan       : Form aset tetap v2 (menggantikan halaman v1 pages::finance.assets): kartu Start (pilih kategori), isian aset, Confirm = Capitalize, Dispose (dialog), tab Schedule (jadwal penyusutan realisasi + proyeksi) dan History.
 Sumber hulu  : tm2acc.fin_asset, fin_asset_category, fin_asset_depr, fin_voucher (kapitalisasi / pelepasan); aturan di App\Services\Acc\FixedAssetService, rumus dan jurnal di AssetService.
 Input        : Query id (aset), atau category (aset baru dari kategori itu); new=1 menampilkan kartu Start; session v2_flash dan errors.
 Output       : POST v2.acc.asset.save (Save / Confirm), POST v2.acc.asset.dispose (dialog Dispose).
 Dipakai oleh : Dibuka dari grid acc-fixed-asset (Open dan + New Record); penyusutan bulanan di layar acc-depreciation-run.
 Status       : Aktif. Hanya Draft yang bisa diubah; Active / Fully depreciated hanya bisa Dispose; tanpa Delete (sama seperti v1).
--}}
@php
    use App\Models\Acc\FinAsset;
    use App\Models\Acc\FinAssetCategory;
    use App\Services\Acc\FixedAssetService as Fa;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'acc-fixed-asset');
    $svc = app(Fa::class);
    $a = request('id') ? FinAsset::with('category')->find((int) request('id')) : null;
    abort_if(request('id') && ! $a, 404);
    abort_unless($a || request('new') || request('category'), 404);
    $new = ! $a;
    $start = $new && ! request('category');
    $cat = $new ? ($start ? null : FinAssetCategory::findOrFail((int) request('category'))) : $a->category;
    $def = $new && $cat ? $svc->defaults($cat) : [];
    [$state, $stage] = $new ? ['new', null] : $svc->formState($a);
    $draft = $new || $a->status === 'DRAFT';
    $active = ! $new && in_array($a->status, ['ACTIVE', 'FULLY_DEPRECIATED'], true);
    $acc = $new ? 0.0 : $svc->accumulated($a);
    $idr = fn ($v) => 'IDR '.Locale::number((float) $v, 2);
    $s = fn (string $col) => Src::of('tm2acc.fin_asset.'.$col);
    $val = fn (string $col, $default = null) => old($col, $default);
    $tabs = ['general' => 'General'] + (! $new && ! $draft ? ['schedule' => 'Schedule'] : []);
    $methods = Fa::METHODS;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($start)
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'acc-fixed-asset-form') }}">
        <x-dl::card title="Start a fixed asset" description="Choose the category first: useful life, method and the capitalization counter account are filled in from it.">
            <x-dl::field.select name="category" label="Category" required :options="$svc->categoryOptions()" :source="Src::of('tm2acc.fin_asset_category')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Start" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    <x-dl::form id="acc-fixed-asset-form" :title="$new ? 'New Fixed Asset' : 'Fixed Asset'" :subtitle="$new ? raw($cat->category_name) : raw($a->asset_name)"
        panel="top" kind="document" :status="$state" :stage="$stage" :pipeline="false"
        :action="route('v2.acc.asset.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['delete', 'cancel', 'email']"
        :hidden="$new ? [] : ['id' => $a->asset_id]" :tabs="$tabs" :log="$new ? [] : $svc->history($a)"
        :created-by="$a?->created_by" :created-at="$a?->created_at?->toDateTimeString()" :updated-by="$a?->updated_by" :updated-at="$a?->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if ($active)
                <x-dl::button type="button" variant="primary" icon="fa-hand-holding-dollar" label="Dispose" data-dl-open="fa-dispose" />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Asset code" :value="$new ? t('(on Save)') : $a->asset_code" />
            <x-dl::field.decimal name="cost" label="Acquisition cost (IDR)" required :value="$val('cost', $a ? (float) $a->acquisition_cost : null)" :source="$s('acquisition_cost')" />
            <x-dl::strip-kv kind="grand" label="Accumulated" :value="$idr($acc)" />
            <x-dl::strip-kv kind="grand" label="Net book value" :value="$new ? '—' : $idr((float) $a->acquisition_cost - $acc)" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="1">
                <x-dl::field.text name="asset_name" label="Asset name" required placeholder="Mesin Cetak Offset Komori 4 warna" :value="$val('asset_name', $a?->asset_name)" :source="$s('asset_name')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="category_id" label="Category" required :options="$svc->categoryOptions($a?->category_id)" :value="$val('category_id', $a?->category_id ?? $cat?->category_id)" :source="$s('category_id')" />
                <x-dl::field.select name="cc_id" label="Cost center" placeholder="None" :options="$svc->costCenterOptions()" :value="$val('cc_id', $a?->cc_id)" :source="$s('cc_id')" />
                <x-dl::field.text name="location" label="Location" placeholder="Pabrik, Ruang Cetak" :value="$val('location', $a?->location)" :source="$s('location')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.date name="acq_date" label="Acquisition date" required :value="$val('acq_date', $a?->acquisition_date?->toDateString() ?? ($def['acq_date'] ?? null))" :source="$s('acquisition_date')" />
                <x-dl::field.date name="start_date" label="Depreciation start" required hint="Usually the first day of the acquisition month." :value="$val('start_date', $a?->depr_start_date?->toDateString() ?? ($def['start_date'] ?? null))" :source="$s('depr_start_date')" />
                <x-dl::field.decimal name="residual" label="Residual value (IDR)" required :value="$val('residual', $a ? (float) $a->residual_value : ($def['residual'] ?? 0))" :source="$s('residual_value')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="life" label="Useful life (months)" required :value="$val('life', $a?->useful_life_months ?? ($def['life'] ?? null))" :source="$s('useful_life_months')" />
                <x-dl::field.select name="method" label="Method" required :options="$methods" :value="$val('method', $a?->method ?? ($def['method'] ?? 'SL'))" :source="$s('method')" />
                <x-dl::field.text name="source_ref" label="Source document" placeholder="BILL-AP-00012 / contract" :case-toggle="false" :value="$val('source_ref', $a?->source_ref)" :source="$s('source_ref')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="1">
                <x-dl::field.select name="credit_account_id" label="Capitalization credit account" required :options="$svc->creditOptions($a?->credit_account_id)" :value="$val('credit_account_id', $a?->credit_account_id ?? ($def['credit_account_id'] ?? null))"
                    hint="The counter side of the capitalization journal: accounts payable, bank, or GR/IR Clearing when the goods are already received." :source="$s('credit_account_id')" />
            </x-dl::form.row>
            @if (! $new && $a->status === 'DISPOSED')
                <x-dl::card title="Disposal">
                    <x-dl::form.row :cols="2">
                        <x-dl::field.display label="Disposal date" :value="Locale::date($a->disposal_date)" :source="$s('disposal_date')" />
                        <x-dl::field.display label="Proceeds" :value="$idr($a->disposal_amount)" :source="$s('disposal_amount')" />
                    </x-dl::form.row>
                </x-dl::card>
            @endif
        </x-slot:general>
        @if (isset($tabs['schedule']))
            <x-slot:schedule>
                <x-dl::table :columns="['month' => 'Month', 'amount' => ['Charge', 'num' => true], 'accumulated' => ['Accumulated', 'num' => true], 'nbv' => ['Book value', 'num' => true], 'status' => 'Status']"
                    :rows="array_map(fn ($r) => ['month' => $r['month'], 'amount' => Locale::number($r['amount'], 2), 'accumulated' => Locale::number($r['accumulated'], 2), 'nbv' => Locale::number($r['nbv'], 2), 'status' => $r['status'] === 'POSTED' ? 'Posted' : 'Planned'], $svc->schedule($a))"
                    :source="Src::of('tm2acc.fin_asset_depr')" />
            </x-slot:schedule>
        @endif
    </x-dl::form>

    @if ($active)
        <x-dl::modal id="fa-dispose" title="Dispose Asset" icon="fa-hand-holding-dollar" size="md">
            <x-dl::action-form action="{{ route('v2.acc.asset.dispose', $a->asset_id) }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.date name="disp_date" label="Disposal date" required :value="now()->toDateString()" :source="$s('disposal_date')" />
                    <x-dl::field.decimal name="disp_amount" label="Proceeds (IDR)" required :value="0" :source="$s('disposal_amount')" />
                </x-dl::form.row>
                <x-dl::field.select name="disp_bank" label="Proceeds to" required :options="$svc->bankOptions()" :value="(int) \App\Models\Acc\FinAccount::where('account_code', '111200')->value('account_id')" :source="Src::of('tm2acc.fin_account')" />
                <x-dl::alert tone="info" message="Journal: Dr accumulated depreciation + Dr cash (if any) / Cr acquisition cost; the difference goes to other income or other expense." />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" label="Post Disposal" />
                </x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::modal>
    @endif
@endif
