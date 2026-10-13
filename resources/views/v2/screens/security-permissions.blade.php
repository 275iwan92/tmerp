{{--
 REMARK
 Tujuan       : Setup > Security > Groups on Modules: memberi tiap grup tingkat akses pada tiap modul.
 Sumber hulu  : App\Support\V2\Data\GroupPermissions (tm2sys.sec_group_permission, sec_module, sec_group).
 Input        : Pilihan grup dan matriks level akses di objek matriks; default level 0.
 Output       : x-dl::card + x-dl::permission-matrix; simpan ke route v2.security.permissions.save (satu grup per klik).
 Dipakai oleh : route v2.screen (routes/v2.php), slug security-permissions (menu Setup > Security).
 Status       : Aktif; Administrator melewati pengecekan ini.
--}}
@php
    use App\Support\V2\Data\GroupPermissions;
    use App\Support\V2\Src;
@endphp

<x-dl::card title="Groups on Modules" description="Choose a group, then set its access level on each module. Administrators always have full access. A change takes effect the next time the user opens a screen.">
    <x-dl::permission-matrix :objects="GroupPermissions::objects()" :groups="GroupPermissions::groups()" :perms="GroupPermissions::levels()" :default="0"
        :save-url="route('v2.security.permissions.save')" :source="Src::of('tm2sys.sec_group_permission', 'tm2sys.sec_module', 'tm2sys.sec_group')" />
</x-dl::card>
