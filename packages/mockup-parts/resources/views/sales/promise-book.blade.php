{{-- Tampilan objek x-mp::sales.promise-book (MockupParts\Components\Sales\PromiseBook). Nilai di $book sudah jadi (diterjemahkan
     dan diformat pemilik data), jadi diteruskan lewat Lang::raw; label ditulis English dan diterjemahkan objek DevLib. --}}
@php
    use Devlib\Lang;
    $b = $book;
@endphp
<div {{ $attributes->class('mp-promise') }} {!! $dl() !!}>
    <x-dl::card>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$b['home']" />
            <x-dl::chips as="span">
                <x-dl::badge :label="$b['coNo']" />
                <x-dl::badge :label="$b['stateLabel']" :tone="$b['stateTone']" />
                @if ($b['posted'])<x-dl::badge label="Posted to Production" tone="info" />@endif
            </x-dl::chips>
            <x-dl::button variant="secondary" icon="send" label="Open Customer Order" :href="$b['orderUrl']" />
            <x-dl::button preset="print" :href="$b['printUrl']" target="_blank" />
        </x-dl::action-bar>
        <x-dl::headline>{{ $b['product'] }}</x-dl::headline>
        @foreach ($factRows() as $row)
            <x-dl::form.row cols="4">
                @foreach ($row as [$label, $value, $sources])
                    <x-dl::field.display :label="$label" :value="$value" :source="$src(...$sources)" />
                @endforeach
            </x-dl::form.row>
        @endforeach
        <x-dl::alert :tone="$nextTone()" :message="$b['next']" />
        @foreach ($b['problems'] as $p)<x-dl::alert tone="warn" :message="Lang::raw($p)" />@endforeach
    </x-dl::card>

    @if ($f = $b['ppic'])
        <x-dl::action-form action="{{ $f['action'] }}" data-dl-guard>
            <x-dl::card title="Production promise (PPIC)" icon="fa-industry"
                description="When will the goods be ready to load? If it is later than the customer slot, choose the reason: Sales will negotiate with the customer.">
                <x-dl::form.row cols="4">
                    <x-dl::field.date name="ready_date" label="Ready date" required :value="$f['date']" :hint="$f['woDue'] ? 'Work Order due '.Lang::raw($f['woDue']) : null" :source="$src('tm2sdi.co_order_delivery.ready_at')" />
                    <x-dl::field.time name="ready_time" label="Ready time" required :value="$f['time']" :source="$src('tm2sdi.co_order_delivery.ready_at')" />
                    <x-dl::field.select name="reason_code" label="Reason if later than the slot" :options="$f['reasons']" :value="$f['reason']" :source="$src('tm2sdi.co_delay_reason')" />
                    <x-dl::field.text name="note" label="Note" :value="$f['note']" :source="$src('tm2sdi.co_delivery_negotiation.note')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @endif

    @if ($f = $b['slot'])
        <x-dl::action-form action="{{ $f['action'] }}" data-dl-guard>
            <x-dl::card title="Change the customer slot (Sales)" icon="fa-handshake" :description="Lang::raw($slotDescription())">
                <x-dl::form.row cols="4">
                    <x-dl::field.date name="ship_date" label="Slot date" required :readonly="$b['posted']" :value="$f['date']" :source="$src('tm2sdi.co_order_delivery.ship_date')" />
                    <x-dl::field.time name="ship_time" label="Slot time" required :value="$f['time']" :source="$src('tm2sdi.co_order_delivery.ship_time')" />
                    @unless ($b['posted'])
                        <x-dl::field.decimal name="split_qty" label="Split qty (optional)" :decimals="0" :hint="'Less than '.Lang::raw($f['maxQty']).' to split; empty = move the whole line'" :source="$src('tm2sdi.co_order_delivery.ship_qty', 'split_from_id')" />
                    @endunless
                    <x-dl::field.select name="reason_code" label="Reason" :options="$f['reasons']" :value="$f['reason']" hint="Required when an agreed slot changes or the shipment is split" :source="$src('tm2sdi.co_delay_reason')" />
                </x-dl::form.row>
                <x-dl::form.row cols="3">
                    <x-dl::field.select name="channel" label="Agreed via" required :options="$f['channels']" :value="$f['channel']" :source="$src('tm2sdi.co_delivery_negotiation.channel')" />
                    <x-dl::field.text name="customer_contact" label="Customer contact (who agreed)" required :value="$f['contact']" :source="$src('tm2sdi.co_delivery_negotiation.customer_contact')" />
                    <x-dl::field.text name="note" label="Note" :value="$f['note']" :source="$src('tm2sdi.co_delivery_negotiation.note')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @endif

    @if ($f = $b['refuse'])
        <x-dl::action-form action="{{ $f['action'] }}">
            <x-dl::card title="Customer refused" icon="fa-circle-exclamation" description="The customer does not accept a new slot or split. This escalates the line to the Operational Manager.">
                <x-dl::form.row cols="3">
                    <x-dl::field.select name="channel" label="Via" required :options="$f['channels']" value="WHATSAPP" />
                    <x-dl::field.text name="customer_contact" label="Customer contact" required :value="$f['contact']" />
                    <x-dl::field.text name="note" label="What the customer said" required />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button type="submit" variant="danger" icon="fa-user-tie" label="Escalate" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @endif

    @if ($f = $b['decide'])
        <x-dl::action-form action="{{ $f['action'] }}">
            <x-dl::card title="Decision (Operational Manager)" icon="fa-user-tie" description="Production must then commit a new ready time that meets the slot.">
                <x-dl::form.row cols="2">
                    <x-dl::field.select name="decision" label="Decision" required :options="$f['decisions']" />
                    <x-dl::field.text name="note" label="Instruction" required />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @elseif ($escalated())
        <x-dl::alert tone="danger" message="Waiting for the Operational Manager's decision." />
    @endif

    @if ($f = $b['post'])
        <x-dl::action-form action="{{ $f['action'] }}">
            <x-dl::card title="Post to Production" icon="fa-industry" :description="Lang::raw($f['description'])">
                <x-dl::action-bar><x-dl::button type="submit" icon="fa-industry" label="Post to Production" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @elseif ($i = $b['postedInfo'])
        <x-dl::alert tone="info" :message="'Posted to Production '.Lang::raw($i[0]).' · Work Order: '.Lang::raw($i[1]).'. Production output must still be entered.'" />
    @endif

    @if ($f = $b['merge'])
        <x-dl::action-form action="{{ $f['action'] }}">
            <input type="hidden" name="ids[]" value="{{ $f['id'] }}">
            <x-dl::card title="Join lines" description="Join with other lines of the same CO and product; the result goes to the earliest date.">
                <x-dl::field.checkboxes name="ids" label="Join with" :options="$f['options']" :source="$src('tm2sdi.co_order_delivery')" />
                <x-dl::action-bar><x-dl::button preset="apply" label="Join" type="submit" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @endif

    <x-dl::card title="Negotiation log">
        <x-dl::table :columns="['at' => 'When', 'who' => 'Who', 'event' => 'Event', 'change' => 'Change', 'reason' => 'Reason', 'via' => 'Via / contact', 'note' => 'Note']"
            :rows="$b['log']" empty="No changes yet: the line is still at its original promise." source="{{ $src('tm2sdi.co_delivery_negotiation') }}" />
    </x-dl::card>

    <x-dl::card title="All delivery lines of this product">
        <x-dl::table :columns="['date' => 'Slot', 'qty' => 'Qty', 'ready' => 'Production ready', 'state' => 'Promise', 'from' => 'Split from']"
            :rows="$b['lines']" source="{{ $src('tm2sdi.co_order_delivery') }}" />
    </x-dl::card>
</div>
