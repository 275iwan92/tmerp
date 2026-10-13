{{--
 REMARK
 Tujuan       : Pusat approval CO: dokumen yang menunggu persetujuan user, diputuskan lewat Form Approval DevLib.
 Sumber hulu  : Partial v2.partials.approval-center memakai App\Support\V2\Data\ApprovalCenter (screen co) dan log tm2mst.flow_log.
 Input        : Parameter screen=co diteruskan ke partial.
 Output       : Hanya include partial approval-center (x-dl::approval; keputusan POST ke route v2.approval.decide).
 Dipakai oleh : Menu Approval > CO Approval Center (slug approval-co, route v2.screen).
 Status       : Aktif.
--}}
@include('v2.partials.approval-center', ['screen' => 'co'])
