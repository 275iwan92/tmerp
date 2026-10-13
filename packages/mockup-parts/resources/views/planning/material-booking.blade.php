{{-- Tampilan objek x-mp::planning.material-booking (MockupParts\Components\Planning\MaterialBooking). Pesan di $booking sudah
     diterjemahkan pemilik data -> Lang::raw; label ditulis English. --}}
{{-- Label tombol di sini diterjemahkan (Lang::text), sama seperti layar asalnya. --}}
@php
    use Devlib\Lang;
    $b = $booking;
@endphp
<div {{ $attributes->class('mp-flat') }} {!! $dl() !!}>
    @if (! $detail())
        <x-dl::alert tone="info" :message="Lang::raw($b['info'])" />
        <x-dl::action-bar>
            <x-dl::action-form action="{{ $b['runAction'] }}"><x-dl::button type="submit" variant="secondary" icon="fa-rotate" :label="Lang::text('Recalculate All')" /></x-dl::action-form>
            @foreach ($b['links'] as [$icon, $label, $url])
                <x-dl::button variant="secondary" :icon="$icon" :label="Lang::text($label)" :href="$url" />
            @endforeach
        </x-dl::action-bar>
        {{ $slot }}
    @else
        <x-dl::card>
            <x-dl::action-form action="{{ $b['action'] }}">
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$b['home']" />
                    <x-dl::chips as="span">
                        <x-dl::badge :label="$b['coNo']" />
                        <x-dl::badge :label="$b['coStatus']" :tone="$b['coTone']" />
                        <x-dl::badge :label="$b['booking']" :tone="$b['bookingTone']" />
                    </x-dl::chips>
                    <x-dl::button variant="secondary" icon="send" :label="Lang::text('Open Customer Order')" :href="$b['orderUrl']" />
                    <x-dl::button preset="print" :href="$b['printUrl']" target="_blank" />
                    @if ($b['canRun'])
                        <x-dl::button type="submit" name="action" value="run" variant="secondary" icon="fa-rotate" :label="Lang::text('Recalculate')" />
                        @if ($b['canConfirm'])<x-dl::button type="submit" name="action" value="confirm" variant="success" icon="confirm" label="Confirm" />@endif
                    @elseif ($b['canUnlock'])
                        <x-dl::button type="submit" name="action" value="unlock" variant="secondary" icon="fa-lock-open" label="Back to Draft" />
                    @endif
                </x-dl::action-bar>
            </x-dl::action-form>
            <x-dl::form.row cols="4">
                @foreach ($b['facts'] as [$label, $value, $sources])
                    <x-dl::field.display :label="$label" :value="$value" :source="$src(...$sources)" />
                @endforeach
            </x-dl::form.row>
            @if ($a = $b['alert'])
                <x-dl::alert :tone="$a[0]" :message="Lang::raw($a[1])" />
            @endif
        </x-dl::card>

        {{ $slot }}

        @if ($k = $b['pick'])
            <x-dl::action-form action="{{ $k['action'] }}">
                <x-dl::card title="Pick Item" icon="fa-hand-pointer" description="Master Product only has the specification for these lines. Choose the stock item to book; the choice is kept on this booking (Master Product is not changed).">
                    <x-dl::form.row cols="2">
                        <x-dl::field.select name="line_id" label="Line" required :options="$k['lines']" :source="$src('tm2inv.rm_booking_d (line_status = NEED_ITEM)')" />
                        <x-dl::field.lookup name="material_id" lookup="material" label="Stock Item" required placeholder="Type at least 2 letters of the item name / code..." :source="$src('tm2mst.mat_raw')" />
                    </x-dl::form.row>
                    <x-dl::action-bar><x-dl::button type="submit" icon="confirm" :label="Lang::text('Pick Item')" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        @elseif ($b['needRun'])
            <x-dl::alert tone="info" message="Click Recalculate to save the booking proposal for this order; after that items can be picked and the booking confirmed." />
        @endif
    @endif
</div>
