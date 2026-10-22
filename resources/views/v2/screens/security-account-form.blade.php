{{--
 REMARK
 Tujuan       : Form Account (menggantikan form Pengguna halaman v1 pages::security.index tab users): akun baru (login, nama, e-mail, password awal, grup) atau ubah nama / e-mail / grup; tombol Reset Password (password acak baru), Reset to Default (kembali ke default_password tersimpan), View Password (password bawaan yang tersimpan, untuk spot-check) dan Deactivate / Activate; kartu login tampil sekali. Hanya administrator.
 Sumber hulu  : tm2sys.sec_user + sec_user_group + sec_group; aturan di App\Services\Security\SecurityAccountService (reset dan aktif / nonaktif dari EmployeeAccountService, satu sumber dengan tab Account Master Employee).
 Input        : Query id (sec_user.id_user) atau new=1; session v2_flash, errors dan v2_login_card.
 Output       : POST v2.setup.account.save (action save, reset, default, reveal, toggle).
 Dipakai oleh : Dibuka dari grid security-accounts (Open dan + New Record) dan tab Account hrm-employee-form (Open in Security).
 Status       : Aktif (T8 Setup).
--}}
@php
    use App\Models\Sys\SecGroup;
    use App\Models\Sys\SecUser;
    use App\Services\Security\SecurityAccountService as Acct;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'security-accounts');
    $svc = app(Acct::class);
    $me = Auth::user();
    abort_unless($me->canSetup(), 403, t('This page is for administrators only.'));
    $u = request('id') ? SecUser::with('groups')->find((int) request('id')) : null;
    abort_if(request('id') && ! $u, 404);
    abort_unless($u || request('new'), 404);
    $new = ! $u;
    $self = ! $new && $u->getKey() === $me->getKey();
    $s = fn (string $col) => Src::of('tm2sys.sec_user.'.$col);
    $val = fn (string $col) => old($col, $u?->{$col});
    $groups = SecGroup::where('activate', true)->orderBy('group_name')->pluck('group_name', 'id_group')->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="security-account-form" :title="$new ? 'New Account' : raw($u->name)" :subtitle="$new ? null : raw($u->login)" panel="top" kind="master" :status="$new ? 'new' : ($u->activate ? 'active' : 'inactive')" :can-input="true"
    :hide="['deactivate', 'activate', 'new']" :action="route('v2.setup.account.save')" action-field="action" :back="$home" :hidden="$new ? [] : ['id' => $u->id_user]"
    :tabs="['general' => t('Account')]" :log="$new ? [] : $svc->history($u)" :created-at="$u?->created_at?->toDateTimeString()" :updated-at="$u?->updated_at?->toDateTimeString()">
    @unless ($new)
        <x-slot:special>
            <x-dl::button variant="secondary" icon="fa-key" label="Reset Password" type="submit" name="action" value="reset" formnovalidate data-dl-confirm="{{ tf('Reset the password of :login to a new password?', ['login' => $u->login]) }}" />
            @if ($u->default_password)
                <x-dl::button variant="secondary" icon="fa-rotate-left" label="Reset to Default" type="submit" name="action" value="default" formnovalidate data-dl-confirm="{{ tf('Set the password of :login back to the stored default password?', ['login' => $u->login]) }}" />
                <x-dl::button variant="secondary" icon="fa-eye" label="View Password" type="submit" name="action" value="reveal" formnovalidate />
            @endif
            @unless ($self)
                <x-dl::button :variant="$u->activate ? 'danger' : 'success'" :icon="$u->activate ? 'fa-user-lock' : 'fa-user-check'" :label="$u->activate ? 'Deactivate Account' : 'Activate Account'" type="submit" name="action" value="toggle" formnovalidate
                    data-dl-confirm="{{ tf(($u->activate ? 'Deactivate' : 'Activate').' user :login?', ['login' => $u->login]) }}" />
            @endunless
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Login" :value="raw($u->login)" />
            <x-dl::strip-kv kind="grand" label="Status" :value="t($u->activate ? 'Active' : 'Inactive')" />
            <x-dl::strip-kv kind="grand" label="Group" :value="raw($u->groups->pluck('group_name')->implode(', ') ?: '—')" />
            <x-dl::strip-kv kind="grand" label="Password" :value="t($u->must_change_password ? 'Must change' : 'OK')" />
        </x-slot:strip>
    @endunless

    <x-slot:general>
        <x-dl::card title="Account">
            @if ($new)
                <x-dl::hint note>The login cannot be changed later. The initial password must be at least 8 characters; give it to the user by hand.</x-dl::hint>
            @endif
            <x-dl::form.row :cols="3">
                @if ($new)
                    <x-dl::field.text name="login" label="Login" required :case-toggle="false" placeholder="e.g. budi.santoso" :value="old('login')" :source="$s('login')" />
                @else
                    <x-dl::field.display label="Login" :value="raw($u->login)" :source="$s('login')" />
                @endif
                <x-dl::field.text name="name" label="Name" required :value="$val('name')" :source="$s('name')" />
                <x-dl::field.text name="email" label="E-mail" :case-toggle="false" :value="$val('email')" :source="$s('email')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="group_id" label="Group" required :options="$groups" :value="old('group_id', $u?->groups->first()?->id_group)" placeholder="— select group —" :source="Src::of('tm2sys.sec_user_group.group_id')" />
                @if ($new)
                    <x-dl::field.password name="password" label="Initial password" required :source="$s('pswd')" />
                @else
                    <x-dl::field.display label="E-mail verified" :value="t($u->email_verified ? 'Yes' : 'No')" :source="$s('email_verified')" />
                    <x-dl::field.display label="Last password change" :value="$u->pswd_last_updated ? Locale::dateTime($u->pswd_last_updated) : '—'" :source="$s('pswd_last_updated')" />
                @endif
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:general>
</x-dl::form>

@include('v2.partials.login-card')
