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
    abort_unless($fund, 404, 'No active petty cash fund.');
    $sum = PettyCash::summary($fund);
    $rp = fn ($v) => $v === null ? '—' : 'Rp '.\Devlib\Locale::number((float) $v, 0);
    $po = request('po') ? PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->find((int) request('po')) : null;
    $step = $po ? PettyCashService::nextStep($po) : null;
    $tx = request('tx') ? \App\Models\Acc\FinPettyCashTx::where('petty_cash_id', $fund->id_petty_cash)->find((int) request('tx')) : null;
    $back = $po ? route('v2.screen', 'purchase-order').'?id='.$po->po_id : $home;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

<x-dl::card :title="raw($fund->petty_name.' · '.$fund->petty_code)" :description="'Custodian: '.raw(PettyCash::custodianName($fund)).' · account 111400 Petty Cash · cash PO limit '.raw($rp(PurchaseOrder::maxCash()).' (Constants PO_CASH_MAX)')">
    <div class="dl-grid__stats">
        <x-dl::stat-box label="Imprest Limit" :value="$rp($sum['imprest'])" :sub="$sum['imprest'] === null ? 'not set — open Setting' : null" />
        <x-dl::stat-box label="Cash on Hand" :value="$rp($sum['balance'])" tone="success" />
        <x-dl::stat-box label="Open Cash Advances" :value="$rp($sum['open'])" tone="warn" />
        <x-dl::stat-box label="To Top Up" :value="$rp($sum['refill'])" tone="info" />
    </div>
    @unless (request()->hasAny(['po', 'topup', 'setup', 'tx']))
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-gear" label="Setting" :href="$home.'?setup=1'" />
            <x-dl::button variant="success" icon="fa-circle-plus" label="Top Up" :href="$home.'?topup=1'" />
        </x-dl::action-bar>
    @endunless
</x-dl::card>

