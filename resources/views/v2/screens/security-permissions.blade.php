{{-- Setup > Security > Groups on Modules (v2). Data & aturan = App\Support\V2\Data\GroupPermissions (sec_group_permission):
     pilih grup, lalu beri tiap modul tingkat akses. Administrator melewati pengecekan ini. Save mengirim satu grup per klik. --}}
@php
    use App\Support\V2\Data\GroupPermissions;
    use App\Support\V2\Src;
@endphp

<x-dl::card title="Groups on Modules" description="Choose a group, then set its access level on each module. Administrators always have full access. A change takes effect the next time the user opens a screen.">
    <x-dl::permission-matrix :objects="GroupPermissions::objects()" :groups="GroupPermissions::groups()" :perms="GroupPermissions::levels()" :default="0"
        :save-url="route('v2.security.permissions.save')" :source="Src::of('tm2sys.sec_group_permission', 'tm2sys.sec_module', 'tm2sys.sec_group')" />
</x-dl::card>
