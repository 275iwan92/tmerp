{{--
 REMARK
 Tujuan       : Rekonsiliasi bank: impor mutasi, Auto-Match, investigasi selisih (cocok manual atau jurnal penyesuaian DRAFT) lalu Reconcile.
 Sumber hulu  : App\Support\V2\Data\BankDesk (BankReconService); tabel tm2acc.fin_bank_statement, fin_bank_recon, fin_voucher, fin_account.
 Input        : Query string account, type, q, stmt; flash v2_flash / v2_warn dan errors.
 Output       : x-dl::card, x-dl::kpi-row, tabel dan form DevLib; POST ke route v2.acc.bank.* (auto-match, reconcile, impor, cocok manual).
 Dipakai oleh : Menu Finance > Transaction Flows > Bank & Reconciliation (slug acc-bank, route v2.screen).
 Status       : Aktif.
--}}
@php
    use App\Models\Acc\FinAccount;
    use App\Support\V2\Data\BankDesk as Bank;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $accountId = request()->integer('account') ?: Bank::defaultAccount();
    $account = $accountId ? FinAccount::find($accountId) : null;
    $home = route('v2.screen', 'acc-bank');
    $src = Src::of('tm2acc.fin_bank_statement', 'tm2acc.fin_bank_recon', 'tm2acc.fin_voucher', 'tm2acc.fin_account');
    $headers = 'date/tanggal, description/keterangan, reference, debit/keluar, credit/masuk, balance/saldo';
    $stmtLine = request('stmt') ? Bank::line(request()->integer('stmt')) : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if (session('v2_warn'))<x-dl::alert tone="warn" :message="raw(session('v2_warn'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $account)
    <x-dl::alert tone="warn" message="There is no cash / bank account yet." />
