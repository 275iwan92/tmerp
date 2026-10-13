{{-- Finance > Control Center > Process Engine (v2). Data = App\Support\V2\Data\FinanceDesk (FinanceMetrics + fin_approval_rule), sama dengan v1: volume dokumen, antrian approval,
     kesiapan tutup buku, alur posting baku, peta integrasi modul, kontrol yang perlu perhatian, dan aturan workflow (ambang nominal + grup penyetuju + pemisahan tugas). --}}
@php
    use App\Support\V2\Data\FinanceDesk as Desk;
    use App\Support\V2\Src;

    $m = Desk::metrics()->engine();
    $rules = Desk::rules();
    $src = Src::of('tm2acc.fin_approval_rule', 'tm2acc.fin_voucher', 'tm2acc.fin_period');
    $home = route('v2.screen', 'acc-engine');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Accounting Process Engine" description="End-to-end accounting operating model: source transaction → validation → approval → subledger → general ledger → close → reporting.">
    <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-download" label="Export Rules" :href="route('v2.acc.engine.rules-csv')" /></x-dl::action-bar>
</x-dl::card>

<x-dl::kpi-row>
    @foreach (Desk::engineKpis($m) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</x-dl::kpi-row>

<x-dl::flow title="Canonical Accounting Posting Flow" :steps="Desk::postingFlow($m)" :source="$src" />

<x-dl::card title="Module Integration Map" description="Where business activity becomes accounting data.">
    @foreach (Desk::integrationMap() as $i)
        <x-dl::field.display :label="$i['title']" :value="$i['text']" />
        <x-dl::button variant="secondary" size="sm" icon="fa-arrow-right" label="Open" :href="$i['url']" />
    @endforeach
</x-dl::card>

<x-dl::card title="Controls Requiring Attention" description="Exceptions block or qualify close.">
    @foreach (Desk::cards(Desk::metrics()->attention()) as $c)
        <x-dl::status-card :title="raw($c['title'])" :desc="raw($c['desc'])" :status="$c['status']" :tone="$c['tone']" :icon="$c['icon']" />
    @endforeach
    <x-dl::action-bar><x-dl::button variant="secondary" size="sm" icon="fa-lock" label="Closing tasks" :href="route('v2.screen', 'acc-closing')" /></x-dl::action-bar>
</x-dl::card>

<x-dl::card title="Workflow Rules" description="Amount threshold and approver group per process; segregation of duties stops the creator from approving their own document.">
    <x-dl::table :columns="['rule' => 'Rule', 'process' => 'Process', 'min' => ['Minimum amount', 'num' => true], 'group' => 'Approver Group', 'sod' => 'Segregation of Duties', 'status' => 'Status']"
        :rows="$rules->map(fn ($r) => ['rule' => raw($r->rule_name), 'process' => t(Desk::PROCESSES[$r->process_code] ?? $r->process_code), 'min' => \Devlib\Locale::money((float) $r->min_amount),
            'group' => raw($r->approver_group), 'sod' => $r->segregation_of_duties ? t('Enforced') : t('Not enforced'), 'status' => $r->activate ? t('Active') : t('Inactive')])->all()"
        empty="There is no rule yet — without rules, a manual journal can be posted without approval." :source="$src" />
    <x-dl::action-bar>
        @foreach ($rules as $r)
            <x-dl::action-form action="{{ route('v2.acc.engine.rule-toggle', $r->rule_id) }}">
                <x-dl::button size="sm" :variant="$r->activate ? 'secondary' : 'ghost'" :icon="$r->activate ? 'fa-toggle-on' : 'fa-toggle-off'" :label="raw(($r->activate ? t('Disable') : t('Enable')).' · '.$r->rule_name)" />
            </x-dl::action-form>
        @endforeach
    </x-dl::action-bar>

    <x-dl::action-form action="{{ route('v2.acc.engine.rule-save') }}">
        <x-dl::form.row :cols="4">
            <x-dl::field.text name="rule_name" label="Rule name" required placeholder="e.g. Manual journal above 500 million" :source="Src::of('tm2acc.fin_approval_rule.rule_name')" />
            <x-dl::field.select name="process_code" label="Process" required :options="array_map('t', Desk::PROCESSES)" :value="old('process_code', 'JOURNAL')" :placeholder="null" :source="Src::of('tm2acc.fin_approval_rule.process_code')" />
            <x-dl::field.number name="min_amount" label="Minimum amount (IDR)" :value="old('min_amount', 0)" hint="0 = applies to every amount." :source="Src::of('tm2acc.fin_approval_rule.min_amount')" />
            <x-dl::field.select name="approver_group" label="Approver group" required :options="Desk::approverGroups()" :value="old('approver_group', 'DIREKSI')" :placeholder="null" :source="Src::of('tm2acc.fin_approval_rule.approver_group')" />
        </x-dl::form.row>
        <x-dl::field.checkbox name="sod" text="The creator may not approve" :value="true" :source="Src::of('tm2acc.fin_approval_rule.segregation_of_duties')" />
        <x-dl::action-bar><x-dl::button preset="save" label="Create Workflow Rule" /></x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>
