{{-- Customer Plan Delivery v2 = buku janji (user 2026-10-01). Satu baris jadwal kirim memegang DUA janji yang harus cocok TEPAT:
     slot ke customer (tanggal + jam, dipegang Sales) dan siap produksi (PPIC). Produksi tidak sanggup -> PPIC mengisi siap + alasan ->
     Sales nego ke customer (WhatsApp / telepon) lalu mengubah slot (boleh Split) dengan log -> customer menolak = eskalasi ke
     Operational Manager. Janji awal tidak pernah berubah (patokan KPI). Aturan = App\Services\Sales\CpdelPromiseService.
     Post to Production (aturan A.3) = WO dibuat; sesudah itu tanggal & qty terkunci (hanya jam slot yang bisa diubah). --}}
@php
    use App\Models\Prd\PrdWo;
    use App\Models\Sdi\CoOrderDelivery;
    use App\Models\Sys\SysConstant;
    use App\Services\Sales\CpdelPromiseService as Promise;
    use App\Services\Sales\DeliveryPromiseService;
    use App\Support\V2\Data\Cpdel;
    use App\Support\V2\Data\ProofOfDelivery;
    use App\Support\V2\Src;
    use Carbon\CarbonImmutable;
    use Devlib\Locale;

    $home = route('v2.screen', 'cpdel');
    $d = request('id') ? Cpdel::query()->find((int) request('id')) : null;
    abort_if(request('id') && ! $d, 404);
    $num = fn ($v) => Locale::number((float) $v, 0);
    $user = auth()->user();
    $stateLabels = ['WAITING_PPIC' => 'Waiting for PPIC', 'NEED_NEGOTIATION' => 'Need negotiation', 'ESCALATED' => 'Escalated', 'ALIGNED' => 'Aligned',
        'LATE' => 'Late', 'SHIPPED' => 'Shipped'];
@endphp

<x-mp::sales.flow-steps current="shipping" note="Every delivery line holds two promises that must match exactly: the slot agreed with the customer and the time production commits to be ready." />

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $d)
    @php
        $m = Cpdel::monitor();
        $kpi = ProofOfDelivery::kpi(CarbonImmutable::today());
    @endphp
    <div class="dl-kpis">
        <x-dl::kpi label="Waiting for PPIC" :value="$m['states']['WAITING_PPIC'] ?? 0" icon="fa-industry" tone="accent" hint="Production has not committed a ready time" />
        <x-dl::kpi label="Need negotiation" :value="$m['states']['NEED_NEGOTIATION'] ?? 0" good-when="down" icon="fa-comments" tone="warn" hint="Ready later than the customer slot" />
        <x-dl::kpi label="Escalated" :value="$m['states']['ESCALATED'] ?? 0" good-when="down" icon="fa-user-tie" tone="danger" hint="Customer rejected; Operational Manager decides" />
        <x-dl::kpi label="Late" :value="$m['states']['LATE'] ?? 0" good-when="down" icon="fa-triangle-exclamation" tone="danger" hint="Slot date passed, not shipped" />
        <x-dl::kpi label="Ship today" :value="$m['today']" icon="fa-truck" tone="accent" :hint="$m['week'].' '.t('in the next 7 days')" />
        <x-dl::kpi label="Slot time not set" :value="$m['no_time']" good-when="down" icon="fa-clock" tone="warn" hint="Agree the exact time with the customer" />
        <x-dl::kpi label="Delivery promise kept (this month)" :value="$kpi['pct'] ?? 0" format="percent" :target="100" icon="fa-handshake" tone="success"
            :hint="$kpi['kept'].' / '.$kpi['total'].' · '.t('Proof of Delivery')" />
    </div>
    <br>
    <x-dl::grid id="cpdel-list" :rows="Cpdel::rows()" search="Search CO no., customer, product..." actions="worklist" :active-key="null" :new-record="null"
        :open-url="$home.'?id={id}'" group-by="co"
        source="{{ Src::of('tm2sdi.co_order_delivery', 'tm2sdi.co_order (CONFIRMED)', 'tm2prd.prd_wo.source_delivery_id') }}"
        :columns="[
            ['key' => 'item', 'label' => 'Product', 'type' => 'identity', 'code' => 'co'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'slot', 'label' => 'Customer slot', 'type' => 'date'],
            ['key' => 'time', 'label' => 'Time'],
            ['key' => 'ready', 'label' => 'Production ready'],
            ['key' => 'gap', 'label' => 'Gap (days)', 'type' => 'number'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'original', 'label' => 'Original promise', 'type' => 'date', 'visible' => false],
            ['key' => 'production', 'label' => 'Production'],
            ['key' => 'state', 'label' => 'Promise', 'type' => 'badge', 'tones' => Promise::STATES],
            ['key' => 'co', 'label' => 'CO No.', 'visible' => false],
        ]"
        :stats="[
            ['label' => 'All'],
            ['label' => 'Waiting for PPIC', 'where' => ['state' => 'WAITING_PPIC'], 'tone' => 'neutral'],
            ['label' => 'Need negotiation', 'where' => ['state' => 'NEED_NEGOTIATION'], 'tone' => 'warn'],
            ['label' => 'Escalated', 'where' => ['state' => 'ESCALATED'], 'tone' => 'danger'],
            ['label' => 'Late', 'where' => ['state' => 'LATE'], 'tone' => 'danger'],
            ['label' => 'Aligned', 'where' => ['state' => 'ALIGNED'], 'tone' => 'success'],
        ]"
        :filters="['customer' => 'Customer', 'state' => 'Promise']" />
