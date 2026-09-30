{{-- Kas Kecil v2 (user 2026-10-01) lewat App\Support\V2\Data\PettyCash + App\Services\Acc\PettyCashService.
     Sistem imprest: PO Kontan (approval Direktur F&A) -> Ambil Kasbon (mode Kasbon) -> belanja -> Pertanggungjawaban nota
     (harga PO diganti nilai nota, kembalian / kurang bayar otomatis) -> SPB mencatat barang masuk. Mode Beli dulu:
     langsung Reimburse dari nota. Isi Ulang dari bank mengembalikan saldo ke plafon. Semua langkah menjurnal otomatis. --}}
@php
    use App\Models\Pur\PoOrder;
    use App\Services\Acc\PettyCashService;
    use App\Support\V2\Data\PettyCash;
    use App\Support\V2\Data\PurchaseOrder;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'petty-cash');
    $fund = PettyCash::fund();
    abort_unless($fund, 404, 'Belum ada Kas Kecil aktif.');
    $sum = PettyCash::summary($fund);
    $rp = fn ($v) => $v === null ? '—' : 'Rp '.\Devlib\Locale::number((float) $v, 0);
    $po = request('po') ? PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->find((int) request('po')) : null;
    $step = $po ? PettyCashService::nextStep($po) : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

<x-dl::card :title="$fund->petty_name.' · '.$fund->petty_code" :description="'Pemegang: '.PettyCash::custodianName($fund).' · akun 111400 Kas Kecil · batas PO Kontan '.$rp(PurchaseOrder::maxCash()).' (Constants PO_CASH_MAX)'">
    <div class="dl-grid__stats">
        <x-dl::stat-box label="Plafon" :value="$rp($sum['imprest'])" :sub="$sum['imprest'] === null ? 'belum diisi — buka Setting' : null" />
        <x-dl::stat-box label="Saldo tunai" :value="$rp($sum['balance'])" tone="success" />
        <x-dl::stat-box label="Kasbon belum dipertanggungjawabkan" :value="$rp($sum['open'])" tone="warn" />
        <x-dl::stat-box label="Siap diisi ulang" :value="$rp($sum['refill'])" tone="info" />
    </div>
    @unless (request()->hasAny(['po', 'topup', 'setup']))
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-gear" label="Setting" :href="$home.'?setup=1'" />
            <x-dl::button variant="success" icon="fa-circle-plus" label="Isi Ulang" :href="$home.'?topup=1'" />
        </x-dl::action-bar>
    @endunless
</x-dl::card>

@if ($po)
    @php
        $shop = $po->i_shop_name ?: (PurchaseOrder::suppliers([$po->i_supplier_id])[$po->i_supplier_id] ?? '—');
        $advance = PettyCashService::advanceOf($po);
    @endphp
    <x-dl::card :title="'PO Kontan '.$po->doc_no.' · '.$shop" :description="'Mode: '.(PurchaseOrder::CASH_MODES[$po->i_cash_mode] ?? '—').' · status PO '.$po->status.' · total '.$rp($po->c_grand_total)">
        <x-dl::table :source="Src::of('tm2pur.po_line')" :columns="['item' => 'Item', 'qty' => ['Qty', 'num' => true], 'uom' => 'Satuan', 'est' => ['Estimasi', 'num' => true, 'money' => true]]"
            :rows="$po->lines->map(fn ($l) => ['item' => $l->i_description, 'qty' => (float) $l->i_qty, 'uom' => $l->i_uom, 'est' => (float) $l->c_line_net])->all()" />
    </x-dl::card>
    @if ($step === 'ADVANCE')
        <form method="post" action="{{ route('v2.petty.advance', $po->po_id) }}">
            @csrf
            <x-dl::card title="Ambil Kasbon" description="Uang keluar dari kas kecil ke pembeli. Jurnal: Dr Uang Muka Pembelian (114300) / Cr Kas Kecil.">
                <x-dl::form.row :cols="2">
                    <x-dl::field.money name="amount" label="Nilai kasbon" required :value="old('amount', (float) $po->c_grand_total)" :source="Src::of('tm2acc.fin_petty_cash_tx.advance_amount')" />
                    <x-dl::field.text name="receiver_name" label="Diserahkan kepada" required :value="old('receiver_name')" placeholder="nama pembeli" :source="Src::of('tm2acc.fin_petty_cash_tx.receiver_name')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'purchase-order').'?id='.$po->po_id" />
                    <x-dl::button preset="save" label="Ambil Kasbon" onclick="return confirm('Serahkan kasbon dari kas kecil?')" />
                </x-dl::action-bar>
            </x-dl::card>
        </form>
    @elseif (in_array($step, ['SETTLE', 'REIMBURSE'], true))
        <form method="post" action="{{ route('v2.petty.settle', $po->po_id) }}" enctype="multipart/form-data">
            @csrf
            <x-dl::card :title="$step === 'SETTLE' ? 'Pertanggungjawaban Kasbon' : 'Reimburse (beli dulu)'"
                :description="$step === 'SETTLE' ? 'Kasbon '.$advance->tx_no.' '.$rp($advance->advance_amount).' kepada '.$advance->receiver_name.'. Isi nilai nota per baris; kembalian / kurang bayar dihitung otomatis.' : 'Isi nilai nota per baris; uang pengganti keluar dari kas kecil.'">
                <x-dl::form.row :cols="2">
                    @foreach ($po->lines as $l)
                        <x-dl::field.money :name="'nota['.$l->po_line_id.']'" :label="'Nota: '.$l->i_description.' ('.\Devlib\Locale::decimal($l->i_qty, 2).' '.$l->i_uom.')'" required :value="old('nota.'.$l->po_line_id, (float) $l->c_line_net)" :source="Src::of('tm2pur.po_line.i_price (dari nota)')" />
                    @endforeach
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    @if ($step === 'REIMBURSE')<x-dl::field.text name="receiver_name" label="Diganti kepada" required :value="old('receiver_name')" placeholder="nama pembeli" :source="Src::of('tm2acc.fin_petty_cash_tx.receiver_name')" />@endif
                    <x-dl::field.upload name="nota_file" label="Foto / scan nota" accept=".jpg,.jpeg,.png,.pdf" :source="Src::of('tm2acc.fin_petty_cash_tx.attachment_path')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'purchase-order').'?id='.$po->po_id" />
                    <x-dl::button preset="save" :label="$step === 'SETTLE' ? 'Simpan Pertanggungjawaban' : 'Bayar Reimburse'" onclick="return confirm('Posting nota ini ke kas kecil?')" />
                </x-dl::action-bar>
            </x-dl::card>
        </form>
    @else
        <x-dl::alert tone="info" message="PO ini tidak sedang menunggu kasbon / pertanggungjawaban (belum Approved, bukan PO Kontan, atau sudah selesai)." />
    @endif
