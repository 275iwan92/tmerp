{{-- Inventory > Valuation & Period (v2.01, V-05, V-06). Status periode inventory (inv_period) dengan Close / Open, nilai stok per kelas dengan hasil rekonsiliasi terakhir, penutupan selisih landed cost
     ke HPP, dan jalan pintas ke laporan valuasi. Aturan periode: App\Services\Inventory\Valuation\InvPeriodService. --}}
@php
    use App\Support\V2\Data\ValuationDesk;
    use App\Support\V2\Src;

    $links = [['Landed Cost', 'fa-truck-ramp-box', 'landed-cost'], ['Obsolete Stock', 'fa-hourglass-end', 'obsolete-stock'], ['GR/IR Aging', 'fa-file-circle-question', 'inv-grir-aging'],
        ['Daily Reconciliation', 'fa-scale-unbalanced', 'inv-reconcile'], ['Repost', 'fa-clock-rotate-left', 'inv-repost'], ['Stock Aging', 'fa-hourglass-half', 'inv-stock-aging']];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Value by Class" description="Stock value now from the stock cards, with the latest Daily Reconciliation (ledger minus stock card)." icon="fa-solid fa-scale-balanced">
    <x-dl::table :columns="['class' => ['Class', 'raw' => true], 'value' => ['Stock Value', 'num' => true, 'raw' => true], 'recon' => ['Last Reconciliation', 'num' => true, 'raw' => true], 'status' => ['Status', 'raw' => true]]"
        :rows="ValuationDesk::classRows()" :source="Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2inv.inv_reconcile_log')" />
    <x-dl::action-bar align="start">
        @foreach ($links as [$label, $icon, $slug])
            <x-dl::button variant="secondary" :icon="'fa-'.$icon" :label="$label" :href="route('v2.screen', $slug)" />
        @endforeach
    </x-dl::action-bar>
</x-dl::card>

<x-dl::card title="Inventory Period" description="A closed period refuses stock documents dated inside or before it. Close the periods in order; open only the latest closed one, and only while the general ledger period of that month is still open." icon="fa-solid fa-calendar-check">
    <x-dl::table :columns="['period' => ['Period', 'raw' => true], 'range' => ['Dates', 'raw' => true], 'status' => ['Status', 'raw' => true], 'closed' => ['Closed By', 'raw' => true]]"
        :rows="ValuationDesk::periodRows()" empty="No period yet." :source="Src::of('tm2inv.inv_period')" />
    <x-dl::action-form action="{{ route('v2.val.period', 'close') }}">
        <x-dl::form.row :cols="2">
            <x-dl::field.select name="period" label="Period" required :options="ValuationDesk::periodOptions()" :source="Src::of('tm2inv.inv_period.i_period_code')" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-plus" label="Add Next Period" type="submit" formaction="{{ route('v2.val.period', 'next') }}" formnovalidate />
            <x-dl::button variant="secondary" icon="fa-lock-open" label="Open" type="submit" formaction="{{ route('v2.val.period', 'open') }}" :onclick="'return confirm('.json_encode(t('Open this period again?')).')'" />
            <x-dl::button icon="fa-lock" label="Close" type="submit" :onclick="'return confirm('.json_encode(t('Close this period? Stock documents dated inside it are refused.')).')'" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::action-form action="{{ route('v2.val.landed.close-variance') }}">
    <x-dl::card title="Close Landed Cost Variance" description="At the end of a month, the landed cost that fell on stock already issued is moved from the variance account to cost of goods sold." icon="fa-solid fa-right-left">
        <x-dl::field.text name="period" label="Period (YYYYMM)" required :value="now()->format('Ym')" :case-toggle="false" :source="Src::of('tm2inv.inv_landed_cost.variance_voucher_id')" />
        <x-dl::action-bar><x-dl::button icon="fa-circle-check" label="Close" type="submit" :onclick="'return confirm('.json_encode(t('Move the landed cost variance of this period to cost of goods sold?')).')'" /></x-dl::action-bar>
    </x-dl::card>
</x-dl::action-form>
