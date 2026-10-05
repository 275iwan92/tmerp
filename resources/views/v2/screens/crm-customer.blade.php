{{-- CRM > Customer 360 (user 2026-10-02) lewat App\Support\V2\Data\Crm. Hanya membaca.
     GRID customer aktif + ringkasan hubungan (order terakhir, quotation / order terbuka, kontak terakhir, follow-up, komplain)
     -> klik 2x = halaman satu customer: angka kunci, grafik order 24 bulan, product yang sering dipesan, dan tab
     Activities / Quotations / Orders / Deliveries / Complaints / Contacts. Order & kiriman = sistem baru + arsip sistem lama. --}}
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
