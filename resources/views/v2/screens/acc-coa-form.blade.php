{{--
 REMARK
 Tujuan       : Form Master of Accounts v2 (menggantikan dialog v1 pages::finance.coa): kode, nama, parent, tipe, saldo normal, cost center bawaan, skema pajak, penanda rekonsiliasi / bisa dijurnal / aktif.
 Sumber hulu  : tm2acc.fin_account, fin_cost_center; aturan di App\Services\Acc\ChartOfAccountService (header tidak diedit, kode unik, bukan parent diri sendiri).
 Input        : Query id (akun) atau new=1; session v2_flash dan errors.
 Output       : POST v2.acc.coa.save (Save / Activate / Deactivate) -> kembali ke form yang sama.
 Dipakai oleh : Dibuka dari grid acc-coa (Open dan + New Record).
 Status       : Aktif. Akun header (bukan akun jurnal) tampil baca-saja. Tarif pajak tidak disimpan di sini, hanya kode skema.
--}}
@php
    use App\Models\Acc\FinAccount;
    use App\Services\Acc\ChartOfAccountService as Coa;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'acc-coa');
    $a = request('id') ? FinAccount::with('parent')->find((int) request('id')) : null;
    abort_if(request('id') && ! $a, 404);
    abort_unless($a || request('new'), 404);
    $new = ! $a;
    $a ??= new FinAccount(['normal_balance' => 'DEBIT', 'is_postable' => true, 'is_recon' => false, 'activate' => true]);
    $locked = ! $new && ! $a->is_postable;
    $svc = app(Coa::class);
    $s = fn (string $col) => Src::of('tm2acc.fin_account.'.$col);
    $v = fn (string $col) => old($col, $a->{$col});
    $flag = fn (string $col) => old() ? (bool) old($col) : (bool) $a->{$col};
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
@if ($locked)<x-dl::alert tone="warning" message="Header accounts are read-only. They only group the accounts below them." />@endif

<x-dl::form id="acc-coa-form" :title="$new ? 'New Account' : raw($a->account_name)" :subtitle="$new ? null : raw($a->account_code.' · '.(Coa::TYPES[$a->account_type] ?? $a->account_type))"
    panel="top" kind="master" :status="$new ? 'new' : ($a->activate ? 'active' : 'inactive')" :can-input="! $locked"
    :action="route('v2.acc.coa.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $a->account_id]" :tabs="['general' => 'General']"
    :created-by="$a->created_by" :created-at="$a->created_at?->toDateTimeString()" :updated-by="$a->updated_by" :updated-at="$a->updated_at?->toDateTimeString()">
    <x-slot:general>
        <x-dl::card title="Account">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="account_code" label="Code" required :case-toggle="false" placeholder="115100" :value="$v('account_code')" :source="$s('account_code')" :readonly="$locked" />
                <x-dl::field.text name="account_name" label="Name" required placeholder="Petty cash, warehouse" :value="$v('account_name')" :source="$s('account_name')" :readonly="$locked" />
                <x-dl::field.select name="parent_id" label="Parent" placeholder="No parent (root)" :options="$svc->parentOptions($new ? null : $a->account_id)" :value="$v('parent_id')" :source="Src::of('tm2acc.fin_account.parent_id', 'tm2acc.fin_account')" :disabled="$locked" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="account_type" label="Type" required :options="Coa::TYPES" :value="$v('account_type')" :source="$s('account_type')" :disabled="$locked" />
                <x-dl::field.select name="normal_balance" label="Normal balance" required :options="['DEBIT' => 'Debit', 'CREDIT' => 'Credit']" :value="$v('normal_balance')" :source="$s('normal_balance')" :disabled="$locked" />
                <x-dl::field.select name="default_cc_id" label="Default cost center" placeholder="None" :options="$svc->costCenterOptions()" :value="$v('default_cc_id')" :source="Src::of('tm2acc.fin_account.default_cc_id', 'tm2acc.fin_cost_center')" :disabled="$locked" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="tax_scheme_code" label="Tax scheme" placeholder="PPN_IN, PPN_OUT, PPH22" :value="$v('tax_scheme_code')" :source="$s('tax_scheme_code')" :readonly="$locked" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.checkbox name="is_recon" text="Reconciliation account (AR/AP)" :value="$flag('is_recon')" :source="$s('is_recon')" :disabled="$locked" />
                <x-dl::field.checkbox name="is_postable" text="Postable" :value="$flag('is_postable')" :source="$s('is_postable')" :disabled="$locked" />
                <x-dl::field.checkbox name="activate" text="Active" :value="$flag('activate')" :source="$s('activate')" :disabled="$locked" />
            </x-dl::form.row>
            <x-dl::hint note>Header accounts (not postable) are read-only. An account cannot be its own parent. Tax rates are not stored here.</x-dl::hint>
        </x-dl::card>
    </x-slot:general>
</x-dl::form>