@if ($tx)
    <x-dl::card :title="(\App\Models\Acc\FinPettyCashTx::TYPES[$tx->tx_type] ?? $tx->tx_type).' · '.raw($tx->tx_no)" :description="$tx->status === 'POSTED' ? 'Status: Posted' : 'Status: Cancelled'">
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Date" :value="\Devlib\Locale::d($tx->tx_date)" :source="Src::of('tm2acc.fin_petty_cash_tx.tx_date')" />
            <x-dl::field.display label="Cash PO" :value="$tx->po_no ?: '—'" :source="Src::of('tm2acc.fin_petty_cash_tx.po_no')" />
            <x-dl::field.display label="Receiver" :value="$tx->receiver_name ?: '—'" :source="Src::of('tm2acc.fin_petty_cash_tx.receiver_name')" />
            <x-dl::field.display label="Cash Effect" :value="($tx->cash_effect >= 0 ? '+ ' : '− ').$rp(abs((float) $tx->cash_effect))" :source="Src::of('tm2acc.fin_petty_cash_tx.cash_effect')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Advance" :value="$rp($tx->advance_amount)" :source="Src::of('tm2acc.fin_petty_cash_tx.advance_amount')" />
            <x-dl::field.display label="Receipt Amount" :value="$rp($tx->actual_amount)" :source="Src::of('tm2acc.fin_petty_cash_tx.actual_amount')" />
            <x-dl::field.display label="Journal" :value="PettyCash::voucherNo($tx->voucher_id).($tx->reversal_voucher_id ? ' · reversed by '.PettyCash::voucherNo($tx->reversal_voucher_id) : '')" :source="Src::of('tm2acc.fin_voucher')" />
            <x-dl::field.display label="Notes" :value="$tx->notes ?: '—'" :source="Src::of('tm2acc.fin_petty_cash_tx.notes')" />
        </x-dl::form.row>
        @if ($tx->attachment_path)
            <x-dl::action-bar><x-dl::button size="sm" variant="secondary" icon="fa-receipt" label="Receipt Photo" :href="\Illuminate\Support\Facades\Storage::disk('public')->url($tx->attachment_path)" target="_blank" /></x-dl::action-bar>
        @endif
        <x-dl::action-bar><x-dl::button size="sm" preset="print" :href="\App\Support\V2\Print\PrintDocs::url('petty-cash', $tx->id_petty_tx)" target="_blank" /></x-dl::action-bar>
        @if ($tx->status !== 'POSTED')
            <x-dl::alert tone="warn" :message="'Cancelled by '.raw($tx->cancelled_by.' · '.\Devlib\Locale::dt($tx->cancelled_at).' — '.$tx->cancel_reason)" />
        @endif
    </x-dl::card>
    @if ($tx->status === 'POSTED')
        <x-dl::action-form action="{{ route('v2.petty.cancel', $tx->id_petty_tx) }}">
            <x-dl::card title="Cancel Transaction" description="For wrong input only. The journal is reversed and the transaction is kept as Cancelled. A settled advance, or a receipt whose goods are already in an SPB, must be cancelled from the later step first.">
                <x-dl::field.text name="cancel_reason" label="Cancel Reason" required :value="old('cancel_reason')" placeholder="min. 10 characters" :source="Src::of('tm2acc.fin_petty_cash_tx.cancel_reason')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button variant="danger" icon="fa-ban" label="Cancel Transaction" type="submit" onclick="return confirm('Cancel this petty cash transaction and reverse its journal?')" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @else
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    @endif
@elseif ($po)
    @php
        $shop = $po->i_shop_name ?: (PurchaseOrder::suppliers([$po->i_supplier_id])[$po->i_supplier_id] ?? '—');
        $advance = PettyCashService::advanceOf($po);
    @endphp
    <x-dl::card :title="'Cash PO '.raw($po->doc_no.' · '.$shop)" :description="'Mode: '.raw(PurchaseOrder::CASH_MODES[$po->i_cash_mode] ?? '—').' · PO status '.raw($po->status).' · total '.raw($rp($po->c_grand_total))">
        <x-dl::table :source="Src::of('tm2pur.po_line')" :columns="['item' => 'Item', 'qty' => ['Qty', 'num' => true], 'uom' => 'UoM', 'est' => ['Estimate', 'num' => true, 'money' => true]]"
            :rows="$po->lines->map(fn ($l) => ['item' => $l->i_description, 'qty' => (float) $l->i_qty, 'uom' => $l->i_uom, 'est' => (float) $l->c_line_net])->all()" />
    </x-dl::card>
    @if ($step === 'ADVANCE')
        <x-dl::action-form action="{{ route('v2.petty.advance', $po->po_id) }}">
            <x-dl::card title="Cash Advance" description="Cash leaves the petty cash to the buyer. Journal: Dr Purchase Advance (114300) / Cr Petty Cash.">
                <x-dl::form.row :cols="2">
                    <x-dl::field.money name="amount" label="Advance Amount" required :value="old('amount', (float) $po->c_grand_total)" :source="Src::of('tm2acc.fin_petty_cash_tx.advance_amount')" />
                    <x-dl::field.text name="receiver_name" label="Given To" required :value="old('receiver_name')" placeholder="buyer name" :source="Src::of('tm2acc.fin_petty_cash_tx.receiver_name')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
                    <x-dl::button preset="save" label="Give Advance" onclick="return confirm('Give this cash advance from petty cash?')" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @elseif (in_array($step, ['SETTLE', 'REIMBURSE'], true))
        <x-dl::action-form action="{{ route('v2.petty.settle', $po->po_id) }}" enctype="multipart/form-data">
            <x-dl::card :title="$step === 'SETTLE' ? 'Advance Settlement' : 'Reimburse'"
                :description="$step === 'SETTLE' ? 'Advance '.raw($advance->tx_no.' '.$rp($advance->advance_amount)).' to '.raw($advance->receiver_name).'. Enter the receipt amount per line; change or shortfall is calculated automatically.' : 'Enter the receipt amount per line; the buyer is paid back from petty cash.'">
                <x-dl::form.row :cols="2">
                    @foreach ($po->lines as $l)
                        <x-dl::field.money :name="'nota['.$l->po_line_id.']'" :label="'Receipt: '.raw($l->i_description.' ('.\Devlib\Locale::decimal($l->i_qty, 2).' '.$l->i_uom.')')" required :value="old('nota.'.$l->po_line_id, (float) $l->c_line_net)" :source="Src::of('tm2pur.po_line.i_price (from receipt)')" />
                    @endforeach
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    @if ($step === 'REIMBURSE')<x-dl::field.text name="receiver_name" label="Paid To" required :value="old('receiver_name')" placeholder="buyer name" :source="Src::of('tm2acc.fin_petty_cash_tx.receiver_name')" />@endif
                    <x-dl::field.upload name="nota_file" label="Receipt Photo / Scan" accept=".jpg,.jpeg,.png,.pdf" :source="Src::of('tm2acc.fin_petty_cash_tx.attachment_path')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
                    <x-dl::button preset="save" :label="$step === 'SETTLE' ? 'Post Settlement' : 'Pay Reimburse'" onclick="return confirm('Post this receipt to petty cash?')" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @else
        <x-dl::alert tone="info" message="This PO is not waiting for an advance or settlement (not approved, not a cash PO, or already done)." />
    @endif
