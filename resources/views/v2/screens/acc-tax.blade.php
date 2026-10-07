{{-- Finance > Controls & Tax > Indonesian Tax (v2). Data & aturan = App\Support\V2\Data\TaxDesk (TaxEngine + fin_tax_register + tm2mst.fin_tax_scheme, sama dengan v1):
     PPN, PPh withholding, provisi PPh Badan, rekonsiliasi pajak dan workpaper pelaporan. Nilai diambil dari mutasi GL periode terkait, bukan input manual. --}}
@php
    use App\Models\Acc\FinTaxRegister;
    use App\Support\V2\Data\TaxDesk as Tax;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $period = preg_match('/^\d{4}-\d{2}$/', (string) request('period')) ? request('period') : Tax::defaultPeriod();
    $type = (string) request('type');
    $search = trim((string) request('q'));
    $current = Tax::current($period);
    $schemes = Tax::schemes();
    $registers = Tax::registers($type, $search);
    $home = route('v2.screen', 'acc-tax');
    $src = Src::of('tm2acc.fin_tax_register', 'tm2mst.fin_tax_scheme', 'tm2acc.fin_voucher');
    $reg = request('reg') ? FinTaxRegister::find(request()->integer('reg')) : null;
    $label = Tax::STATUS_LABELS;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Indonesian Tax" description="PPN, PPh withholding, PPh Badan provision, tax reconciliation, and filing workpapers.">
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="period" label="Tax Period" :options="Tax::periodOptions()" :value="$period" :placeholder="null" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            <x-dl::button variant="secondary" icon="fa-download" label="Export Summary" :href="route('v2.acc.tax.csv', ['type' => $type, 'q' => $search])" />
        </x-dl::action-bar>
    </x-dl::action-form>
    <x-dl::action-bar>
        <x-dl::action-form action="{{ route('v2.acc.tax.provision') }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <x-dl::button variant="secondary" icon="fa-file-contract" label="Update CIT Provision" data-dl-confirm="{{ t('Book or adjust the corporate income tax provision now?') }}" />
        </x-dl::action-form>
        <x-dl::action-form action="{{ route('v2.acc.tax.generate') }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <x-dl::button icon="fa-plus" label="New Workpaper" />
        </x-dl::action-form>
    </x-dl::action-bar>
</x-dl::card>

