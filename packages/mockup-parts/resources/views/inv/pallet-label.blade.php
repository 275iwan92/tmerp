{{-- Tampilan objek x-mp::inv.pallet-label (MockupParts\Components\Inv\PalletLabel). Pesan / judul di $desk sudah jadi -> Lang::raw. --}}
@php
    use Devlib\Lang;
    $d = $desk;
@endphp
<div {{ $attributes->class('mp-flat') }} {!! $dl() !!}>
@if ($d['empty'])
    <x-dl::empty-state title="No warehouse yet" message="Add a building and its bins first." icon="fa-warehouse" />
@else
    @php
        $pos = $d['pos'];
        $sizes = $d['sizes'];
        $papers = $d['papers'];
    @endphp
    @if ($n = $d['notice'])
        <x-dl::alert tone="info" :message="Lang::raw($n['message'])" />
        <x-dl::action-bar align="start">
            @foreach ($n['buttons'] as [$icon, $label, $url])
                <x-dl::button variant="secondary" :icon="$icon" :label="$label" :href="$url" />
            @endforeach
        </x-dl::action-bar>
    @else
        <x-dl::action-form method="get" action="{{ $d['filter']['action'] }}">
            <x-dl::action-bar align="start">
                <x-dl::field.select name="wh" label="Building" :options="$d['filter']['options']" :value="$d['filter']['wh']" :source="$src('tm2mst.wh_warehouse')" />
                <x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" />
                <x-dl::button variant="secondary" icon="fa-clock-rotate-left" label="Print History" :href="$d['filter']['historyUrl']" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @endif

    <x-dl::label-guide size="lg" :colors="$d['legend']" />

    @if ($pos)
        <x-dl::card :title="Lang::raw($pos['title'])" icon="fa-pallet" :description="Lang::raw($pos['description'])">
            <x-slot:actions>
                <x-dl::button size="sm" variant="secondary" icon="back" label="Back" :href="$pos['backUrl']" />
            </x-slot:actions>

            @if ($pos['relabelWarn'])
                <x-dl::alert tone="warn" :message="Lang::raw($pos['relabelWarn'])" />
            @endif

            @if ($c = $pos['create'])
                <x-dl::action-form action="{{ $c['action'] }}">
                    @foreach ($c['hidden'] as $hk => $hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
                    <x-dl::alert tone="info" :message="Lang::raw($c['info'])" />
                    <x-dl::form.row :cols="4">
                        <x-dl::field.date name="arrived_at" label="Arrival date" required :value="$c['arrived']" :source="$src('tm2inv.inv_pallet.arrived_at')" />
                        <x-dl::field.text name="ref_doc_no" label="Receipt No." :case-toggle="false" :value="$c['receiptNo']" :readonly="$c['receiptLocked']" :source="$src('tm2inv.inv_pallet.ref_doc_no')" />
                        <x-dl::field.select name="size" label="Label size" required :options="$sizes" value="lg" />
                        <x-dl::field.select name="paper" label="Print On" required :options="$papers" value="a4" />
                    </x-dl::form.row>
                    <x-dl::lines name="lines" title="New Pallets" add-label="+ Pallet" :duplicable="true" :min-rows="1" :rows="$c['rows']"
                        source="{{ $src('tm2inv.inv_pallet') }}" :columns="$newPalletColumns($c['codeHint'])" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="unit_kind" label="Kind of the new units" :options="$c['kinds']" value="" :source="$src('tm2inv.inv_pallet.unit_kind')"
                            hint="Stack 1 is the bottom. A new unit goes on top; when a unit leaves, the ones above move down by themselves." />
                        <x-dl::field.text name="notes" label="Notes" :source="$src('tm2inv.inv_pallet.notes')" />
                    </x-dl::form.row>
                    <x-dl::action-bar>
                        <x-dl::button icon="print" label="Create & Print" type="submit" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @elseif ($pos['done'])
                <x-dl::alert tone="success" message="Every unit of this stock is on a pallet label. Relabel a pallet after its contents are used." />
            @endif
        </x-dl::card>

        @if ($p = $pos['pallets'])
            @php
                $r = $p['relabel'];
                $q = $p['reprint'];
            @endphp
            <x-dl::card title="Pallets of this Stock" icon="fa-layer-group" description="Oldest arrival first: use the oldest pallet first. After using from a pallet, relabel it with what is left; 0 closes the pallet.">
                <x-dl::grid id="pallets-of-position" mode="report" row-tone="tone" :per-page="50" search="Search pallet no., lot, supplier code..." source="{{ $src('tm2inv.inv_pallet') }}"
                    :rows="$p['rows']" :columns="$stockPalletColumns()" />
            </x-dl::card>
            <x-dl::form.row :cols="2">
                <x-dl::action-form action="{{ $r['action'] }}">
                    <x-dl::card title="Relabel after Use" icon="fa-rotate" description="Type what is left on the pallet that was opened. At or below the remnant limit it becomes a remnant pack. If the remnant was put on another pile, choose that bin: a Stock Transfer is made and the pack goes on top.">
                        <x-dl::form.row :cols="2">
                            <x-dl::field.select name="pallet_id" label="Pallet" required :options="$p['options']" :value="$r['picked']" :source="$src('tm2inv.inv_pallet')" />
                            <x-dl::field.decimal name="remaining" label="Remaining quantity" required :value="$r['remaining']" :source="$src('tm2inv.inv_pallet.qty')" />
                        </x-dl::form.row>
                        <x-dl::form.row :cols="2">
                            <x-dl::field.select name="unit_kind" label="Unit kind" :options="$r['kinds']" value="" :source="$src('tm2inv.inv_pallet.unit_kind')" :hint="Lang::raw($r['limitHint'])" />
                            <x-dl::field.select name="to_location_id" label="Now in bin" :options="$r['bins']" value="" :source="$src('tm2mst.wh_loc')" />
                        </x-dl::form.row>
                        <x-dl::form.row :cols="3">
                            <x-dl::field.text name="note" label="Note" placeholder="e.g. taken for a work order" :source="$src('tm2inv.inv_pallet_log.note')" />
                            <x-dl::field.select name="size" label="Label size" :options="$sizes" value="lg" />
                            <x-dl::field.select name="paper" label="Print On" :options="$papers" value="a4" />
                        </x-dl::form.row>
                        <x-dl::action-bar><x-dl::button icon="print" label="Relabel & Print" type="submit" /></x-dl::action-bar>
                    </x-dl::card>
                </x-dl::action-form>
                <x-dl::action-form action="{{ $q['action'] }}">
                    @foreach ($q['hidden'] as $hk => $hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
                    <x-dl::card title="Print Again" icon="fa-print" description="For a label that is damaged or lost. The quantity on the label does not change.">
                        <x-dl::field.multi-select name="keys" label="Pallets" required :options="$p['options']" :value="$q['picked']" empty-text="Choose (0 selected)" noun="selected" :source="$src('tm2inv.inv_pallet')" />
                        <x-dl::form.row :cols="2">
                            <x-dl::field.select name="size" label="Label size" required :options="$sizes" value="lg" />
                            <x-dl::field.select name="paper" label="Print On" required :options="$papers" value="a4" />
                        </x-dl::form.row>
                        <x-dl::action-bar><x-dl::button variant="secondary" icon="print" label="Print" type="submit" /></x-dl::action-bar>
                    </x-dl::card>
                </x-dl::action-form>
            </x-dl::form.row>
        @endif
    @endif

    <x-dl::card :title="$receipt() ? 'Receipt Lines' : 'Pallet Label Work'" icon="fa-list-check"
        :description="$receipt() ? 'One row per item and bin of this receipt. Open a row to make its pallet labels.' : 'Stock whose pallet labels do not match what is in the bin. Arrival label: goods came in without a pallet label. Relabel after use: stock was taken and the pallet label still shows the old quantity.'">
        @if (! $d['todo']['rows'])
            <x-dl::alert tone="success" :message="$receipt() ? 'This receipt put no stock into a bin.' : 'All stock in this building is on an up-to-date pallet label.'" />
        @else
            <x-dl::grid :id="$d['todo']['id']" :rows="$d['todo']['rows']" actions="worklist" :active-key="null" :new-record="null" open-url="{open_url}" row-tone="tone"
                search="Search item, bin..." source="{{ $src('tm2inv.inv_pallet', 'tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand') }}"
                :columns="$todoColumns()" :stats="$d['todo']['stats']" />
        @endif
    </x-dl::card>

    @if ($a = $d['all'])
        <x-dl::card title="Pallets in this Building" icon="fa-pallet" description="Every pallet with a label that still holds stock. Open a pallet to relabel it or print its label again.">
            @if (! $a['rows'])
                <x-dl::alert tone="info" message="No pallet has a label yet in this building." />
            @else
                <x-dl::grid :id="$a['id']" :rows="$a['rows']" actions="worklist" :active-key="null" :new-record="null" open-url="{open_url}" row-tone="tone"
                    search="Search pallet no., item, bin, lot, supplier code..." source="{{ $src('tm2inv.inv_pallet', 'tm2mst.wh_loc') }}"
                    :columns="$buildingPalletColumns()" :stats="$a['stats']" :filters="['bin' => 'Bin', 'kind' => 'Kind', 'expiry_state' => 'Expiry']" />
            @endif
        </x-dl::card>
    @endif
@endif
</div>
