{{-- Tampilan objek x-mp::acc.petty-cash (MockupParts\Components\Acc\PettyCash). Nilai di $desk sudah jadi (diterjemahkan dan
     diformat pemilik data) -> diteruskan lewat Lang::raw; label ditulis English dan diterjemahkan objek DevLib. --}}
@php
    use Devlib\Lang;
    $k = $desk;
@endphp
<div {{ $attributes->class('mp-flat') }} {!! $dl() !!}>
    <x-dl::card :title="Lang::raw($k['title'])" :description="Lang::raw($k['description'])">
        <x-dl::kpi-row>
            @foreach ($k['stats'] as [$label, $value, $tone, $sub])
                <x-dl::stat-box :label="$label" :value="$value" :tone="$tone ?? 'accent'" :sub="$sub" />
            @endforeach
        </x-dl::kpi-row>
        @if ($k['showActions'])
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="fa-gear" label="Setting" :href="$k['home'].'?setup=1'" />
                <x-dl::button variant="success" icon="fa-circle-plus" label="Top Up" :href="$k['home'].'?topup=1'" />
            </x-dl::action-bar>
        @endif
    </x-dl::card>

    @if ($t = $k['tx'])
        <x-dl::card :title="Lang::raw($t['title'])" :description="$t['status']">
            @foreach (array_chunk($t['facts'], 4) as $row)
                <x-dl::form.row :cols="4">
                    @foreach ($row as [$label, $value, $sources])
                        <x-dl::field.display :label="$label" :value="$value" :source="$src(...$sources)" />
                    @endforeach
                </x-dl::form.row>
            @endforeach
            @if ($t['receiptUrl'])
                <x-dl::action-bar><x-dl::button size="sm" variant="secondary" icon="fa-receipt" label="Receipt Photo" :href="$t['receiptUrl']" target="_blank" /></x-dl::action-bar>
            @endif
            <x-dl::action-bar><x-dl::button size="sm" preset="print" :href="$t['printUrl']" target="_blank" /></x-dl::action-bar>
            @if ($t['cancelled'])
                <x-dl::alert tone="warn" :message="'Cancelled by '.Lang::raw($t['cancelled'])" />
            @endif
        </x-dl::card>
        @if ($c = $t['cancel'])
            <x-dl::action-form action="{{ $c['action'] }}">
                <x-dl::card title="Cancel Transaction" description="For wrong input only. The journal is reversed and the transaction is kept as Cancelled. A settled advance, or a receipt whose goods are already in an SPB, must be cancelled from the later step first.">
                    <x-dl::field.text name="cancel_reason" label="Cancel Reason" required :value="$c['reason']" placeholder="min. 10 characters" :source="$src('tm2acc.fin_petty_cash_tx.cancel_reason')" />
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="back" label="Back" :href="$k['home']" />
                        <x-dl::button variant="danger" icon="fa-ban" label="Cancel Transaction" type="submit" :onclick="$ask($c['confirm'])" />
                    </x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        @else
            <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$k['home']" /></x-dl::action-bar>
        @endif
    @elseif ($p = $k['po'])
        <x-dl::card :title="Lang::raw($p['title'])" :description="Lang::raw($p['description'])">
            <x-dl::table :source="$src('tm2pur.po_line')" :columns="['item' => 'Item', 'qty' => ['Qty', 'num' => true], 'uom' => 'UoM', 'est' => ['Estimate', 'num' => true, 'money' => true]]" :rows="$p['lines']" />
        </x-dl::card>
        @if ($a = $p['advance'])
            <x-dl::action-form action="{{ $a['action'] }}">
                <x-dl::card title="Cash Advance" description="Cash leaves the petty cash to the buyer. Journal: Dr Purchase Advance (114300) / Cr Petty Cash.">
                    <x-dl::form.row :cols="2">
                        <x-dl::field.money name="amount" label="Advance Amount" required :value="$a['amount']" :source="$src('tm2acc.fin_petty_cash_tx.advance_amount')" />
                        <x-dl::field.text name="receiver_name" label="Given To" required :value="$a['receiver']" placeholder="buyer name" :source="$src('tm2acc.fin_petty_cash_tx.receiver_name')" />
                    </x-dl::form.row>
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="back" label="Back" :href="$p['back']" />
                        <x-dl::button preset="save" label="Give Advance" :onclick="$ask($a['confirm'])" />
                    </x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        @elseif ($s = $p['settle'])
            <x-dl::action-form action="{{ $s['action'] }}" enctype="multipart/form-data">
                <x-dl::card :title="$settleTitle()" :description="Lang::raw($s['description'])">
                    <x-dl::form.row :cols="2">
                        @foreach ($s['lines'] as $l)
                            <x-dl::field.money :name="$l['name']" :label="Lang::raw($l['label'])" required :value="$l['value']" :source="$src('tm2pur.po_line.i_price (from receipt)')" />
                        @endforeach
                    </x-dl::form.row>
                    <x-dl::form.row :cols="2">
                        @if ($s['reimburse'])<x-dl::field.text name="receiver_name" label="Paid To" required :value="$s['receiver']" placeholder="buyer name" :source="$src('tm2acc.fin_petty_cash_tx.receiver_name')" />@endif
                        <x-dl::field.upload name="nota_file" label="Receipt Photo / Scan" accept=".jpg,.jpeg,.png,.pdf" :source="$src('tm2acc.fin_petty_cash_tx.attachment_path')" />
                    </x-dl::form.row>
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="back" label="Back" :href="$p['back']" />
                        <x-dl::button preset="save" :label="$settleButton()" :onclick="$ask($s['confirm'])" />
                    </x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        @else
            <x-dl::alert tone="info" message="This PO is not waiting for an advance or settlement (not approved, not a cash PO, or already done)." />
        @endif
    @elseif ($f = $k['topup'])
        <x-dl::action-form action="{{ $f['action'] }}">
            <x-dl::card title="Petty Cash Top Up" description="Money from the bank into petty cash. Journal: Dr Petty Cash / Cr Bank. Suggested amount = limit − cash on hand − open advances.">
                <x-dl::form.row :cols="3">
                    <x-dl::field.money name="amount" label="Top Up Amount" required :value="$f['amount']" :source="$src('tm2acc.fin_petty_cash_tx.cash_effect')" />
                    <x-dl::field.select name="bank_account_id" label="From Account" required :options="$f['accounts']" :source="$src('tm2acc.fin_account (CASH)')" />
                    <x-dl::field.date name="tx_date" label="Date" required :value="$f['date']" :source="$src('tm2acc.fin_petty_cash_tx.tx_date')" />
                </x-dl::form.row>
                <x-dl::field.text name="notes" label="Notes" :value="$f['notes']" :source="$src('tm2acc.fin_petty_cash_tx.notes')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$k['home']" />
                    <x-dl::button preset="save" label="Top Up" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @elseif ($f = $k['setup'])
        <x-dl::action-form action="{{ $f['action'] }}">
            <x-dl::card title="Petty Cash Setting" description="Name, custodian, and imprest limit (the fixed amount kept by Top Up).">
                <x-dl::form.row :cols="3">
                    <x-dl::field.text name="petty_name" label="Petty Cash Name" required :value="$f['name']" :source="$src('tm2acc.fin_petty_cash.petty_name')" />
                    <x-dl::field.select name="custodian_user_id" label="Custodian" required :options="$f['users']" :value="$f['custodian']" :source="$src('tm2acc.fin_petty_cash.custodian_user_id')" />
                    <x-dl::field.money name="imprest_amount" label="Imprest Limit" :value="$f['imprest']" :source="$src('tm2acc.fin_petty_cash.imprest_amount')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$k['home']" />
                    <x-dl::button preset="save" label="Save" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @else
        {{ $slot }}
    @endif
</div>
