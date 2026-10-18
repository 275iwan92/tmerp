{{--
 REMARK
 Tujuan       : Form Journal Entry v2 (menggantikan dialog v1 pages::finance.journals): jurnal umum manual dengan baris debit / kredit, ajukan (Confirm), setujui, kembalikan ke Draft, posting ke GL, balik (Reverse), hapus draft.
 Sumber hulu  : tm2acc.fin_voucher + fin_voucher_line, fin_account, fin_cost_center, fin_approval_rule (JOURNAL); aturan di App\Services\Acc\ManualJournalService dan JournalPostingService.
 Input        : Query id (jurnal) atau new=1 (+ template=id jurnal Recurring yang sudah diposting); session v2_flash dan errors.
 Output       : POST v2.acc.journal.save (Save / Confirm / Delete / Approve / Reject / Return / Post), POST v2.acc.journal.reverse (dialog Reverse).
 Dipakai oleh : Dibuka dari grid acc-journal (Open dan + New Record).
 Status       : Aktif. Nomor JV terbit saat Save; Posted tidak pernah diedit (koreksi = Reverse). Penanda Balanced hidup di bawah baris; angka total hidup di strip atas.
--}}
@php
    use App\Models\Acc\FinVoucher;
    use App\Services\Acc\ManualJournalService as Jv;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'acc-journal');
    $svc = app(Jv::class);
    $v = request('id') ? FinVoucher::with('lines.account')->find((int) request('id')) : null;
    abort_if(request('id') && ! $v, 404);
    abort_unless($v || request('new'), 404);
    $new = ! $v;
    $tpl = $new && request('template') ? FinVoucher::with('lines')->where('journal_kind', 'RECURRING')->find((int) request('template')) : null;
    $v ??= new FinVoucher(['journal_kind' => $tpl ? 'RECURRING' : 'MANUAL', 'entry_date' => now(), 'description' => $tpl?->description, 'status' => 'DRAFT']);
    [$state, $stage] = $new ? ['new', null] : $svc->formState($v);
    $draft = $new || $v->status === 'DRAFT';
    $user = Auth::user();
    $kinds = Jv::ENTRY_KINDS + (isset(Jv::KINDS[$v->journal_kind]) ? [$v->journal_kind => Jv::KINDS[$v->journal_kind]] : []);
    $src = $tpl ?? $v;
    $rows = old('lines') ?: $src->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => (float) $l->debit ?: '', 'credit' => (float) $l->credit ?: '', 'cc_id' => $l->cc_id ?: '', 'narration' => (string) $l->narration])->all();
    $rows = $rows ?: array_fill(0, 2, ['account_id' => '', 'debit' => '', 'credit' => '', 'cc_id' => '', 'narration' => '']);
    $debit = round((float) collect($rows)->sum(fn ($r) => (float) (Locale::input($r['debit'] ?? 0) ?? 0)), 2);
    $credit = round((float) collect($rows)->sum(fn ($r) => (float) (Locale::input($r['credit'] ?? 0) ?? 0)), 2);
    $s = fn (string $col) => Src::of('tm2acc.fin_voucher.'.$col);
    $val = fn (string $col, $default = null) => old($col, $default);
    $templates = $new && ! $tpl ? $svc->templates() : collect();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($templates->isNotEmpty())
    <x-dl::card title="Start from a recurring journal" description="Fills the description and the lines from a posted Recurring Journal. Nothing is saved until you press Save.">
        <x-dl::action-bar>
            @foreach ($templates as $t)
                <x-dl::button size="sm" variant="secondary" icon="fa-rotate" :label="raw($t->description)" :href="$home.'?new=1&template='.$t->voucher_id" />
            @endforeach
        </x-dl::action-bar>
    </x-dl::card>
@endif

