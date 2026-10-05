{{-- Kas Kecil v2 (user 2026-10-01) lewat App\Support\V2\Data\PettyCash + App\Services\Acc\PettyCashService.
     Sistem imprest: PO Kontan (approval Direktur F&A) -> Cash Advance (mode Kasbon) -> belanja -> Settlement nota
     (harga PO diganti nilai nota, kembalian / kurang bayar otomatis) -> SPB mencatat barang masuk. Mode Beli dulu:
     langsung Reimburse dari nota. Top Up dari bank mengembalikan saldo ke plafon. Semua langkah menjurnal otomatis.
     Label English (diterjemahkan lewat sys_dictionary, aturan multi-bahasa 2026-10-01). --}}
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