@elseif (request('topup'))
    <x-dl::action-form action="{{ route('v2.petty.topup') }}">
        <x-dl::card title="Petty Cash Top Up" description="Money from the bank into petty cash. Journal: Dr Petty Cash / Cr Bank. Suggested amount = limit − cash on hand − open advances.">
            <x-dl::form.row :cols="3">
                <x-dl::field.money name="amount" label="Top Up Amount" required :value="old('amount', $sum['refill'])" :source="Src::of('tm2acc.fin_petty_cash_tx.cash_effect')" />
                <x-dl::field.select name="bank_account_id" label="From Account" required :options="PettyCash::bankAccounts($fund)" :source="Src::of('tm2acc.fin_account (CASH)')" />
                <x-dl::field.date name="tx_date" label="Date" required :value="old('tx_date', now()->toDateString())" :source="Src::of('tm2acc.fin_petty_cash_tx.tx_date')" />
            </x-dl::form.row>
            <x-dl::field.text name="notes" label="Notes" :value="old('notes')" :source="Src::of('tm2acc.fin_petty_cash_tx.notes')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Top Up" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@elseif (request('setup'))
    <x-dl::action-form action="{{ route('v2.petty.setup') }}">
        <x-dl::card title="Petty Cash Setting" description="Name, custodian, and imprest limit (the fixed amount kept by Top Up).">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="petty_name" label="Petty Cash Name" required :value="old('petty_name', $fund->petty_name)" :source="Src::of('tm2acc.fin_petty_cash.petty_name')" />
                <x-dl::field.select name="custodian_user_id" label="Custodian" required :options="PettyCash::users()" :value="$fund->custodian_user_id" :source="Src::of('tm2acc.fin_petty_cash.custodian_user_id')" />
                <x-dl::field.money name="imprest_amount" label="Imprest Limit" :value="$fund->imprest_amount === null ? null : (float) $fund->imprest_amount" :source="Src::of('tm2acc.fin_petty_cash.imprest_amount')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
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
        :detail="['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
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
        :detail="['key' => 'items', 'title' => 'Item (Cash PO)', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]]"
        :stats="[['label' => 'All'], ['label' => 'Cash Advance', 'where' => ['type' => 'Cash Advance'], 'tone' => 'warn'], ['label' => 'Settlement / Reimburse', 'where' => ['type' => ['Settlement', 'Reimburse']], 'tone' => 'info'], ['label' => 'Top Up', 'where' => ['type' => 'Top Up'], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'Cancelled'], 'tone' => 'danger']]"
        :filters="['type' => 'Transaction']" />
@endif
