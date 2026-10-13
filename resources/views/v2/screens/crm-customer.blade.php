{{--
 REMARK
 Tujuan       : Customer 360: ringkasan hubungan tiap customer (order, quotation, kontak, follow-up, komplain), hanya baca.
 Sumber hulu  : App\Support\V2\Data\Crm; grid CustomerListGrid.
 Input        : Query id (customer).
 Output       : Daftar lewat _dbgrid; halaman satu customer lewat x-mp::sales.customer-card (angka kunci, grafik order, tab Activities / Quotations / Orders / Deliveries / Complaints / Contacts).
 Dipakai oleh : Rute v2.screen dengan slug 'crm-customer' (menu v2:crm-customer di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (read-only; order dan kiriman dari sistem baru + arsip lama).
--}}
@php
    use App\Support\V2\Data\Crm;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'crm-customer');
    $c = request('id') ? Crm::find((int) request('id')) : null;
    abort_if(request('id') && ! $c, 404);
    $dueStats = [
        ['label' => 'All'],
        ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'],
        ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'],
        ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow'],
    ];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif

@if (! $c)
    {{-- Daftar = objek grid App\Support\V2\Grid\Sales\CustomerListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Sales\CustomerListGrid()]])
@else
    {{-- Halaman satu customer = objek form khusus x-mp::sales.customer-card (MockupParts); isi = Crm::card(). --}}
    <x-mp::sales.customer-card :card="Crm::card($c, $home)" />
@endif