<div class="dl-grid__stats">
    @foreach (Tax::kpis($current, $period) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</div>

<x-dl::flow title="Tax-to-Accounting Flow" :steps="Tax::flow()" :source="$src" />

<x-dl::card title="Tax Compliance Calendar" description="Upcoming filing and payment obligations.">
    @forelse (Tax::calendar() as $r)
        @php [$dueText, $dueTone] = Tax::dueTag($r->payment_due); @endphp
        <x-dl::status-card :title="raw($r->description)" :desc="raw(t('Payment').' '.Locale::d($r->payment_due).' · '.t('Filing').' '.($r->filing_due ? Locale::d($r->filing_due) : t('Annual')))" :status="$dueText" :tone="$dueTone === 'danger' ? 'danger' : 'warn'" icon="percent" />
    @empty
        <x-dl::alert tone="info" message="There is no open tax obligation." />
    @endforelse
</x-dl::card>

<x-dl::card title="Tax Reconciliation Status" :description="raw(Tax::monthName($period).' '.t('book-to-tax controls.'))">
    @foreach (Tax::reconciliation($period) as $r)
        <x-dl::status-card :title="raw($r[0])" :desc="raw($r[1])" :status="t($r[2])" :tone="$r[2] === 'Matched' ? 'success' : 'warn'" :icon="$r[2] === 'Matched' ? 'circle-check' : 'triangle-exclamation'" />
    @endforeach
</x-dl::card>

@if ($reg)
    <x-dl::card title="Register Actions" :description="raw($reg->register_code.' · '.$reg->description.' · '.t($label[$reg->status] ?? $reg->status).' · '.Locale::money((float) $reg->payable_amount))">
        @if (Tax::canPay($reg))
            <x-dl::action-form action="{{ route('v2.acc.tax.pay', $reg->tax_reg_id) }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <x-dl::form.row :cols="3">
                    <x-dl::field.date name="pay_date" label="Payment date" required :value="now()->toDateString()" :source="$src" />
                    <x-dl::field.text name="ntpn" label="NTPN" required placeholder="16 characters from the payment slip" :case-toggle="false" :source="Src::of('tm2acc.fin_tax_register.ntpn')" />
                    <x-dl::field.select name="pay_bank" label="Pay from" required :options="Tax::bankOptions()" :value="\App\Models\Acc\FinAccount::where('account_code', '111200')->value('account_id')" :placeholder="null" :source="$src" />
                </x-dl::form.row>
                <x-dl::alert tone="info" message="PPN: Dr Output VAT / Cr Input VAT / Cr Bank for the amount underpaid. PPh: Dr tax payable / Cr Bank." />
                <x-dl::action-bar><x-dl::button icon="fa-money-bill-transfer" label="Post Payment" data-dl-confirm="{{ t('Post this tax payment now?') }}" /></x-dl::action-bar>
            </x-dl::action-form>
            <x-dl::action-form action="{{ route('v2.acc.tax.review', $reg->tax_reg_id) }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <x-dl::button variant="secondary" icon="fa-magnifying-glass" label="Mark under review" />
            </x-dl::action-form>
        @endif
        @if (Tax::canFile($reg))
            <x-dl::action-form action="{{ route('v2.acc.tax.file', $reg->tax_reg_id) }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <x-dl::field.text name="filing_ref" label="Filing reference (BPE / e-Bupot)" required placeholder="e.g. BPE-1234567890" :case-toggle="false" :source="Src::of('tm2acc.fin_tax_register.filing_ref')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home.'?period='.$period" />
                    <x-dl::button icon="fa-file-signature" label="Mark SPT filed" />
                </x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </x-dl::card>
@endif

<x-dl::card title="Tax Register" description="Tax obligations, accounting provisions, and filing workflow status.">
    <x-dl::action-form method="get" action="{{ $home }}">
        <input type="hidden" name="period" value="{{ $period }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="q" label="Search" :value="$search" placeholder="Search tax period, type, or reference..." :case-toggle="false" :source="$src" />
            <x-dl::field.select name="type" label="Tax Type" :options="$schemes->pluck('tax_name', 'tax_code')->all()" :value="$type" placeholder="All tax types" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-filter" label="Filter" /></x-dl::action-bar>
    </x-dl::action-form>
    <x-dl::alert tone="info" :message="raw(tf('Active rates: PPN :rate% × DPP :dpp', ['rate' => rtrim(rtrim(number_format((float) ($schemes['PPN_OUT']->rate ?? 0), 2), '0'), '.'), 'dpp' => round((float) ($schemes['PPN_OUT']->dpp_factor ?? 1), 4)]))" />
    <x-dl::table :columns="['type' => 'Tax Type / Period', 'desc' => 'Description', 'base' => ['Output / Base', 'num' => true], 'credit' => ['Input / Credit', 'num' => true], 'payable' => ['Payable', 'num' => true],
            'pay_due' => 'Payment Due', 'file_due' => 'Filing Due', 'status' => 'Workpaper Status']"
        :rows="$registers->map(fn ($r) => ['type' => raw(($schemes[$r->tax_code]->tax_name ?? $r->tax_code).' — '.($r->period_no ? Tax::monthName(sprintf('%04d-%02d', $r->fiscal_year, $r->period_no)) : $r->fiscal_year)),
            'desc' => raw(\Illuminate\Support\Str::after((string) $r->description, '· ')), 'base' => (float) $r->base_amount ? Locale::money((float) $r->base_amount) : '', 'credit' => (float) $r->credit_amount ? Locale::money((float) $r->credit_amount) : '',
            'payable' => Locale::money((float) $r->payable_amount), 'pay_due' => $r->payment_due ? Locale::d($r->payment_due) : '—', 'file_due' => $r->filing_due ? Locale::d($r->filing_due) : t('Annual'),
            'status' => t($label[$r->status] ?? $r->status)])->all()" empty="There is no workpaper yet — choose a tax period and click New Workpaper." :source="$src" />
    <x-dl::action-form method="get" action="{{ $home }}">
        <input type="hidden" name="period" value="{{ $period }}">
        <x-dl::field.select name="reg" label="Register Actions" :options="Tax::actionable()" :value="$reg?->tax_reg_id" placeholder="Choose a register..." hint="Record payment (NTPN), mark under review, or mark the SPT filed." :source="$src" />
        <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-hand-pointer" label="Open" /></x-dl::action-bar>
    </x-dl::action-form>
    <x-dl::alert tone="info" :message="raw(tf(':n register rows · values come from the GL movements of the period — not manual input', ['n' => $registers->count()]))" />
</x-dl::card>