<x-dl::form id="acc-journal-form" :title="$new ? 'New Journal Entry' : 'Journal Entry'" :subtitle="$new ? null : raw($v->voucher_number)"
    panel="top" kind="document" :status="$state" :stage="$stage" :pipeline="false" :approval-levels="1" :approval-at="1" :can-approve="! $new && $svc->canApprove($v, $user)"
    :action="route('v2.acc.journal.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['hold', 'cancel', 'email', 'duplicate']"
    :hidden="$new ? [] : ['id' => $v->voucher_id]" :tabs="['general' => 'General']" :log="$new ? [] : $svc->history($v)"
    :created-by="$v->created_by" :created-at="$v->created_at?->toDateTimeString()" :updated-by="$v->updated_by" :updated-at="$v->updated_at?->toDateTimeString()">
    <x-slot:special>
        @if ($v->status === 'APPROVED' || ($v->status === 'SUBMITTED' && ! $svc->canApprove($v, $user)))
            <x-dl::button type="submit" name="action" value="return" variant="secondary" icon="fa-rotate-left" label="Return to Draft" formnovalidate />
        @endif
        @if ($v->status === 'APPROVED')
            <x-dl::button type="submit" name="action" value="post" variant="primary" icon="fa-check-double" label="Post to GL" formnovalidate />
        @endif
        @if (! $new && $svc->canReverse($v))
            <x-dl::button type="button" variant="secondary" icon="fa-arrows-left-right" label="Reverse" data-dl-open="jv-reverse" />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Journal No." :value="$new ? t('(on Save)') : $v->voucher_number" />
        <x-dl::field.date name="entry_date" label="Transaction date" required :value="$val('entry_date', $v->entry_date?->toDateString())" :source="$s('entry_date')" />
        <x-dl::field.select name="journal_kind" label="Journal type" required :options="$kinds" :value="$val('journal_kind', $v->journal_kind)" :source="$s('journal_kind')" />
        <x-dl::strip-kv kind="grand" label="Total debit" :value="'IDR '.Locale::number($debit, 2)" :value-attrs="['data-l-val' => 'lines:sum.debit']" />
    </x-slot:strip>
    <x-slot:general>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="description" label="Description" required placeholder="Factory electricity accrual, September 2026" :value="$val('description', $src->description)" :source="$s('description')" />
            <x-dl::field.text name="reference_no" label="Reference" placeholder="REF-2026-0001" :case-toggle="false" :value="$val('reference_no', $v->reference_no)" :source="$s('reference_no')" />
            <x-dl::field.date name="auto_reverse_date" label="Auto-reverse date" hint="Accrual journals only: the reversing journal is posted on this date." :value="$val('auto_reverse_date', $v->auto_reverse_date?->toDateString())" :source="$s('auto_reverse_date')" />
        </x-dl::form.row>
        @if (! $new && $v->reversal_of_id)<x-dl::alert tone="info" :message="raw('Reverses '.$v->reference_no)" />@endif
        @if (abs($debit - $credit) < 0.01 && $debit > 0)
            <x-dl::alert tone="success" :message="'Balanced: debit IDR '.Locale::number($debit, 2).' = credit IDR '.Locale::number($credit, 2)" />
        @elseif ($debit || $credit)
            <x-dl::alert tone="warning" :message="'Difference IDR '.Locale::number($debit - $credit, 2).' (debit IDR '.Locale::number($debit, 2).', credit IDR '.Locale::number($credit, 2).')'" />
        @endif
    </x-slot:general>
    <x-slot:detail>
        <x-dl::lines name="lines" title="Lines" :min-rows="2" :rows="$rows" :readonly="! $draft" source="{{ Src::of('tm2acc.fin_voucher_line') }}"
            :columns="[
                ['key' => 'account_id', 'label' => 'Account', 'type' => 'select', 'options' => $svc->accountOptions($new ? null : $v), 'required' => true, 'width' => '360px'],
                ['key' => 'debit', 'label' => 'Debit', 'type' => 'money', 'sum' => true, 'width' => '150px'],
                ['key' => 'credit', 'label' => 'Credit', 'type' => 'money', 'sum' => true, 'width' => '150px'],
                ['key' => 'cc_id', 'label' => 'Cost center', 'type' => 'select', 'options' => $svc->costCenterOptions(), 'width' => '160px'],
                ['key' => 'narration', 'label' => 'Narration'],
            ]" />
    </x-slot:detail>
</x-dl::form>

@if (! $new && $svc->canReverse($v))
    <x-dl::modal id="jv-reverse" title="Reverse Journal" icon="fa-arrows-left-right" size="md">
        <x-dl::action-form action="{{ route('v2.acc.journal.reverse', $v->voucher_id) }}">
            <x-dl::form.row :cols="2">
                <x-dl::field.date name="reverse_date" label="Reversal date" required :value="now()->toDateString()" :source="$s('entry_date')" />
                <x-dl::field.text name="reverse_reason" label="Reason" required placeholder="Wrong cost center" :case-toggle="false" :source="$s('description')" />
            </x-dl::form.row>
            <x-dl::alert tone="info" message="Debit and credit are reversed in full into a new voucher with status Posted. The original voucher stays intact as the audit trail." />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" label="Post Reversal" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>
@endif
