{{--
 REMARK
 Tujuan       : Pusat approval PR: dokumen yang menunggu persetujuan user, diputuskan lewat Form Approval DevLib.
 Sumber hulu  : Partial v2.partials.approval-center memakai App\Support\V2\Data\ApprovalCenter (screen pr) dan log tm2mst.flow_log.
 Input        : Parameter screen=pr diteruskan ke partial.
 Output       : Hanya include partial approval-center (x-dl::approval; keputusan POST ke route v2.approval.decide).
 Dipakai oleh : Menu Approval > PR Approval Center (slug approval-pr, route v2.screen).
 Status       : Aktif.
--}}
@include('v2.partials.approval-center', ['screen' => 'pr'])
