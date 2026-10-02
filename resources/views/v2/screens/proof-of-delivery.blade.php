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

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $do)
    @php
        $kpi = ProofOfDelivery::kpi(CarbonImmutable::today());
        $prev = ProofOfDelivery::kpi(CarbonImmutable::today()->subMonthNoOverflow());
    @endphp
    <div class="dl-kpis">
        <x-dl::kpi label="Delivery promise kept (this month)" :value="$kpi['pct'] ?? 0" format="percent" :previous="$prev['pct']" :target="100" icon="fa-handshake" tone="success"
            :hint="raw($kpi['kept'].' / '.$kpi['total']).' shipments'" />
        @foreach ($kpi['by_dept'] as $dept => $n)
            <x-dl::kpi :label="'Missed: '.raw(t($dept))" :value="$n" good-when="down" icon="fa-triangle-exclamation" tone="danger" />
        @endforeach
    </div>
    <br>
    <x-dl::grid id="pod-list" :rows="ProofOfDelivery::rows()" search="Search delivery note no., customer..." actions="worklist" :active-key="null"
        :new-record="false" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2sdi.do_delivery (CONFIRMED / SHIPPED)', 'tm2sdi.do_delivery_pod') }}"
        :columns="[
            ['key' => 'no', 'label' => 'Delivery Note No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'promise', 'label' => 'Promised date', 'type' => 'date'],
            ['key' => 'address', 'label' => 'Destination'],
            ['key' => 'arrived', 'label' => 'Arrived'],
            ['key' => 'result', 'label' => 'Result'],
            ['key' => 'state', 'label' => 'Promise', 'type' => 'badge', 'tones' => ProofOfDelivery::STATE_TONES],
        ]"
        :detail="['key' => 'items', 'title' => t('Product'), 'columns' => [
            ['key' => 'product', 'label' => t('Product'), 'strong' => true],
            ['key' => 'co', 'label' => t('CO No.')],
            ['key' => 'qty', 'label' => t('Qty'), 'type' => 'number'],
            ['key' => 'unit', 'label' => t('Unit')],
        ]]"
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
    <x-dl::form id="pod-form" :title="'Proof of Delivery · '.raw($do->do_no)" :subtitle="raw($do->customer?->customer_name)" panel="top" kind="master"
        :status="$pods->isEmpty() ? 'new' : 'active'" :hide="['deactivate', 'new']" :can-input="! $done" :action="route('v2.pod.save', $do->id_delivery)" action-field="action"
        :back="$home" enctype="multipart/form-data"
        :created-by="$last?->created_by" :created-at="$last?->created_at?->toDateTimeString()" :updated-by="$last?->updated_by" :updated-at="$last?->updated_at?->toDateTimeString()">
        <x-slot:special>
            <x-dl::button size="sm" preset="print" :href="\App\Support\V2\Print\PrintDocs::url('proof-of-delivery', $do->id_delivery)" target="_blank" />
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Delivery Note')</span><b>{{ $do->do_no }}</b></div>
            <div class="dl-strip__kv"><span>@t('Promised')</span><b>{{ Locale::d($promise['date']) ?? '—' }}{{ $promise['time'] ? ' '.substr($promise['time'], 0, 5) : '' }}</b></div>
            <div class="dl-strip__kv"><span>@t('Customer receives')</span><b>{{ $promise['date'] ? Promise::receivingText($hours) : '—' }}</b></div>
            <div class="dl-strip__kv dl-strip__grand"><span>@t('Promise')</span><b>{{ t(ProofOfDelivery::state($last)) }}</b></div>
        </x-slot:strip>
        @if ($done)
            <x-dl::alert tone="info" message="Delivery finished. The result is locked as the KPI record." />
            {{-- Yang divalidasi customer per baris = dasar tagih Customer Invoice (yang tidak diterima tidak ditagih). --}}
            <x-dl::table :columns="['product' => 'Product', 'shipped' => ['Shipped', 'num' => true, 'raw' => true], 'received' => ['Received', 'num' => true, 'raw' => true],
                    'diff' => ['Difference', 'num' => true, 'raw' => true], 'reason' => 'Reason for Difference', 'note' => 'Condition / Note', 'invoiced' => ['Already Invoiced', 'num' => true, 'raw' => true]]"
                :rows="$do->lines->map(fn ($l) => ['product' => $l->item_description, 'shipped' => Locale::number($l->qty_do, 2), 'received' => $l->qty_received === null ? '—' : Locale::number($l->qty_received, 2),
                    'diff' => $l->qty_received === null || (float) $l->qty_do - (float) $l->qty_received < 0.005 ? '' : Locale::number((float) $l->qty_do - (float) $l->qty_received, 2),
                    'reason' => $l->receive_diff_reason ? t(Promise::DIFF_REASONS[$l->receive_diff_reason] ?? $l->receive_diff_reason) : '', 'note' => $l->receive_note ?? '',
                    'invoiced' => Locale::number($l->qty_invoiced, 2)])->all()"
                empty="Not received yet." source="{{ Src::of('tm2sdi.do_line.qty_received', 'receive_diff_reason', 'receive_note', 'qty_invoiced') }}" />
        @else
            <x-dl::form.row :cols="3">
                <x-dl::field.date-time name="arrived_at" label="Arrived at customer" :value="old('arrived_at')" :source="Src::of('tm2sdi.do_delivery_pod.arrived_at')" />
                <x-dl::field.select name="result" label="Result" required :options="array_map(fn ($l) => t($l), Promise::RESULTS)" :value="old('result', 'RECEIVED')" :source="Src::of('tm2sdi.do_delivery_pod.result')" />
                <x-dl::field.text name="received_by" label="Received by (name)" :value="old('received_by')" :source="Src::of('tm2sdi.do_delivery_pod.received_by')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="miss_cause" label="Cause if promise missed" :options="['' => '—'] + array_map(fn ($c) => t($c[0]).' ('.t($c[1]).')', Promise::CAUSES)"
                    :value="old('miss_cause')" hint="Required when the truck arrived late, the customer did not receive, or it was not shipped." :source="Src::of('tm2sdi.do_delivery_pod.miss_cause')" />
                <x-dl::field.upload name="photo" label="Photo of signed delivery note" accept=".jpg,.jpeg,.png,.webp,.pdf" :source="Src::of('tm2sdi.do_delivery_pod.photo_path')" />
                <x-dl::field.text name="notes" label="Notes" :value="old('notes')" :source="Src::of('tm2sdi.do_delivery_pod.notes')" />
            </x-dl::form.row>
            {{-- Qty diterima per baris = dasar tagih Customer Invoice. Received: semua baris = qty kirim; Partially received: isi per baris. --}}
            <x-dl::lines name="received" title="Received Quantity" key-field="id_line" :addable="false" :duplicable="false" :min-rows="$do->lines->count()"
                :rows="$do->lines->map(fn ($l) => ['id_line' => $l->id_line, 'product' => $l->item_description, 'shipped' => Locale::number($l->qty_do, 2), 'unit' => $l->qty_unit,
                    'qty_received' => (float) ($l->qty_received ?? $l->qty_do), 'reason' => $l->receive_diff_reason ?? '', 'note' => $l->receive_note ?? ''])->all()"
                source="{{ Src::of('tm2sdi.do_line.qty_received', 'receive_diff_reason', 'receive_note') }}"
                :columns="[['key' => 'product', 'label' => 'Product', 'type' => 'display'], ['key' => 'shipped', 'label' => 'Shipped', 'type' => 'display', 'width' => '110px'],
                    ['key' => 'unit', 'label' => 'Unit', 'type' => 'display', 'width' => '70px'], ['key' => 'qty_received', 'label' => 'Received', 'type' => 'number', 'width' => '120px'],
                    ['key' => 'reason', 'label' => 'Reason for Difference', 'type' => 'select', 'options' => ['' => '—'] + array_map(fn ($r) => t($r), Promise::DIFF_REASONS), 'width' => '200px'],
                    ['key' => 'note', 'label' => 'Condition / Note', 'type' => 'text', 'width' => '220px']]" />
            <x-dl::alert tone="info" :message="t('Result Received: every line counts as fully received. Partially received: type what the customer really received per line and why the rest was not received. Only the received quantity can be invoiced.')" />
        @endif
        <x-slot:detail>
            <x-dl::table :columns="['no' => '#', 'arrived' => 'Arrived', 'result' => 'Result', 'by' => 'Received by', 'kept' => 'Promise', 'cause' => 'Cause', 'photo' => 'Photo', 'rec' => 'Recorded by']"
                :rows="$pods->map(fn ($p) => ['no' => $p->attempt_no, 'arrived' => Locale::dt($p->arrived_at) ?? '—', 'result' => t(Promise::RESULTS[$p->result] ?? $p->result),
                    'by' => $p->received_by ?? '—', 'kept' => $p->promise_kept ? t('Kept') : t('Missed'), 'cause' => $p->miss_cause ? t(Promise::CAUSES[$p->miss_cause][0]) : '—',
                    'photo' => $p->photo_path ? t('Yes') : '—', 'rec' => $p->created_by])->all()"
                empty="No arrival recorded yet." source="{{ Src::of('tm2sdi.do_delivery_pod') }}" />
        </x-slot:detail>
    </x-dl::form>
@endif
