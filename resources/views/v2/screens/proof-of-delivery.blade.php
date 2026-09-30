{{-- Proof of Delivery v2 (KPI janji kirim semua departemen, user 2026-10-01). Officer pengiriman (Ms. Winda) mencatat tiap
     kedatangan di customer: jam tiba + hasil terima. Tepat = tiba di hari janji, tidak lewat jam slot, dan diterima.
     Aturan = App\Services\Sales\DeliveryPromiseService; data = App\Support\V2\Data\ProofOfDelivery. Versi HP + foto GPS menyusul. --}}
@php
    use App\Models\Sdi\DoDelivery;
    use App\Models\Sdi\DoDeliveryPod;
    use App\Services\Sales\DeliveryPromiseService as Promise;
    use App\Support\V2\Data\ProofOfDelivery;
    use App\Support\V2\Src;
    use Carbon\CarbonImmutable;
    use Devlib\Locale;

    $home = route('v2.screen', 'proof-of-delivery');
    $do = request('id') ? DoDelivery::with('customer')->whereIn('status', ['CONFIRMED', 'SHIPPED'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $do, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $do)
    @php
        $kpi = ProofOfDelivery::kpi(CarbonImmutable::today());
        $prev = ProofOfDelivery::kpi(CarbonImmutable::today()->subMonthNoOverflow());
    @endphp
    <div class="dl-kpis">
        <x-dl::kpi label="Delivery promise kept (this month)" :value="$kpi['pct'] ?? 0" format="percent" :previous="$prev['pct']" :target="100" icon="fa-handshake" tone="success"
            :hint="$kpi['kept'].' / '.$kpi['total'].' shipments'" />
        @foreach ($kpi['by_dept'] as $dept => $n)
            <x-dl::kpi :label="'Missed: '.$dept" :value="$n" good-when="down" icon="fa-triangle-exclamation" tone="danger" />
        @endforeach
    </div>
    <br>
    <x-dl::grid id="pod-list" :rows="ProofOfDelivery::rows()" search="Search delivery note no., customer..." actions="worklist" :active-key="null"
        :new-record="false" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2sdi.do_delivery (CONFIRMED / SHIPPED)', 'tm2sdi.do_delivery_pod') }}"
        :columns="[
            ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no'],
            ['key' => 'promise', 'label' => 'Promised date', 'type' => 'date'],
            ['key' => 'arrived', 'label' => 'Arrived'],
            ['key' => 'result', 'label' => 'Result'],
            ['key' => 'state', 'label' => 'Promise', 'type' => 'badge', 'tones' => ProofOfDelivery::STATE_TONES],
        ]"
        :stats="[['label' => 'All'], ['label' => 'Waiting', 'where' => ['state' => 'Waiting'], 'tone' => 'warn'], ['label' => 'Kept', 'where' => ['state' => 'Kept'], 'tone' => 'success'],
            ['label' => 'Missed', 'where' => ['state' => 'Missed'], 'tone' => 'danger'], ['label' => 'Retry', 'where' => ['state' => 'Retry'], 'tone' => 'info']]"
        :filters="['customer' => 'Customer', 'state' => 'Promise']" />
@else
    @php
        $promise = Promise::promise($do);
        $hours = $promise['date'] ? Promise::receivingHours($do->customer_id, $promise['date']) : null;
        $pods = DoDeliveryPod::where('delivery_id', $do->id_delivery)->orderBy('attempt_no')->get();
        $last = $pods->last();
        $done = $last && in_array($last->result, ['RECEIVED', 'PARTIAL', 'NOT_SHIPPED'], true);
    @endphp
    <x-dl::form id="pod-form" :title="'Proof of Delivery · '.$do->do_no" :subtitle="$do->customer?->customer_name" panel="top" kind="master"
        :status="$pods->isEmpty() ? 'new' : 'active'" :hide="['deactivate', 'new']" :can-input="! $done" :action="route('v2.pod.save', $do->id_delivery)" action-field="action"
        :back="$home" enctype="multipart/form-data"
        :created-by="$last?->created_by" :created-at="$last?->created_at?->toDateTimeString()" :updated-by="$last?->updated_by" :updated-at="$last?->updated_at?->toDateTimeString()">
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Delivery Note')</span><b>{{ $do->do_no }}</b></div>
            <div class="dl-strip__kv"><span>Promised</span><b>{{ Locale::d($promise['date']) ?? '—' }}{{ $promise['time'] ? ' '.substr($promise['time'], 0, 5) : '' }}</b></div>
            <div class="dl-strip__kv"><span>Customer receives</span><b>{{ $promise['date'] ? Promise::receivingText($hours) : '—' }}</b></div>
            <div class="dl-strip__kv dl-strip__grand"><span>Promise</span><b>{{ ProofOfDelivery::state($last) }}</b></div>
        </x-slot:strip>
        @if ($done)
            <x-dl::alert tone="info" message="Delivery finished. The result is locked as the KPI record." />
        @else
            <x-dl::form.row :cols="3">
                <x-dl::field.date-time name="arrived_at" label="Arrived at customer" :value="old('arrived_at')" :source="Src::of('tm2sdi.do_delivery_pod.arrived_at')" />
                <x-dl::field.select name="result" label="Result" required :options="Promise::RESULTS" :value="old('result', 'RECEIVED')" :source="Src::of('tm2sdi.do_delivery_pod.result')" />
                <x-dl::field.text name="received_by" label="Received by (name)" :value="old('received_by')" :source="Src::of('tm2sdi.do_delivery_pod.received_by')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="miss_cause" label="Cause if promise missed" :options="['' => '—'] + array_map(fn ($c) => $c[0].' ('.$c[1].')', Promise::CAUSES)"
                    :value="old('miss_cause')" hint="Required when the truck arrived late, the customer did not receive, or it was not shipped." :source="Src::of('tm2sdi.do_delivery_pod.miss_cause')" />
                <x-dl::field.upload name="photo" label="Photo of signed delivery note" accept=".jpg,.jpeg,.png,.webp,.pdf" :source="Src::of('tm2sdi.do_delivery_pod.photo_path')" />
                <x-dl::field.text name="notes" label="Notes" :value="old('notes')" :source="Src::of('tm2sdi.do_delivery_pod.notes')" />
            </x-dl::form.row>
        @endif
        <x-slot:detail>
            <x-dl::table :columns="['no' => '#', 'arrived' => 'Arrived', 'result' => 'Result', 'by' => 'Received by', 'kept' => 'Promise', 'cause' => 'Cause', 'photo' => 'Photo', 'rec' => 'Recorded by']"
                :rows="$pods->map(fn ($p) => ['no' => $p->attempt_no, 'arrived' => Locale::dt($p->arrived_at) ?? '—', 'result' => Promise::RESULTS[$p->result] ?? $p->result,
                    'by' => $p->received_by ?? '—', 'kept' => $p->promise_kept ? 'Kept' : 'Missed', 'cause' => $p->miss_cause ? Promise::CAUSES[$p->miss_cause][0] : '—',
                    'photo' => $p->photo_path ? 'Yes' : '—', 'rec' => $p->created_by])->all()"
                empty="No arrival recorded yet." source="{{ Src::of('tm2sdi.do_delivery_pod') }}" />
        </x-slot:detail>
    </x-dl::form>
@endif
