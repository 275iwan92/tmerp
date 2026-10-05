{{-- Material Planning (MRP baru) v2 (docs/materialbooking_tier1.md). Isi = MaterialPlanningEngine lewat App\Support\V2\Data\MaterialPlanning.
     Alur layar: GRID satu baris per bahan (butuh dari CO + forecast MPS, ditutup stok / PO / PR, kurang, pesan paling lambat)
     -> centang bahan yang kurang -> Create PR (satu PR per supplier usulan, qty dibulatkan ke satuan beli)
     -> klik 2x bahan = proyeksi saldo per tanggal. --}}
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
