{{--
 REMARK
 Tujuan       : Antrian approval Stock Transfer; memberi tahu bila approval transfer sedang dimatikan.
 Sumber hulu  : App\Support\V2\Data\StockTransfer::approvalActive() dan partial v2.partials.approval-center (ApprovalCenter layar tf).
 Input        : -
 Output       : x-dl::alert info + tombol ke Setting Approval bila nonaktif; lalu partial approval-center (x-dl::approval).
 Dipakai oleh : Rute v2.screen dengan slug 'approval-tf' (menu v2:approval-tf di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (approval transfer bawaan nonaktif sejak 2026-10-02, bisa dinyalakan di Setting Approval modul TF).
--}}
@unless (\App\Support\V2\Data\StockTransfer::approvalActive())
    <x-dl::alert tone="info" message="Approval for Stock Transfer is switched off: Confirm moves the stock at once. Switch it on in Setup > Setting Approval (module TF) to make every transfer wait for approval.">
        <x-dl::button size="sm" variant="secondary" icon="fa-list-ol" label="Setting Approval" :href="route('v2.screen', 'approval-setting')" />
    </x-dl::alert>
@endunless
@include('v2.partials.approval-center', ['screen' => 'tf'])
