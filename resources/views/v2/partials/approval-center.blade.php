{{-- Approval Center v2 = DevLib Form Approval (x-dl::approval, disetujui user 2026-09-30): hanya-baca, tampil Level n of N,
     Approve/Reject final, Hold sementara, setiap keputusan tercatat di tm2mst.flow_log (siapa, kapan, alasan).
     Data & aturan = App\Support\V2\Data\ApprovalCenter ($module = PR | PO | CO). --}}
@php
    use App\Support\V2\Data\ApprovalCenter;
    use App\Support\V2\Src;

    $meta = ApprovalCenter::MODULES[$module];
@endphp
@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif
<x-dl::approval :title="$meta['title'].' Menunggu Approval Saya'" :me="auth()->user()->name ?? auth()->user()->login"
    approve-label="Setujui" :docs="ApprovalCenter::docs($module)" :line-columns="ApprovalCenter::lineColumns($module)"
    :log="ApprovalCenter::myLog($module)" :action="route('v2.approval.decide', $module)"
    :source="Src::of('tm2mst.flow_log', 'tm2mst.flow_config_step')" />
