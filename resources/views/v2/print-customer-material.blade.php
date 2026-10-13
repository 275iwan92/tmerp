{{--
 REMARK
 Tujuan       : Cetak Tanda Terima Bahan Customer (penerimaan jenis CUSTOMER dari SPB), tanpa harga.
 Sumber hulu  : tm2pur.pv_receipt dan pv_line, tm2sdi.co_order / co_order_item, tm2mst.cust_customer dan mat_raw, wh_warehouse.
 Input        : pv, customer, coNo, warehouse, company, rows dari route.
 Output       : x-dl::print-out dibungkus x-dl::doc-share (PDF / JPEG / online + QR / Email), Back ke purchase-receive.
 Dipakai oleh : route v2.pv.print-customer (routes/v2.php, grup pv).
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => t('Customer Material Receipt').' '.$pv->doc_no, 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id" />
            <x-dl::doc-share target="#cm-print" type="customer-material" :doc-id="$pv->pv_id" :number="$pv->doc_no" title="Customer Material Receipt" :email-to="$customer->email ?? null" />
        </x-dl::action-bar>
        <x-dl::box id="cm-print">
            <x-dl::print-out title="Customer Material Receipt" subtitle="Receipt of Customer-Owned Material" :number="$pv->doc_no" :date="$pv->i_doc_date?->toDateString()"
                :status="$pv->status" :company="$company" :printed-by="auth()->user()->login" :prices="false" :source="\App\Support\V2\Src::of('tm2pur.pv_receipt (CUSTOMER)', 'tm2pur.pv_line')"
                :parties="array_filter([
                    'Customer' => [$customer->customer_name ?? '—', $customer->default_address ?? ''],
                    'Customer Order' => $coNo ?: '—',
                    'Received Date' => \Devlib\Locale::d($pv->i_doc_date),
                    'Customer Delivery Note No.' => $pv->i_supplier_do_no ?: '—',
                    'Vehicle No.' => $pv->i_vehicle_no ?: '—',
                    'Stored At' => $warehouse ?: '—',
                ])"
                :columns="[
                    ['key' => 'co_item', 'label' => 'For CO Item'],
                    ['key' => 'name', 'label' => 'Material (as delivered)', 'sub' => 'code'],
                    ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
                    ['key' => 'uom', 'label' => 'UoM'],
                    ['key' => 'lot', 'label' => 'Lot / Roll No.'],
                ]"
                :rows="$rows"
                :signatures="[['Handed Over By', 'Customer / Courier'], ['Received By', 'Warehouse'], ['Acknowledged By', 'PPIC / Sales']]">
                <x-slot:notes><b>{{ t('Notes') }}:</b> {{ $pv->i_notes ?: t('Customer-owned material for the Customer Order above; not company stock.') }}</x-slot:notes>
            </x-dl::print-out>
        </x-dl::box>
    </x-dl::box>
@endsection
