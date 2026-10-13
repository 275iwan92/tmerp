{{--
 REMARK
 Tujuan       : Antrian approval stok menunggu keputusan user login.
 Sumber hulu  : Partial v2.partials.approval-center dengan App\Support\V2\Data\ApprovalCenter (layar stock); log tm2mst.flow_log.
 Input        : -
 Output       : Menyerahkan ke partial approval-center (x-dl::approval, Approve / Reject / Hold) yang posting ke v2.approval.decide.
 Dipakai oleh : Rute v2.screen dengan slug 'approval-stock' (menu v2:approval-stock di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (pembungkus satu baris).
--}}
@include('v2.partials.approval-center', ['screen' => 'stock'])
