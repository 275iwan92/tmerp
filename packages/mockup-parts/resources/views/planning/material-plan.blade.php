{{-- Tampilan objek x-mp::planning.material-plan (MockupParts\Components\Planning\MaterialPlan). --}}
{{-- Label tombol di sini diterjemahkan (Lang::text), sama seperti layar asalnya. --}}
@php
    use Devlib\Lang;
    $p = $plan;
@endphp
<div {{ $attributes->class('mp-flat') }} {!! $dl() !!}>
    @if ($detail())
        <x-dl::card>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$p['home']" />
                <x-dl::chips as="span">
                    <x-dl::badge :label="$p['code']" />
                    <x-dl::badge :label="$p['status']" :tone="$p['statusTone']" />
                </x-dl::chips>
                @if ($p['buy'])
                    <x-dl::action-form action="{{ $p['buy']['action'] }}">
                        <input type="hidden" name="material_id" value="{{ $p['buy']['id'] }}">
                        <x-dl::button type="submit" icon="fa-cart-shopping" :label="Lang::text('Buy (PR + PO)')" />
                    </x-dl::action-form>
                @endif
            </x-dl::action-bar>
            <x-dl::headline>{{ $p['material'] }}</x-dl::headline>
            <x-dl::form.row cols="4">
                @foreach ($p['facts'] as [$label, $value])
                    <x-dl::field.display :label="$label" :value="$value" />
                @endforeach
            </x-dl::form.row>
        </x-dl::card>
        {{ $slot }}
    @else
        <x-dl::action-bar>
            <x-dl::action-form action="{{ $p['runAction'] }}"><x-dl::button type="submit" variant="secondary" icon="fa-rotate" :label="Lang::text('Recalculate All')" /></x-dl::action-form>
            @foreach ($p['links'] as [$icon, $label, $url])
                <x-dl::button variant="secondary" :icon="$icon" :label="Lang::text($label)" :href="$url" />
            @endforeach
        </x-dl::action-bar>
        @if ($p['warning'])
            <x-dl::alert tone="warn" :message="Lang::raw($p['warning'])" />
        @endif
        {{ $slot }}
        <x-dl::action-form action="{{ $p['pr']['action'] }}">
            <x-dl::card title="Create Purchase Requisition" icon="fa-file-circle-plus" description="One requisition per suggested supplier. Quantity is rounded up to the purchase unit; the need date is the first date the material is needed. The requisition is created as Draft in Procurement.">
                @if (! $p['pr']['options'])
                    <x-dl::empty-state title="No short material with a stock item" />
                @else
                    <x-dl::field.checkboxes name="material_ids" label="Short materials" :value="$p['pr']['picked']" :options="$p['pr']['options']"
                        :source="$src('tm2pur.pr_requisition (i_source = MRP)', 'tm2pur.pr_line.i_need_date', 'i_co_line_id')" />
                    <x-dl::action-bar><x-dl::button type="submit" icon="fa-file-circle-plus" :label="Lang::text('Create PR')" /></x-dl::action-bar>
                @endif
            </x-dl::card>
        </x-dl::action-form>
    @endif
</div>
