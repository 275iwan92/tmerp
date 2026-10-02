{{-- Stock Transfer Approval Center: antrian transfer yang menunggu keputusan user login (modul flow TF). Fasilitas ini
     bawaan TIDAK aktif (user 2026-10-02: approval transfer di-skip dulu, bisa diaktifkan kapan saja di Setup > Setting Approval). --}}
@unless (\App\Support\V2\Data\StockTransfer::approvalActive())
    <x-dl::alert tone="info" message="Approval for Stock Transfer is switched off: Confirm moves the stock at once. Switch it on in Setup > Setting Approval (module TF) to make every transfer wait for approval.">
        <x-dl::button size="sm" variant="secondary" icon="fa-list-ol" label="Setting Approval" :href="route('v2.screen', 'approval-setting')" />
    </x-dl::alert>
@endunless
@include('v2.partials.approval-center', ['screen' => 'tf'])
