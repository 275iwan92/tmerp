{{--
 REMARK
 Tujuan       : Receiving & Putaway: tugas putaway palet dari area Receiving / QC Hold ke bin usulan, termasuk Send to line untuk palet Conditional / URGENT.
 Sumber hulu  : App\Support\V2\Data\ReceivingPutaway; App\Services\Inventory\WarehouseTaskService (sync tugas); grid ReceivingPutawayGrid.
 Input        : Query task (tugas terbuka); user login (sync tugas); session v2_flash dan errors.
 Output       : Daftar tugas dengan kotak statistik, panel pindai palet dan bin; POST ke v2.wh.task.confirm.
 Dipakai oleh : route v2.screen (routes/v2.php), slug wh-receiving-putaway (menu Warehouse); ditautkan dari Task Board.
 Status       : Aktif.
--}}
@php
    use App\Services\Inventory\WarehouseTaskService;
    use App\Support\V2\Data\ReceivingPutaway;
    use App\Support\V2\Grid\Inv\ReceivingPutawayGrid;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'wh-receiving-putaway');
    (new WarehouseTaskService)->sync(auth()->user()->login);
    $task = request('task') ? ReceivingPutaway::find((int) request('task')) : null;
    abort_if(request('task') && ! $task, 404);
    $panel = $task ? ReceivingPutaway::panel($task) : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($panel)
    @php
        $pallet = $panel['pallet'];
        $open = $task->isOpen() && in_array($panel['state'], [WarehouseTaskService::READY, WarehouseTaskService::TO_LINE], true);
        $toLine = $panel['state'] === WarehouseTaskService::TO_LINE;
    @endphp
    <x-dl::card :title="'Selected · '.raw(($pallet?->pallet_no ?? '—').' · '.$task->item_name.' · '.($panel['batch']?->batch_no ?? '—'))"
        :description="$toLine ? 'Dock to line: scan the pallet, then the bin in the production floor or staging area.' : 'Scan the pallet, then the bin. A bin other than the suggested one needs a reason.'">
        @if (! $open)
            <x-dl::alert tone="info" :message="$task->status === 'DONE' ? 'This task is done.' : match ($panel['state']) {
                WarehouseTaskService::WAITING => 'The batch is still waiting for the sampling decision, so the pallet cannot be put away yet.',
                WarehouseTaskService::BLOCKED => 'The batch is Rejected or Blocked and is not available.',
                default => 'This task is closed.',
            }" />
        @else
            <x-dl::table :columns="['n' => ['#', 'num' => true, 'raw' => true], 'bin' => 'Bin', 'area' => 'Area', 'free' => ['Free (kg)', 'num' => true, 'raw' => true], 'why' => 'Why']"
                :rows="$panel['suggestions']" empty="No suitable bin was found. Scan a bin and give the reason." :source="Src::of('tm2mst.wh_loc', 'tm2mst.wh_zone')" />
            <x-dl::action-form action="{{ route('v2.wh.task.confirm', $task->id_task) }}">
                <x-dl::form.row :cols="3">
                    <x-dl::field.text name="scan_pallet" label="Scan pallet" required :case-toggle="false" :value="old('scan_pallet')" :source="Src::of('tm2inv.inv_pallet.pallet_no')" />
                    <x-dl::field.text name="scan_bin" label="Scan bin" required :case-toggle="false" :value="old('scan_bin')" :source="Src::of('tm2mst.wh_loc.location_code')" />
                    <x-dl::field.text name="reason" label="Reason (needed when the bin is not the suggested one)" :value="old('reason')" :source="Src::of('tm2inv.wh_task.reason')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button preset="confirm" type="submit" />
                </x-dl::action-bar>
            </x-dl::action-form>
        @endif
        @unless ($open)
            <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
        @endunless
    </x-dl::card>
@else
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new ReceivingPutawayGrid]])
    <x-dl::card title="How the bin is suggested" description="Engine suggests, officer confirms.">
        <x-dl::table :columns="['n' => ['#', 'num' => true, 'raw' => true], 'rule' => 'Rule']" :rows="ReceivingPutaway::rules()" :source="Src::of('tm2mst.wh_zone', 'tm2mst.wh_loc', 'tm2inv.inv_batch')" />
    </x-dl::card>
@endif
