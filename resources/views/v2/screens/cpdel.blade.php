{{--
 REMARK
 Tujuan       : Buku janji pengiriman: tiap jadwal kirim memegang janji slot customer (Sales) dan siap produksi (PPIC), sampai Post to Production.
 Sumber hulu  : App\Support\V2\Data\Cpdel; App\Services\Sales\CpdelPromiseService; grid CpdelListGrid.
 Input        : Query id (jadwal kirim); session v2_flash dan errors.
 Output       : Daftar lewat _dbgrid (baris berwarna menurut jatuh tempo); detail lewat x-mp::sales.promise-book (slot, siap, alasan, Split, eskalasi, Post to Production).
 Dipakai oleh : Rute v2.screen dengan slug 'cpdel' (menu v2:cpdel di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (janji awal tidak berubah sebagai patokan KPI; setelah Post tanggal dan qty terkunci).
--}}
@php
    use App\Services\Sales\CpdelPromiseService as Promise;
    use App\Support\V2\Data\Cpdel;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'cpdel');
    $d = request('id') ? Cpdel::query()->find((int) request('id')) : null;
    abort_if(request('id') && ! $d, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $d)
    {{-- Tanpa kotak KPI (user 2026-10-02, aturan C.22 + C.32): baris berwarna menurut jatuh tempo slot (hari kerja) + kolom Due;
         filter kiri = waktu, kanan = janji. Persen janji kirim ditepati tetap di layar Proof of Delivery. --}}
    {{-- Daftar = objek grid App\Support\V2\Grid\Sales\CpdelListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Sales\CpdelListGrid()]])
@else
    {{-- Form = objek form khusus x-mp::sales.promise-book (MockupParts); isi & hak = Cpdel::promise(). --}}
    <x-mp::sales.promise-book :book="Cpdel::promise($d, auth()->user(), $home)" />
@endif
