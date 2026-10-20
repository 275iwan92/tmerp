{{--
 REMARK
 Tujuan       : Form Group (menggantikan form Grup halaman v1 pages::security.index tab groups): grup akses baru (kode, nama, level 1-5) atau ubah nama / level; tombol Deactivate / Activate (grup dengan anggota aktif tidak bisa dinonaktifkan); daftar anggota. Grup = level akses, bukan jabatan; hak per modul di layar Groups on Modules. Hanya administrator.
 Sumber hulu  : tm2sys.sec_group + sec_user_group + sec_user; aturan di App\Services\Security\SecurityGroupService.
 Input        : Query id (sec_group.id_group) atau new=1; session v2_flash dan errors.
 Output       : POST v2.setup.group.save (action save, toggle).
 Dipakai oleh : Dibuka dari grid security-groups (Open dan + New Record).
 Status       : Aktif (T8 Setup).
--}}
@php
    use App\Models\Sys\SecGroup;
    use App\Services\Security\SecurityGroupService as Grp;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'security-groups');
    $me = Auth::user();
    abort_unless($me->canSetup(), 403, t('This page is for administrators only.'));
    $g = request('id') ? SecGroup::with('users')->find((int) request('id')) : null;
    abort_if(request('id') && ! $g, 404);
    abort_unless($g || request('new'), 404);
    $new = ! $g;
    $s = fn (string $col) => Src::of('tm2sys.sec_group.'.$col);
    $val = fn (string $col) => old($col, $g?->{$col});
    $members = $new ? [] : $g->users->map(fn ($u) => ['login' => raw($u->login), 'name' => raw($u->name), 'status' => t($u->activate ? 'Active' : 'Inactive')])->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="security-group-form" :title="$new ? 'New Group' : raw($g->group_name)" :subtitle="$new ? null : raw($g->group_code)" panel="top" kind="master" :status="$new ? 'new' : ($g->activate ? 'active' : 'inactive')" :can-input="true"
    :hide="['deactivate', 'activate', 'new']" :action="route('v2.setup.group.save')" action-field="action" :back="$home" :hidden="$new ? [] : ['id' => $g->id_group]"
    :tabs="['general' => t('Group'), 'members' => t('Members')]" :created-at="$g?->created_at?->toDateTimeString()" :updated-at="$g?->updated_at?->toDateTimeString()">
    @unless ($new)
        <x-slot:special>
            <x-dl::button :variant="$g->activate ? 'danger' : 'success'" :icon="$g->activate ? 'fa-toggle-off' : 'fa-toggle-on'" :label="$g->activate ? 'Deactivate Group' : 'Activate Group'" type="submit" name="action" value="toggle" formnovalidate
                data-dl-confirm="{{ tf(($g->activate ? 'Deactivate' : 'Activate').' group :name?', ['name' => $g->group_name]) }}" />
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Group code" :value="raw($g->group_code)" />
            <x-dl::strip-kv kind="grand" label="Access level" :value="t(Grp::LEVELS[$g->mgmt_level] ?? '—').' ('.$g->mgmt_level.')'" />
            <x-dl::strip-kv kind="grand" label="Members" :value="(string) count($members)" />
            <x-dl::strip-kv kind="grand" label="Status" :value="t($g->activate ? 'Active' : 'Inactive')" />
        </x-slot:strip>
    @endunless

    <x-slot:general>
        <x-dl::card title="Group">
            @if ($new)
                <x-dl::hint note>The code is written in capitals with spaces turned into underscores and cannot be changed later. Rights per module are set in Groups on Modules.</x-dl::hint>
            @endif
            <x-dl::form.row :cols="3">
                @if ($new)
                    <x-dl::field.text name="group_code" label="Group code" required :case-toggle="false" placeholder="e.g. HR_OFFICER" :value="old('group_code')" :source="$s('group_code')" />
                @else
                    <x-dl::field.display label="Group code" :value="raw($g->group_code)" :source="$s('group_code')" />
                @endif
                <x-dl::field.text name="group_name" label="Group name" required :value="$val('group_name')" :source="$s('group_name')" />
                <x-dl::field.select name="mgmt_level" label="Access level" required :options="array_map('t', Grp::LEVELS)" :value="old('mgmt_level', $g?->mgmt_level ?? 1)" :placeholder="null" :source="$s('mgmt_level')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:general>

    <x-slot:members>
        <x-dl::card title="Members (read-only)">
            <x-dl::table :columns="['login' => 'Login', 'name' => 'Name', 'status' => 'Status']" :rows="$members" />
        </x-dl::card>
    </x-slot:members>
</x-dl::form>
