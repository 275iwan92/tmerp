{{--
 REMARK
 Tujuan       : Form voucher Kas & Bank v2 (menggantikan halaman v1 pages::finance.cash-bank): BKM / BKK / BBM / BBK dengan baris jurnal, tab Tax (PPN, e-Faktur, PPh, bukti potong), Confirm = Post ke GL, Cancel Draft, Print.
 Sumber hulu  : tm2acc.fin_voucher + fin_voucher_line, fin_account (kas & bank = kode 111x), fin_journal (CSH / BNK); aturan di App\Services\Acc\CashBankVoucherService dan JournalPostingService::postDraft.
 Input        : Query id (voucher) atau type + account (voucher baru: baris pertama = akun kas/bank di sisi menurut jenis); new=1 menampilkan kartu Start; session v2_flash dan errors.
 Output       : POST v2.acc.cashbank.save (Save / Confirm / Cancel / Duplicate).
 Dipakai oleh : Dibuka dari grid acc-cash-bank (Open dan + New Record).
 Status       : Aktif. Posted dan Cancelled baca-saja; tidak ada Reverse (sama seperti v1). Keseimbangan debit-kredit dicek saat Confirm (Post).
--}}
@php
    use App\Models\Acc\FinVoucher;
    use App\Services\Acc\CashBankVoucherService as Cb;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'acc-cash-bank');
    $svc = app(Cb::class);
    $v = request('id') ? FinVoucher::with('lines.account')->find((int) request('id')) : null;
    abort_if(request('id') && ! $v, 404);
    abort_unless($v || request('new') || request('type'), 404);
    $new = ! $v;
    $start = $new && ! request('type');
    $type = $new ? (array_key_exists((string) request('type'), Cb::TYPES) ? request('type') : 'BKM') : $v->voucher_type;
    $account = $new ? (int) request('account') : (int) $v?->main_account_id;
    $v ??= new FinVoucher(['voucher_type' => $type, 'entry_date' => now(), 'main_account_id' => $account ?: null, 'ppn_type' => 'NONE', 'pph_type' => 'NONE', 'status' => 'DRAFT']);
    [$state, $stage] = $new ? ['new', null] : $svc->formState($v);
    $draft = $new || $v->status === 'DRAFT';
    $rows = old('lines') ?: ($new ? ($account ? $svc->starterLines($type, $account) : []) : $v->lines->map(fn ($l) => ['account_id' => $l->account_id, 'desc' => (string) $l->narration,
        'debit' => (float) $l->debit > 0 ? (float) $l->debit : '', 'credit' => (float) $l->credit > 0 ? (float) $l->credit : ''])->all());
    $rows = $rows ?: array_fill(0, 2, ['account_id' => '', 'desc' => '', 'debit' => '', 'credit' => '']);
    $sum = fn (string $k) => round((float) collect($rows)->sum(fn ($r) => (float) (Locale::input($r[$k] ?? 0) ?? 0)), 2);
    $debit = $sum('debit');
    $credit = $sum('credit');
    $s = fn (string $col) => Src::of('tm2acc.fin_voucher.'.$col);
    $val = fn (string $col, $default = null) => old($col, $default);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($start)
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'acc-cash-bank-form') }}">
        <x-dl::card title="Start a voucher" description="Choose the type and the cash / bank account first: the first line is filled in for you (debit for receipts, credit for payments).">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="type" label="Type" required :options="$svc->typeOptions()" :value="request('type', 'BKM')" :source="$s('voucher_type')" />
                <x-dl::field.select name="account" label="Cash / bank account" required :options="$svc->cashBankOptions()" :source="$s('main_account_id')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Start" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    <x-dl::form id="acc-cash-bank-form" :title="$new ? 'New Cash and Bank Voucher' : 'Cash and Bank Voucher'" :subtitle="$new ? null : raw($v->voucher_number)"
        panel="top" kind="document" :status="$state" :stage="$stage" :pipeline="false"
        :action="route('v2.acc.cashbank.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['delete', 'cancel', 'email']"
        :print-url="$new ? null : PrintDocs::url('payment-voucher', $v->voucher_id)"
        :hidden="$new ? [] : ['id' => $v->voucher_id]" :tabs="['general' => 'General', 'tax' => 'Tax']" :log="$new ? [] : $svc->history($v)"
        :created-by="$v->created_by" :created-at="$v->created_at?->toDateTimeString()" :updated-by="$v->updated_by" :updated-at="$v->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if (! $new && $draft)
                <x-dl::button type="submit" name="action" value="cancel" variant="secondary" icon="fa-ban" label="Cancel Draft" formnovalidate />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Voucher No." :value="$new ? t('(on Save)') : $v->voucher_number" />
            <x-dl::field.date name="entry_date" label="Entry date" required :value="$val('entry_date', $v->entry_date?->toDateString())" :source="$s('entry_date')" />
            <x-dl::field.select name="voucher_type" label="Type" required :options="$svc->typeOptions()" :value="$val('voucher_type', $type)" :source="$s('voucher_type')" />
            <x-dl::field.select name="main_account_id" label="Cash / bank account" required :options="$svc->cashBankOptions($v->main_account_id)" :value="$val('main_account_id', $v->main_account_id)" :source="$s('main_account_id')" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="partner_name" label="Paid to / received from" placeholder="Company or person" :value="$val('partner_name', $v->partner_name)" :source="$s('partner_name')" />
                <x-dl::field.text name="reference_no" label="Reference / cheque / giro no." :case-toggle="false" :value="$val('reference_no', $v->reference_no)" :source="$s('reference_no')" />
            </x-dl::form.row>
            @if (abs($debit - $credit) < 0.01 && $debit > 0)
                <x-dl::alert tone="success" :message="'Balanced: debit IDR '.Locale::number($debit, 2).' = credit IDR '.Locale::number($credit, 2)" />
            @elseif ($debit || $credit)
                <x-dl::alert tone="warning" :message="'Difference IDR '.Locale::number($debit - $credit, 2).' (debit IDR '.Locale::number($debit, 2).', credit IDR '.Locale::number($credit, 2).'). The voucher can be saved, but Confirm needs it balanced.'" />
            @endif
        </x-slot:general>
        <x-slot:tax>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="ppn_type" label="PPN treatment" :options="$svc->ppnOptions()" :value="$val('ppn_type', $v->ppn_type ?: 'NONE')" :source="$s('ppn_type')" />
                <x-dl::field.text name="efaktur_no" label="Tax invoice no. (e-Faktur)" placeholder="010.003-25.00000000" :case-toggle="false" :value="$val('efaktur_no', $v->efaktur_no)" :source="$s('efaktur_no')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="pph_type" label="Income tax withholding" :options="Cb::PPH" :value="$val('pph_type', $v->pph_type ?: 'NONE')" :source="$s('pph_type')" />
                <x-dl::field.text name="bupot_no" label="Withholding slip no." placeholder="BP-2025-0001928" :case-toggle="false" :value="$val('bupot_no', $v->bupot_no)" :source="$s('bupot_no')" />
            </x-dl::form.row>
        </x-slot:tax>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Lines" :min-rows="2" :rows="$rows" :readonly="! $draft" source="{{ Src::of('tm2acc.fin_voucher_line') }}"
                :columns="[
                    ['key' => 'account_id', 'label' => 'Account', 'type' => 'select', 'options' => $svc->accountOptions($new ? null : $v), 'required' => true, 'width' => '380px'],
                    ['key' => 'desc', 'label' => 'Narration'],
                    ['key' => 'debit', 'label' => 'Debit', 'type' => 'money', 'sum' => true, 'width' => '150px'],
                    ['key' => 'credit', 'label' => 'Credit', 'type' => 'money', 'sum' => true, 'width' => '150px'],
                ]" />
        </x-slot:detail>
    </x-dl::form>
@endif
