{{--
 REMARK
 Tujuan       : Material Planning (MRP baru): per bahan menghitung kebutuhan (CO + forecast MPS) terhadap stok, PO, PR, lalu membuat PR per supplier usulan.
 Sumber hulu  : App\Support\V2\Data\MaterialPlanning (materialRows, planDesk, timeline) di atas MaterialPlanningEngine; v_rm_available, po_line, pr_line, co_order_item, prd_demand_forecast.
 Input        : Query id (id bahan); session v2_flash dan error validasi.
 Output       : Grid bahan (view _dbgrid) atau form x-mp::planning.material-plan plus x-dl::grid proyeksi saldo per tanggal; aksi Create PR.
 Dipakai oleh : Layar v2 slug material-planning lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\MaterialPlanning;
    use App\Support\V2\Src;

    $grid = $screen['grid'];
    $home = $grid->url();
    $materialId = (int) request('id');
    $rows = MaterialPlanning::materialRows();
    $m = $materialId ? collect($rows)->firstWhere('id', $materialId) : null;
    abort_if($materialId && ! $m, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::planning.material-plan (MockupParts); isi = MaterialPlanning::planDesk(). Grid = slot. --}}
<x-mp::planning.material-plan :plan="MaterialPlanning::planDesk($home, $rows, $m)">
@if (! $m)
    @include('v2.screens._dbgrid', ['screen' => $screen])
@else
    <x-dl::grid id="material-timeline" :rows="MaterialPlanning::timeline($materialId)" mode="report" actions="none" :active-key="null" :new-record="null" search="Search..."
        source="{{ Src::of('tm2inv.v_rm_available', 'tm2pur.po_line', 'tm2pur.pr_line', 'tm2sdi.co_order_item', 'tm2prd.prd_demand_forecast') }}"
        :columns="[
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'what', 'label' => 'Event'],
            ['key' => 'in', 'label' => 'In', 'type' => 'number', 'decimals' => 2],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number', 'decimals' => 2],
            ['key' => 'balance', 'label' => 'Projected Balance', 'type' => 'number', 'decimals' => 2],
        ]" />
@endif
</x-mp::planning.material-plan>
