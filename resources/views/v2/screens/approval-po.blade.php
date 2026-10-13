{{--
 REMARK
 Tujuan       : Pusat approval PO: dokumen yang menunggu persetujuan user, diputuskan lewat Form Approval DevLib.
 Sumber hulu  : Partial v2.partials.approval-center memakai App\Support\V2\Data\ApprovalCenter (screen po) dan log tm2mst.flow_log.
 Input        : Parameter screen=po diteruskan ke partial.
 Output       : Hanya include partial approval-center (x-dl::approval; keputusan POST ke route v2.approval.decide).
 Dipakai oleh : Menu Approval > PO Approval Center (slug approval-po, route v2.screen).
 Status       : Aktif.
--}}
@include('v2.partials.approval-center', ['screen' => 'po'])
