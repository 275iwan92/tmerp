{{-- Tanda Terima Bahan Customer (user 2026-10-01): cetak penerimaan jenis CUSTOMER dari SPB v2 (pv_receipt).
     Objek DevLib x-dl::print-out (kop + pihak + tabel + tanda tangan) + x-dl::doc-share (PDF / JPEG / online + QR / Email).
     Dokumen gudang: tanpa harga. Label English (diterjemahkan lewat sys_dictionary / bahasa cetak). --}}
@extends('v2.layout', ['title' => t('Customer Material Receipt').' '.$pv->doc_no, 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id" />
            <x-dl::doc-share target="#cm-print" type="customer-material" :doc-id="$pv->pv_id" :number="$pv->doc_no" title="Customer Material Receipt" :email-to="$customer->email ?? null" />
        </x-dl::action-bar>
        <div id="cm-print">
            <x-dl::print-out title="Customer Material Receipt" subtitle="Tanda Terima Bahan Customer" :number="$pv->doc_no" :date="$pv->i_doc_date?->toDateString()"
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
        </div>
    </div>
@endsection
