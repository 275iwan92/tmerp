{{--
 REMARK
 Tujuan       : Kas Kecil sistem imprest: PO Kontan, Cash Advance (Kasbon), Settlement nota, Reimburse (Beli dulu), Top Up dari bank; setiap langkah menjurnal otomatis.
 Sumber hulu  : App\Support\V2\Data\PettyCash dan App\Services\Acc\PettyCashService; PettyCash::fund, desk, waiting; model Pur\PoOrder.
 Input        : Dana kas kecil aktif (404 bila tidak ada); session v2_flash dan error validasi.
 Output       : Form khusus x-mp::acc.petty-cash dengan slot daftar: PettyCashWaitingListGrid dan PettyCashTxListGrid lewat view _dbgrid.
 Dipakai oleh : Layar v2 slug petty-cash lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Models\Pur\PoOrder;
    use App\Services\Acc\PettyCashService;
    use App\Support\V2\Data\PettyCash;
    use App\Support\V2\Data\PurchaseOrder;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'petty-cash');
    $fund = PettyCash::fund();
    abort_unless($fund, 404, t('No active petty cash fund.'));
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

{{-- Form = objek form khusus x-mp::acc.petty-cash (MockupParts); isi = PettyCash::desk(). Daftar (mode list) = slot. --}}
<x-mp::acc.petty-cash :desk="PettyCash::desk($fund, $home)">
    @php $waiting = PettyCash::waiting(); @endphp
    @if ($waiting)
        {{-- Daftar = objek grid App\Support\V2\Grid\Acc\PettyCashWaitingListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
        @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Acc\PettyCashWaitingListGrid($waiting)]])
    @endif
    {{-- Daftar = objek grid App\Support\V2\Grid\Acc\PettyCashTxListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Acc\PettyCashTxListGrid($fund)]])
</x-mp::acc.petty-cash>