@elseif (request('topup'))
    <form method="post" action="{{ route('v2.petty.topup') }}">
        @csrf
        <x-dl::card title="Isi Ulang Kas Kecil" description="Uang dari bank ke kas kecil. Jurnal: Dr Kas Kecil / Cr Bank. Nilai usulan = plafon − saldo − kasbon terbuka.">
            <x-dl::form.row :cols="3">
                <x-dl::field.money name="amount" label="Nilai isi ulang" required :value="old('amount', $sum['refill'])" :source="Src::of('tm2acc.fin_petty_cash_tx.cash_effect')" />
                <x-dl::field.select name="bank_account_id" label="Dari akun" required :options="PettyCash::bankAccounts($fund)" :source="Src::of('tm2acc.fin_account (CASH)')" />
                <x-dl::field.date name="tx_date" label="Tanggal" required :value="old('tx_date', now()->toDateString())" :source="Src::of('tm2acc.fin_petty_cash_tx.tx_date')" />
            </x-dl::form.row>
            <x-dl::field.text name="notes" label="Catatan" :value="old('notes')" :source="Src::of('tm2acc.fin_petty_cash_tx.notes')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Isi Ulang" />
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@elseif (request('setup'))
    <form method="post" action="{{ route('v2.petty.setup') }}">
        @csrf
        <x-dl::card title="Setting Kas Kecil" description="Nama, pemegang kas, dan plafon (jumlah tetap yang dijaga lewat Isi Ulang).">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="petty_name" label="Nama kas kecil" required :value="old('petty_name', $fund->petty_name)" :source="Src::of('tm2acc.fin_petty_cash.petty_name')" />
                <x-dl::field.select name="custodian_user_id" label="Pemegang kas" required :options="PettyCash::users()" :value="$fund->custodian_user_id" :source="Src::of('tm2acc.fin_petty_cash.custodian_user_id')" />
                <x-dl::field.money name="imprest_amount" label="Plafon" :value="$fund->imprest_amount === null ? null : (float) $fund->imprest_amount" :source="Src::of('tm2acc.fin_petty_cash.imprest_amount')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@else
    @php $waiting = PettyCash::waiting(); @endphp
    @if ($waiting)
        <x-dl::grid id="petty-waiting" :rows="$waiting" search="Cari PO Kontan..." actions="worklist" :active-key="null" :open-url="$home.'?po={id}'" source="{{ Src::of('tm2pur.po_order (CASH, APPROVED)') }}"
            :columns="[
                ['key' => 'shop', 'label' => 'PO Kontan menunggu', 'type' => 'identity', 'code' => 'no'],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
                ['key' => 'step', 'label' => 'Langkah berikut', 'type' => 'badge', 'tones' => ['Ambil Kasbon' => 'warn', 'Pertanggungjawaban' => 'info', 'Reimburse' => 'info']],
                ['key' => 'by', 'label' => 'Dibuat oleh'],
            ]" />
    @endif
    <x-dl::grid id="petty-tx" :rows="PettyCash::rows($fund)" search="Cari no. transaksi, PO, penerima..." actions="none" :active-key="null" source="{{ Src::of('tm2acc.fin_petty_cash_tx', 'tm2acc.fin_voucher') }}"
        :columns="[
            ['key' => 'type', 'label' => 'Transaksi', 'type' => 'identity', 'code' => 'no'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'po', 'label' => 'PO Kontan'],
            ['key' => 'who', 'label' => 'Penerima'],
            ['key' => 'in', 'label' => 'Masuk', 'type' => 'money', 'sum' => true],
            ['key' => 'out', 'label' => 'Keluar', 'type' => 'money', 'sum' => true],
            ['key' => 'voucher', 'label' => 'Jurnal'],
            ['key' => 'note', 'label' => 'Catatan'],
        ]"
        :stats="[['label' => 'Semua'], ['label' => 'Kasbon', 'where' => ['type' => 'Kasbon'], 'tone' => 'warn'], ['label' => 'Nota / Reimburse', 'where' => ['type' => ['Pertanggungjawaban', 'Reimburse']], 'tone' => 'info'], ['label' => 'Isi Ulang', 'where' => ['type' => 'Isi Ulang'], 'tone' => 'success']]"
        :filters="['type' => 'Transaksi']" />
@endif
