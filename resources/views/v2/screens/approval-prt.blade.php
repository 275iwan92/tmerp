{{--
 REMARK
 Tujuan       : Pusat approval Purchase Return: dokumen yang menunggu persetujuan user, diputuskan lewat Form Approval DevLib.
 Sumber hulu  : Partial v2.partials.approval-center memakai App\Support\V2\Data\ApprovalCenter (screen prt) dan log tm2mst.flow_log.
 Input        : Parameter screen=prt diteruskan ke partial.
 Output       : Hanya include partial approval-center (x-dl::approval; keputusan POST ke route v2.approval.decide).
 Dipakai oleh : Menu Approval > Purchase Return Approval Center (slug approval-prt, route v2.screen).
 Status       : Aktif.
--}}
@include('v2.partials.approval-center', ['screen' => 'prt'])
