{{--
 REMARK
 Tujuan       : Count Tasks: hitung stok per area; Start Count membuat tugas per posisi stok dan mengunci bin, hasil hitung menjadi Stock Opname.
 Sumber hulu  : App\Support\V2\Data\CountTasks; App\Services\Inventory\CountTaskService; grid CountTaskGrid; tm2inv.wh_task, kartu stok.
 Input        : Query id (tugas); session v2_flash dan errors.
 Output       : Daftar via grid, form isi qty hasil hitung; POST ke v2.wh.count.start, .confirm, .cancel; x-dl::card, x-dl::action-form.
 Dipakai oleh : route v2.screen (routes/v2.php), slug wh-count-tasks (menu Warehouse).
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\CountTasks;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'wh-count-tasks');
    $task = request('id') ? CountTasks::find((int) request('id')) : null;
    abort_if(request('id') && ! $task, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($task)
    <x-dl::action-form action="{{ route('v2.wh.count.confirm', $task->id_task) }}">
        <x-dl::card :title="'Count · '.raw($task->task_no.' · '.$task->item_name)"
            :description="raw(t('System quantity').' '.Locale::decimal((float) $task->system_qty, 2).' '.$task->uom_code)" icon="fa-solid fa-clipboard-list">
            @if ($task->isOpen())
                <x-dl::form.row :cols="3">
                    <x-dl::field.decimal name="counted_qty" label="Counted Quantity" required :value="old('counted_qty')" :source="Src::of('tm2inv.wh_task.counted_qty')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button variant="secondary" icon="fa-ban" label="Cancel" type="submit" formaction="{{ route('v2.wh.count.cancel', $task->id_task) }}" formnovalidate />
                    <x-dl::button preset="confirm" type="submit" />
                </x-dl::action-bar>
            @else
                <x-dl::form.row :cols="3">
                    <x-dl::field.display label="Counted Quantity" :value="$task->counted_qty === null ? '—' : raw(Locale::decimal((float) $task->counted_qty, 2))" :source="Src::of('tm2inv.wh_task.counted_qty')" />
                    <x-dl::field.display label="Stock Opname" :value="$task->adjustment_doc_no ?: '—'" :source="Src::of('tm2inv.wh_task.adjustment_doc_no')" />
                    <x-dl::field.display label="Counted By" :value="$task->confirmed_by ?: '—'" :source="Src::of('tm2inv.wh_task.confirmed_by')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
            @endif
        </x-dl::card>
    </x-dl::action-form>
@else
    <x-dl::card title="Start Counting" description="Choose an area. A count task is made for every item in its bins and the bins are locked until they are counted. Areas marked due have passed their count interval.">
        <x-dl::action-form action="{{ route('v2.wh.count.start') }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="zone_id" label="Area" required :options="CountTasks::zoneOptions()" :source="Src::of('tm2mst.wh_zone')" />
            </x-dl::form.row>
            <x-dl::action-bar align="start">
                <x-dl::button icon="fa-play" label="Start Count" type="submit" />
                <x-dl::button variant="secondary" icon="fa-calendar-check" label="Start Due Areas" type="submit" formaction="{{ route('v2.wh.count.start-due') }}" formnovalidate />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>
    @include('v2.screens._dbgrid', ['screen' => $screen])
@endif
