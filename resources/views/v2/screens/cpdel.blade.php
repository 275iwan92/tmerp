{{-- Customer Plan Delivery v2 = buku janji (user 2026-10-01). Satu baris jadwal kirim memegang DUA janji yang harus cocok TEPAT:
     slot ke customer (tanggal + jam, dipegang Sales) dan siap produksi (PPIC). Produksi tidak sanggup -> PPIC mengisi siap + alasan ->
     Sales nego ke customer (WhatsApp / telepon) lalu mengubah slot (boleh Split) dengan log -> customer menolak = eskalasi ke
     Operational Manager. Janji awal tidak pernah berubah (patokan KPI). Aturan = App\Services\Sales\CpdelPromiseService.
     Post to Production (aturan A.3) = WO dibuat; sesudah itu tanggal & qty terkunci (hanya jam slot yang bisa diubah). --}}
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
