{{-- Warehouse > Receiving & Putaway (v2.01, docs/mockups/wh_v201_receiving_putaway.jpg): daftar tugas putaway = objek Grid
     App\Support\V2\Grid\Inv\ReceivingPutawayGrid (kotak statistik = saringan). Open satu baris (?task=) -> usulan bin berurutan dari aturan
     area + pindai palet, lalu pindai bin (bin lain dari usulan wajib alasan) -> stok pindah lewat dokumen Stock Transfer asli
     (App\Services\Inventory\WarehouseTaskService). Palet Conditional / URGENT = "Send to line" (dock-to-line ke area WIP / Staging).
     Tugas dibuat otomatis (sync) dari palet yang masih di area Receiving / QC Hold. --}}
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