@else
    @php
        $state = Promise::state($d);
        $planned = $d->status === 'PLANNED';
        $posted = (bool) $d->posted_at;
        $siblings = Cpdel::siblings($d);
        $unit = $d->item?->qty_unit ?? '';
        $slotTime = $d->ship_time ? substr($d->ship_time, 0, 5) : null;
        $recv = $d->ship_date ? DeliveryPromiseService::receivingHours($d->order?->customer_id, $d->ship_date->toDateString()) : null;
        $problems = $d->ship_date && $planned ? Promise::slotProblems($d->order?->customer_id, $d->ship_date->toDateString(), $slotTime) : [];
        $woDue = PrdWo::where('source_delivery_id', $d->id_delivery)->min('due_date');
        $gap = Promise::gapDays($d);
        $next = [
            'WAITING_PPIC' => 'Next: PPIC commits the time the goods will be ready.',
            'NEED_NEGOTIATION' => 'Next: Sales negotiates with the customer (WhatsApp / call), then changes the slot or splits the shipment. If the customer refuses, record it to escalate.',
            'ESCALATED' => 'Next: the Operational Manager decides (overtime, priority, subcontract, or a new offer).',
            'ALIGNED' => 'Both promises match. Keep the slot; Delivery records the arrival in Proof of Delivery.',
            'LATE' => 'The slot date has passed and the goods are not shipped: agree a new slot with the customer now.',
            'SHIPPED' => 'Shipped. The arrival and the customer receipt are recorded in Proof of Delivery.',
        ][$state];
    @endphp
    <x-dl::card>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
            <span class="mp-chips">
                <x-dl::badge :label="$d->order?->co_no" />
                <x-dl::badge :label="$stateLabels[$state]" :tone="Promise::STATES[$state]" />
                @if ($posted)<x-dl::badge label="Posted to Production" tone="info" />@endif
            </span>
            <x-dl::button variant="secondary" icon="send" label="Open Customer Order" :href="route('v2.screen', 'customer-order').'?id='.$d->order_id" />
        </x-dl::action-bar>
        <h2 class="mp-bigname">{{ $d->item?->description }}</h2>
        <x-dl::form.row cols="4">
            <x-dl::field.display label="Original promise" :value="Locale::d($d->original_date ?? $d->ship_date) ?? '—'" :source="Src::of('tm2sdi.co_order_delivery.original_date')" />
            <x-dl::field.display label="Customer slot" :value="(Locale::d($d->ship_date) ?? '—').' '.($slotTime ?? t('time not set'))" :source="Src::of('tm2sdi.co_order_delivery.ship_date', 'ship_time')" />
            <x-dl::field.display label="Production ready" :value="Locale::dt($d->ready_at) ?? t('not committed')" :source="Src::of('tm2sdi.co_order_delivery.ready_at')" />
            <x-dl::field.display label="Gap" :value="$gap === null ? '—' : ($gap > 0 ? '+'.$gap.' '.t('days late') : t('on time'))" />
        </x-dl::form.row>
        <x-dl::form.row cols="4">
            <x-dl::field.display label="Qty" :value="$num($d->ship_qty).' '.$unit.' / '.t('order').' '.$num($d->item?->qty).' '.$unit" :source="Src::of('tm2sdi.co_order_delivery.ship_qty')" />
            <x-dl::field.display label="Customer receives that day" :value="$d->ship_date ? DeliveryPromiseService::receivingText($recv) : '—'" :source="Src::of('tm2mst.cust_receiving_hour')" />
            <x-dl::field.display label="Due date on the order" :value="Locale::d($d->item?->due_date) ?? '—'" :source="Src::of('tm2sdi.co_order_item.due_date')" />
            <x-dl::field.display label="Production" :value="$posted ? Locale::dt($d->posted_at).' · '.$d->posted_by : t('not posted')" :source="Src::of('tm2sdi.co_order_delivery.posted_at')" />
        </x-dl::form.row>
        <x-dl::alert :tone="in_array($state, ['LATE', 'ESCALATED'], true) ? 'danger' : ($state === 'ALIGNED' || $state === 'SHIPPED' ? 'success' : 'warn')" :message="$next" />
        @foreach ($problems as $p)<x-dl::alert tone="warn" :message="$p" />@endforeach
    </x-dl::card>

    @if ($planned && Promise::canPlan($user))
        <form method="post" action="{{ route('v2.cpdel.ready', $d->id_delivery) }}" data-dl-guard>
            @csrf
            <x-dl::card title="Production promise (PPIC)" icon="fa-industry"
                description="When will the goods be ready to load? If it is later than the customer slot, choose the reason: Sales will negotiate with the customer.">
                <x-dl::form.row cols="4">
                    <x-dl::field.date name="ready_date" label="Ready date" required :value="old('ready_date', $d->ready_at?->toDateString() ?? ($woDue ? substr($woDue, 0, 10) : $d->ship_date?->toDateString()))"
                        :hint="$woDue ? t('Work Order due').' '.Locale::d($woDue) : null" :source="Src::of('tm2sdi.co_order_delivery.ready_at')" />
                    <x-dl::field.time name="ready_time" label="Ready time" required :value="old('ready_time', $d->ready_at?->format('H:i') ?? '07:30')" :source="Src::of('tm2sdi.co_order_delivery.ready_at')" />
                    <x-dl::field.select name="reason_code" label="Reason if later than the slot" :options="['' => '—'] + Promise::reasons()" :value="old('reason_code')" :source="Src::of('tm2sdi.co_delay_reason')" />
                    <x-dl::field.text name="note" label="Note" :value="old('note')" :source="Src::of('tm2sdi.co_delivery_negotiation.note')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
            </x-dl::card>
        </form>
    @endif

    @if ($planned && Promise::canSell($user))
        <form method="post" action="{{ route('v2.cpdel.reschedule', $d->id_delivery) }}" data-dl-guard>
            @csrf
            <x-dl::card title="Change the customer slot (Sales)" icon="fa-handshake"
                :description="$posted ? t('Posted to Production: only the slot time can change. Record who agreed and how.') : t('After the customer agrees by WhatsApp or phone: move the whole line, or fill Split qty to send part of it at the new slot. The slot must be inside Telasih office hours and the customer receiving hours.')">
                <x-dl::form.row cols="4">
                    <x-dl::field.date name="ship_date" label="Slot date" required :readonly="$posted" :value="old('ship_date', $d->ship_date?->toDateString())" :source="Src::of('tm2sdi.co_order_delivery.ship_date')" />
                    <x-dl::field.time name="ship_time" label="Slot time" required :value="old('ship_time', $slotTime ?? ($recv?->open_time ? substr($recv->open_time, 0, 5) : '08:00'))" :source="Src::of('tm2sdi.co_order_delivery.ship_time')" />
                    @unless ($posted)
                        <x-dl::field.decimal name="split_qty" label="Split qty (optional)" :decimals="0" :hint="t('Less than').' '.$num($d->ship_qty).' '.t('to split; empty = move the whole line')" :source="Src::of('tm2sdi.co_order_delivery.ship_qty', 'split_from_id')" />
                    @endunless
                    <x-dl::field.select name="reason_code" label="Reason" :options="['' => '—'] + Promise::reasons()" :value="old('reason_code')" hint="Required when an agreed slot changes or the shipment is split" :source="Src::of('tm2sdi.co_delay_reason')" />
                </x-dl::form.row>
                <x-dl::form.row cols="3">
                    <x-dl::field.select name="channel" label="Agreed via" required :options="Promise::CHANNELS" :value="old('channel', 'WHATSAPP')" :source="Src::of('tm2sdi.co_delivery_negotiation.channel')" />
                    <x-dl::field.text name="customer_contact" label="Customer contact (who agreed)" required :value="old('customer_contact', $d->order?->customer?->contact_name)" :source="Src::of('tm2sdi.co_delivery_negotiation.customer_contact')" />
                    <x-dl::field.text name="note" label="Note" :value="old('note')" :source="Src::of('tm2sdi.co_delivery_negotiation.note')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
            </x-dl::card>
        </form>

        @if (in_array($state, ['NEED_NEGOTIATION', 'LATE'], true))
            <form method="post" action="{{ route('v2.cpdel.reject', $d->id_delivery) }}">
                @csrf
                <x-dl::card title="Customer refused" icon="fa-circle-exclamation" description="The customer does not accept a new slot or split. This escalates the line to the Operational Manager.">
                    <x-dl::form.row cols="3">
                        <x-dl::field.select name="channel" label="Via" required :options="Promise::CHANNELS" value="WHATSAPP" />
                        <x-dl::field.text name="customer_contact" label="Customer contact" required :value="$d->order?->customer?->contact_name" />
                        <x-dl::field.text name="note" label="What the customer said" required />
                    </x-dl::form.row>
                    <x-dl::action-bar><x-dl::button type="submit" variant="danger" icon="fa-user-tie" label="Escalate" /></x-dl::action-bar>
                </x-dl::card>
            </form>
        @endif
    @endif

    @if ($state === 'ESCALATED')
        @if (Promise::canDecide($user))
            <form method="post" action="{{ route('v2.cpdel.decide', $d->id_delivery) }}">
                @csrf
                <x-dl::card title="Decision (Operational Manager)" icon="fa-user-tie" description="Production must then commit a new ready time that meets the slot.">
                    <x-dl::form.row cols="2">
                        <x-dl::field.select name="decision" label="Decision" required :options="Promise::DECISIONS" />
                        <x-dl::field.text name="note" label="Instruction" required />
                    </x-dl::form.row>
                    <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
                </x-dl::card>
            </form>
        @else
            <x-dl::alert tone="danger" message="Waiting for the Operational Manager's decision." />
        @endif
    @endif

    @if ($planned && ! $posted)
        <form method="post" action="{{ route('v2.cpdel.post', $d->id_delivery) }}">
            @csrf
            <x-dl::card title="Post to Production" icon="fa-industry" description="Creates the Work Order for this line. After posting, the line can be pulled into a Delivery Order / Delivery Note right away (quick jobs of about {{ Locale::number((float) SysConstant::number('QUICK_JOB_MINUTES', 30), 0) }} minutes). Production output must still be entered afterwards: it is the KPI basis for all departments.">
                <x-dl::action-bar><x-dl::button type="submit" icon="fa-industry" label="Post to Production" /></x-dl::action-bar>
            </x-dl::card>
        </form>
    @elseif ($posted)
        <x-dl::alert tone="info" :message="t('Posted to Production').' '.Locale::dt($d->posted_at).' · '.$d->posted_by.' · Work Order: '.(PrdWo::where('source_delivery_id', $d->id_delivery)->pluck('wo_no')->join(', ') ?: '—').'. '.t('Production output must still be entered.')" />
    @endif

    @if ($planned && ! $posted && Promise::canSell($user) && $siblings->isNotEmpty())
        <form method="post" action="{{ route('v2.cpdel.merge') }}">
            @csrf
            <input type="hidden" name="ids[]" value="{{ $d->id_delivery }}">
            <x-dl::card title="Join lines" description="Join with other lines of the same CO and product; the result goes to the earliest date.">
                <x-dl::field.checkboxes name="ids" label="Join with" :options="$siblings->mapWithKeys(fn ($s) => [$s->id_delivery => (Locale::d($s->ship_date) ?? '—').' · '.$num($s->ship_qty).' '.$unit])->all()" :source="Src::of('tm2sdi.co_order_delivery')" />
                <x-dl::action-bar><x-dl::button preset="apply" label="Join" type="submit" /></x-dl::action-bar>
            </x-dl::card>
        </form>
    @endif

    <x-dl::card title="Negotiation log">
        <x-dl::table :columns="['at' => 'When', 'who' => 'Who', 'event' => 'Event', 'change' => 'Change', 'reason' => 'Reason', 'via' => 'Via / contact', 'note' => 'Note']"
            :rows="$d->negotiations->map(fn ($n) => ['at' => Locale::dt($n->created_at), 'who' => $n->created_by.' ('.$n->actor_role.')', 'event' => t(ucfirst(strtolower(str_replace('_', ' ', $n->event)))),
                'change' => $n->event === 'READY_COMMITTED' ? t('ready').' '.Locale::dt($n->ready_at) : ($n->new_date ? (Locale::d($n->old_date) ?? '—').' '.substr((string) $n->old_time, 0, 5).' → '.Locale::d($n->new_date).' '.substr((string) $n->new_time, 0, 5).($n->new_qty !== null && (float) $n->new_qty !== (float) $n->old_qty ? ' · '.$num($n->new_qty).' '.$unit : '') : ($n->decision ? t(Promise::DECISIONS[$n->decision] ?? $n->decision) : '—')),
                'reason' => $n->reason_code ? (Promise::reasons()[$n->reason_code] ?? $n->reason_code) : '—',
                'via' => $n->channel ? (Promise::CHANNELS[$n->channel] ?? $n->channel).($n->customer_contact ? ' · '.$n->customer_contact : '') : '—', 'note' => $n->note ?? '—'])->all()"
            empty="No changes yet: the line is still at its original promise." source="{{ Src::of('tm2sdi.co_delivery_negotiation') }}" />
    </x-dl::card>

    <x-dl::card title="All delivery lines of this product">
        <x-dl::table :columns="['date' => 'Slot', 'qty' => 'Qty', 'ready' => 'Production ready', 'state' => 'Promise', 'from' => 'Split from']"
            :rows="CoOrderDelivery::where('order_id', $d->order_id)->where('item_id', $d->item_id)->orderBy('ship_date')->get()->map(fn ($s) => ['date' => (Locale::d($s->ship_date) ?? '—').' '.($s->ship_time ? substr($s->ship_time, 0, 5) : ''),
                'qty' => $num($s->ship_qty), 'ready' => Locale::dt($s->ready_at) ?? '—', 'state' => t($stateLabels[Promise::state($s)]).($s->id_delivery === $d->id_delivery ? ' · '.t('this line') : ''), 'from' => $s->split_from_id ? '#'.$s->split_from_id : '—'])->all()"
            source="{{ Src::of('tm2sdi.co_order_delivery') }}" />
    </x-dl::card>
@endif
