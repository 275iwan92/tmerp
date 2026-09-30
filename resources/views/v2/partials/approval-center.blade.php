{{-- Approval Center v2 = DevLib Form Approval (x-dl::approval, disetujui user 2026-09-30): hanya-baca, tampil Level n of N,
     Approve/Reject final, Hold sementara, setiap keputusan tercatat di tm2mst.flow_log (siapa, kapan, alasan).
     Data & aturan = App\Support\V2\Data\ApprovalCenter ($screen = pr | po | co | stock | prt). --}}
@php
    use App\Support\V2\Data\ApprovalCenter;
    use App\Support\V2\Src;

    $title = collect(ApprovalCenter::SCREENS[$screen])->map(fn ($m) => ApprovalCenter::MODULES[$m]['title'])->implode(' & ');
@endphp
@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif
<x-dl::approval :title="$title.' Menunggu Approval Saya'" :me="auth()->user()->name ?? auth()->user()->login"
    approve-label="Setujui" :docs="ApprovalCenter::docs($screen)" :line-columns="ApprovalCenter::lineColumns($screen)"
    :log="ApprovalCenter::myLog($screen)" :action="route('v2.approval.decide', $screen)"
    :source="Src::of('tm2mst.flow_log', 'tm2mst.flow_config_step')" />