@else
    @php
        $stats = Bank::stats($accountId);
        $type = in_array(request('type'), ['IN', 'OUT'], true) ? request('type') : '';
        $search = trim((string) request('q'));
        $matched = Bank::matchedVouchers();
        $statement = Bank::statement($accountId);
        $unmatched = $statement->where('match_status', 'UNMATCHED');
        $tone = fn (?string $s) => Bank::TONES[$s ?? 'UNMATCHED'] ?? 'warn';
    @endphp

    <x-dl::card title="Bank & Cash Process" description="Bank journals, cash movements, statement imports, matching, and reconciliation.">
        <x-dl::action-form method="get" action="{{ $home }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="account" label="Bank Account" :options="Bank::accountOptions()" :value="$accountId" :placeholder="null" :source="$src" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
                <x-dl::button variant="secondary" icon="fa-plus" label="Bank Transaction" :href="route('v2.screen', 'acc-cash-bank')" />
            </x-dl::action-bar>
        </x-dl::action-form>
        <x-dl::action-bar>
            <x-dl::action-form action="{{ route('v2.acc.bank.auto-match') }}">
                <input type="hidden" name="account" value="{{ $accountId }}">
                <x-dl::button variant="secondary" icon="fa-bolt" label="Auto-Match" />
            </x-dl::action-form>
            <x-dl::action-form action="{{ route('v2.acc.bank.reconcile') }}">
                <input type="hidden" name="account" value="{{ $accountId }}">
                <x-dl::button icon="fa-circle-check" label="Reconcile Now" data-dl-confirm="{{ t('Record the reconciliation of this account now?') }}" />
            </x-dl::action-form>
        </x-dl::action-bar>
    </x-dl::card>

    <x-dl::kpi-row>
        @foreach (Bank::kpis($stats, $account) as $k)
            <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
        @endforeach
    </x-dl::kpi-row>

    <x-dl::flow title="Bank Reconciliation Flow" :steps="Bank::flow($stats)" :source="$src" />

    <x-dl::card title="Import Bank Statement" :description="raw(tf('Required header columns: :columns. Separator , or ;. Numbers may use thousand separators.', ['columns' => $headers]))">
        <x-dl::action-form action="{{ route('v2.acc.bank.import') }}" enctype="multipart/form-data">
            <input type="hidden" name="account" value="{{ $accountId }}">
            <x-dl::field.upload name="csv" label="CSV file" accept=".csv,.txt" :preview="false" required :source="$src" />
            <x-dl::action-bar><x-dl::button icon="fa-file-import" label="Import" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>

    @if ($stmtLine)
        @php $candidates = Bank::candidates($stmtLine); @endphp
        <x-dl::card title="Investigate Statement Line" :description="raw(Locale::d($stmtLine->txn_date).' · '.($stmtLine->reference ?: '—').' — '.$stmtLine->description.' · '.((float) $stmtLine->amount_in > 0 ? t('In').' '.Locale::money((float) $stmtLine->amount_in) : t('Out').' '.Locale::money((float) $stmtLine->amount_out)))">
            <x-dl::action-form action="{{ route('v2.acc.bank.match', $stmtLine->stmt_line_id) }}">
                <input type="hidden" name="account" value="{{ $accountId }}">
                <x-dl::field.select name="voucher" label="Match to book entry (±30 days, closest amount first)" required placeholder="— choose voucher —"
                    :options="$candidates->mapWithKeys(fn ($l) => [$l->voucher_id => Locale::d($l->voucher->entry_date).' · '.$l->voucher->voucher_number.' · '.Locale::money((float) ($l->debit ?: $l->credit))])->all()" :source="$src" />
                <x-dl::action-bar><x-dl::button icon="fa-link" label="Match" /></x-dl::action-bar>
            </x-dl::action-form>
            <x-dl::action-form action="{{ route('v2.acc.bank.adjust', $stmtLine->stmt_line_id) }}">
                <input type="hidden" name="account" value="{{ $accountId }}">
                <x-dl::field.select name="adj_account" label="…or create adjustment journal against" required :options="Bank::counterAccounts()" :value="\App\Models\Acc\FinAccount::where('account_code', '812000')->value('account_id')" :placeholder="null"
                    hint="Bank charges, interest, interest tax, etc. Created as a DRAFT and must be approved in Journal Entries." :source="$src" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home.'?account='.$accountId" />
                    <x-dl::button icon="fa-file-circle-plus" label="Create Adjustment Journal" />
                </x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::card>
    @endif

    <x-dl::card title="Bank Statement" description="Imported statement lines to reconcile.">
        <x-dl::table :columns="['date' => 'Date', 'ref' => 'Reference / Description', 'in' => ['In', 'num' => true], 'out' => ['Out', 'num' => true], 'status' => 'Status']"
            :rows="$statement->map(fn ($s) => ['date' => Locale::d($s->txn_date), 'ref' => raw(($s->reference ?: '—').' · '.$s->description), 'in' => (float) $s->amount_in > 0 ? Locale::money((float) $s->amount_in) : '', 'out' => (float) $s->amount_out > 0 ? Locale::money((float) $s->amount_out) : '',
                'status' => t(ucfirst(strtolower((string) $s->match_status)))])->all()" empty="No bank statement yet — import one above." :source="$src" />
        @foreach ($unmatched as $s)
            <x-dl::action-bar>
                <x-dl::badge :label="raw(Locale::d($s->txn_date).' · '.($s->reference ?: '—'))" tone="warn" />
                <x-dl::button size="sm" variant="secondary" icon="fa-magnifying-glass" label="Investigate / match" :href="$home.'?account='.$accountId.'&stmt='.$s->stmt_line_id" />
                <x-dl::action-form action="{{ route('v2.acc.bank.ignore', $s->stmt_line_id) }}">
                    <input type="hidden" name="account" value="{{ $accountId }}">
                    <x-dl::button size="sm" variant="ghost" icon="fa-eye-slash" label="Ignore" data-dl-confirm="{{ t('Mark this line as ignored?') }}" />
                </x-dl::action-form>
            </x-dl::action-bar>
        @endforeach
    </x-dl::card>

    <x-dl::card title="Bank Journal" description="Recent cash and bank movements (posted GL).">
        <x-dl::action-form method="get" action="{{ $home }}">
            <input type="hidden" name="account" value="{{ $accountId }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="q" label="Search" :value="$search" placeholder="Search reference or description..." :case-toggle="false" :source="$src" />
                <x-dl::field.select name="type" label="Transaction Type" :options="['IN' => t('Receipt'), 'OUT' => t('Payment')]" :value="$type" placeholder="All transaction types" :source="$src" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-filter" label="Filter" /></x-dl::action-bar>
        </x-dl::action-form>
        <x-dl::table :columns="['date' => 'Date', 'ref' => 'Reference', 'desc' => 'Description', 'in' => ['Money In', 'num' => true], 'out' => ['Money Out', 'num' => true], 'match' => 'Match Status']"
            :rows="Bank::movements($accountId, $type, $search)->map(fn ($l) => ['date' => Locale::d($l->voucher->entry_date), 'ref' => raw($l->voucher->voucher_number.($l->voucher->reference_no ? ' · '.$l->voucher->reference_no : '')),
                'desc' => raw((string) $l->voucher->description), 'in' => (float) $l->debit > 0 ? Locale::money((float) $l->debit) : '', 'out' => (float) $l->credit > 0 ? Locale::money((float) $l->credit) : '',
                'match' => isset($matched[$l->voucher_id]) ? t('Matched') : t('Unmatched')])->all()" empty="No posted movements on this account." :source="$src" />
    </x-dl::card>
@endif
