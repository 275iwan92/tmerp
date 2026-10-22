{{--
 REMARK
 Tujuan       : Form jadwal amortisasi v2 (menggantikan halaman v1 pages::finance.amortization): kartu Start (pilih kategori), isian jadwal, Save = buat jadwal + catat jurnal, tab Schedule (beban, akumulasi, sisa per bulan) dan History.
 Sumber hulu  : tm2acc.fin_asset (kategori PREPAID / INTANGIBLE / LEASEHOLD), fin_asset_category, fin_asset_depr, fin_voucher (kapitalisasi); aturan di App\Services\Acc\AmortizationScheduleService, rumus dan jurnal di AssetService.
 Input        : Query id (jadwal), atau category (jadwal baru dari kategori itu); new=1 menampilkan kartu Start; session v2_flash dan errors.
 Output       : POST v2.acc.amort.save (Save; Confirm hanya untuk jadwal Draft peninggalan v1).
 Dipakai oleh : Dibuka dari grid acc-amortization (Open dan + New Record); amortisasi bulanan di layar acc-depreciation-run (Assets = Prepaid, intangible and leasehold).
 Status       : Aktif. Jadwal baca-saja sesudah dibuat; tanpa edit dan tanpa Dispose (sama seperti v1).
--}}
@php
    use App\Models\Acc\FinAsset;
    use App\Models\Acc\FinAssetCategory;
    use App\Services\Acc\AmortizationScheduleService as Am;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'acc-amortization');
    $svc = app(Am::class);
    $a = request('id') ? FinAsset::with('category')->find((int) request('id')) : null;
    abort_if(request('id') && ! $a, 404);
    abort_unless($a || request('new') || request('category'), 404);
    $new = ! $a;
    $start = $new && ! request('category');
    $cat = $new ? ($start ? null : FinAssetCategory::findOrFail((int) request('category'))) : $a->category;
    $def = $new && $cat ? $svc->defaults($cat) : [];
    [$state, $stage] = $new ? ['new', null] : $svc->formState($a);
    $acc = $new ? 0.0 : $svc->accumulated($a);
    $idr = fn ($v) => 'IDR '.Locale::number((float) $v, 2);
    $s = fn (string $col) => Src::of('tm2acc.fin_asset.'.$col);
    $val = fn (string $col, $default = null) => old($col, $default);
    $tabs = ['general' => 'General'] + (! $new && $a->status !== 'DRAFT' ? ['schedule' => 'Schedule'] : []);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($start)
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'acc-amortization-form') }}">
        <x-dl::card title="Start an amortization schedule" description="Choose the category first: the period in months and the paid-from account are filled in from it.">
            <x-dl::field.select name="category" label="Category" required :options="$svc->categoryOptions()" :source="Src::of('tm2acc.fin_asset_category')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Start" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    <x-dl::form id="acc-amortization-form" :title="$new ? 'New Amortization Schedule' : 'Amortization Schedule'" :subtitle="$new ? raw($cat->category_name) : raw($a->asset_name)"
        panel="top" kind="document" :status="$state" :stage="$stage" :pipeline="false" :can-input="$new"
        :action="route('v2.acc.amort.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="$new ? [] : ['delete', 'save', 'undo', 'cancel', 'email']"
        :hidden="$new ? [] : ['id' => $a->asset_id]" :tabs="$tabs" :log="$new ? [] : $svc->history($a)"
        :created-by="$a?->created_by" :created-at="$a?->created_at?->toDateTimeString()" :updated-by="$a?->updated_by" :updated-at="$a?->updated_at?->toDateTimeString()">
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Reference" :value="$new ? t('(on Save)') : $a->asset_code" />
            <x-dl::field.decimal name="cost" label="Amount (IDR)" required :value="$val('cost', $a ? (float) $a->acquisition_cost : null)" :source="$s('acquisition_cost')" />
            <x-dl::strip-kv kind="grand" label="Monthly expense" :value="$new ? '—' : $idr($svc->monthly($a))" />
            <x-dl::strip-kv kind="grand" label="Amortized" :value="$idr($acc)" />
            <x-dl::strip-kv kind="grand" label="Unamortized balance" :value="$new ? '—' : $idr((float) $a->acquisition_cost - $acc)" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="1">
                <x-dl::field.text name="asset_name" label="Schedule name" required placeholder="Factory and vehicle insurance 2027" :value="$val('asset_name', $a?->asset_name)" :source="$s('asset_name')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="category_id" label="Category" required :options="$svc->categoryOptions($a?->category_id)" :value="$val('category_id', $a?->category_id ?? $cat?->category_id)" :source="$s('category_id')" />
                <x-dl::field.date name="acq_date" label="Document date" required :value="$val('acq_date', $a?->acquisition_date?->toDateString() ?? ($def['acq_date'] ?? null))" :source="$s('acquisition_date')" />
                <x-dl::field.date name="start_date" label="Amortization start" required :value="$val('start_date', $a?->depr_start_date?->toDateString() ?? ($def['start_date'] ?? null))" :source="$s('depr_start_date')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="life" label="Period (months)" required :value="$val('life', $a?->useful_life_months ?? ($def['life'] ?? null))" :source="$s('useful_life_months')" />
                <x-dl::field.text name="source_ref" label="Reference" placeholder="Policy no. / contract" :case-toggle="false" :value="$val('source_ref', $a?->source_ref)" :source="$s('source_ref')" />
                <x-dl::field.select name="credit_account_id" label="Paid from / payable to" required :options="$svc->creditOptions($a?->credit_account_id)" :value="$val('credit_account_id', $a?->credit_account_id ?? ($def['credit_account_id'] ?? null))" :source="$s('credit_account_id')" />
            </x-dl::form.row>
            @if (! $new)
                <x-dl::alert tone="info" :message="'Period '.Locale::date($a->depr_start_date).' to '.Locale::date($a->depr_start_date->copy()->addMonths((int) $a->useful_life_months)->subDay()).' · '.$a->useful_life_months.' months'" />
            @endif
        </x-slot:general>
        @if (isset($tabs['schedule']))
            <x-slot:schedule>
                <x-dl::table :columns="['month' => 'Month', 'amount' => ['Expense', 'num' => true], 'accumulated' => ['Amortized', 'num' => true], 'nbv' => ['Remaining', 'num' => true], 'status' => 'Status']"
                    :rows="array_map(fn ($r) => ['month' => $r['month'], 'amount' => Locale::number($r['amount'], 2), 'accumulated' => Locale::number($r['accumulated'], 2), 'nbv' => Locale::number($r['nbv'], 2), 'status' => $r['status'] === 'POSTED' ? 'Posted' : 'Planned'], $svc->schedule($a))"
                    :source="Src::of('tm2acc.fin_asset_depr')" />
            </x-slot:schedule>
        @endif
    </x-dl::form>
@endif
