{{--
 REMARK
 Tujuan       : Booking bahan per Customer Order: daftar CO aktif beserta forecast, lalu rincian bahan, sumber penutup, Recalculate, Pick Item, Confirm (kunci stok) dan Back to Draft.
 Sumber hulu  : App\Support\V2\Data\MaterialPlanning (bookingDesk, lineRows) di atas App\Services\Planning\MaterialPlanningEngine; tabel rm_booking_d, rm_booking_alloc, v_rm_available, po_line, pr_line, mat_usage_standard.
 Input        : Query id (id_order CO); session v2_flash dan error validasi.
 Output       : Grid CO (view _dbgrid) tanpa id, atau form x-mp::planning.material-booking plus x-dl::grid baris bahan; booking hanya menulis transaksi rm_booking, Master Product tidak diubah.
 Dipakai oleh : Layar v2 slug material-booking lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Models\Sdi\CoOrder;
    use App\Support\V2\Data\MaterialPlanning;
    use App\Support\V2\Src;

    $grid = $screen['grid'];
    $home = $grid->url();
    $co = request('id') ? CoOrder::find((int) request('id')) : null;
    abort_if(request('id') && ! $co, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::planning.material-booking (MockupParts); isi = MaterialPlanning::bookingDesk(). Grid = slot. --}}
<x-mp::planning.material-booking :booking="MaterialPlanning::bookingDesk($home, $co)">
@if (! $co)
    @include('v2.screens._dbgrid', ['screen' => $screen])
@else
    <x-dl::grid id="material-booking-lines" :rows="MaterialPlanning::lineRows((int) $co->id_order)" mode="report" actions="none" :active-key="null" :new-record="null" search="Search product, material..."
        source="{{ Src::of('tm2inv.rm_booking_d', 'tm2inv.rm_booking_alloc', 'tm2mst.prod_layout_group', 'tm2mst.mat_usage_standard', 'tm2inv.v_rm_available', 'tm2pur.po_line', 'tm2pur.pr_line') }}"
        :columns="[
            ['key' => 'material', 'label' => 'Material', 'type' => 'identity', 'code' => 'role', 'avatar' => false],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'part', 'label' => 'Part'],
            ['key' => 'need', 'label' => 'Needed By', 'type' => 'date'],
            ['key' => 'qty', 'label' => 'Qty Needed', 'type' => 'number', 'decimals' => 2],
            ['key' => 'uom', 'label' => 'UoM'],
            ['key' => 'covered', 'label' => 'Covered By'],
            ['key' => 'short', 'label' => 'Short', 'type' => 'number', 'decimals' => 2],
            ['key' => 'status', 'label' => 'Coverage', 'type' => 'badge', 'tones' => MaterialPlanning::COVER_TONES],
            ['key' => 'calc', 'label' => 'Calculation'],
            ['key' => 'role', 'label' => 'Kind', 'visible' => false],
        ]"
        :stats="[
            ['label' => 'All'],
            ['label' => 'Not covered', 'where' => ['status' => ['Short', 'Need item', 'Need layout']], 'tone' => 'danger'],
            ['label' => 'Late', 'where' => ['status' => 'Late'], 'tone' => 'warn'],
            ['label' => 'Waiting PO/PR', 'where' => ['status' => 'Waiting PO/PR'], 'tone' => 'info'],
            ['label' => 'Covered', 'where' => ['status' => ['Covered by stock', 'Booked']], 'tone' => 'success'],
        ]"
        :filters="['product' => 'Product', 'role' => 'Kind', 'status' => 'Coverage']" />
@endif
</x-mp::planning.material-booking>
