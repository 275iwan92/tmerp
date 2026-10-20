{{--
 REMARK
 Tujuan       : Notification Center: riwayat lengkap notifikasi user login dengan cari, saringan tipe / status dan tombol Mark all read.
 Sumber hulu  : App\Support\V2\Grid\Personal\NotificationGrid (sys_notifications); pindahan layar v1 pages::notification.index.
 Input        : Tidak ada (grid memuat sendiri).
 Output       : x-dl::action-form (POST v2.notifications.read-all) dan grid x-dl::grid lewat v2.screens._dbgrid.
 Dipakai oleh : Rute v2.notifications (layar penuh seperti Help dan Edit Profile); panel notifikasi Layer Menu menautkan ke sini.
 Status       : Aktif
--}}
@php $grid = new \App\Support\V2\Grid\Personal\NotificationGrid; @endphp
<x-dl::card>
    <x-dl::action-form action="{{ route('v2.notifications.read-all') }}">
        <x-dl::action-bar align="start">
            <x-dl::button variant="secondary" icon="fa-check-double" label="Mark all read" type="submit" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>
@include('v2.screens._dbgrid', ['screen' => ['grid' => $grid]])
