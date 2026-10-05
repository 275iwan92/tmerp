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
        <x-dl::grid id="petty-waiting" :rows="$waiting" search="Search cash PO..." actions="worklist" :active-key="null" :open-url="$home.'?po={id}'" source="{{ Src::of('tm2pur.po_order (CASH, APPROVED)') }}"
            :columns="[
                ['key' => 'no', 'label' => 'Cash PO No. / Shop', 'type' => 'doc', 'party' => 'shop'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
                ['key' => 'step', 'label' => 'Next Step', 'type' => 'badge', 'tones' => PettyCash::STEP_TONES],
                ['key' => 'by', 'label' => 'Created By'],
            ]"
        :detail="['key' => 'items', 'title' => t('Item'), 'columns' => [
            ['key' => 'code', 'label' => t('Code')],
            ['key' => 'name', 'label' => t('Item Name'), 'strong' => true],
            ['key' => 'qty', 'label' => t('Qty'), 'type' => 'number'],
            ['key' => 'unit', 'label' => t('Unit')],
        ]]" />
    @endif
    <x-dl::grid id="petty-tx" :rows="PettyCash::rows($fund)" search="Search transaction, PO, receiver..." actions="worklist" :active-key="null" :open-url="$home.'?tx={id}'" source="{{ Src::of('tm2acc.fin_petty_cash_tx', 'tm2acc.fin_voucher') }}"
        :columns="[
            ['key' => 'no', 'label' => 'Transaction No. / Receiver', 'type' => 'doc', 'party' => 'who'],
            ['key' => 'type', 'label' => 'Transaction'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'po', 'label' => 'Cash PO'],
            ['key' => 'in', 'label' => 'In', 'type' => 'money', 'sum' => true],
            ['key' => 'out', 'label' => 'Out', 'type' => 'money', 'sum' => true],
            ['key' => 'voucher', 'label' => 'Journal'],
            ['key' => 'note', 'label' => 'Notes', 'visible' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Posted' => 'success', 'Cancelled' => 'danger']],
        ]"
        :detail="['key' => 'items', 'title' => t('Item (Cash PO)'), 'columns' => [
            ['key' => 'code', 'label' => t('Code')],
            ['key' => 'name', 'label' => t('Item Name'), 'strong' => true],
            ['key' => 'qty', 'label' => t('Qty'), 'type' => 'number'],
            ['key' => 'unit', 'label' => t('Unit')],
        ]]"
        :stats="[['label' => 'All'], ['label' => 'Cash Advance', 'where' => ['type' => t('Cash Advance')], 'tone' => 'warn'], ['label' => 'Settlement / Reimburse', 'where' => ['type' => [t('Settlement'), t('Reimburse')]], 'tone' => 'info'], ['label' => 'Top Up', 'where' => ['type' => t('Top Up')], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'Cancelled'], 'tone' => 'danger']]"
        :filters="['type' => 'Transaction']" />
</x-mp::acc.petty-cash>
