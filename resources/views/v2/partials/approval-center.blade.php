{{--
 REMARK
 Tujuan       : Menampilkan Approval Center (hanya-baca, Level n of N, Approve / Reject / Hold) untuk satu jenis dokumen.
 Sumber hulu  : App\Support\V2\Data\ApprovalCenter (docs, lineColumns, myLog); log keputusan tm2mst.flow_log dan flow_config_step.
 Input        : $screen (pr, po, co, stock, prt, tf); flash v2_flash dan error validasi.
 Output       : x-dl::approval yang mengirim keputusan ke route v2.approval.decide.
 Dipakai oleh : @include dari screens/approval-pr, approval-po, approval-co, approval-stock, approval-prt, approval-tf.
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\ApprovalCenter;
    use App\Support\V2\Src;

    $title = collect(ApprovalCenter::SCREENS[$screen])->map(fn ($m) => t(ApprovalCenter::MODULES[$m]['title']))->implode(' & ');
@endphp
@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
<x-dl::approval :title="raw($title).' Waiting for My Approval'" :me="auth()->user()->name ?? auth()->user()->login"
    approve-label="Approve" :docs="ApprovalCenter::docs($screen)" :line-columns="ApprovalCenter::lineColumns($screen)"
    :log="ApprovalCenter::myLog($screen)" :action="route('v2.approval.decide', $screen)"
    :source="Src::of('tm2mst.flow_log', 'tm2mst.flow_config_step')" />
