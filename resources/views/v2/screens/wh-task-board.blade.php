{{--
 REMARK
 Tujuan       : Task Board: papan baca-saja pekerjaan gudang gabungan (Receive, Putaway, Pick, Move, Count, In Transit) dalam kolom Open / In progress / Done.
 Sumber hulu  : App\Support\V2\Data\WarehouseTaskBoard (tm2inv.wh_task, wh_pick_list, inv_transfer; tm2mst.wh_zone).
 Input        : Tidak ada parameter.
 Output       : x-dl::kanban readonly; tombol Buka di kartu ke layar asal; tombol ke Receiving & Putaway.
 Dipakai oleh : route v2.screen (routes/v2.php), slug wh-task-board (menu Warehouse).
 Status       : Aktif (baca-saja).
--}}
@php
    use App\Support\V2\Data\WarehouseTaskBoard;
    use App\Support\V2\Src;

    $board = WarehouseTaskBoard::board();
@endphp

<x-dl::card title="Task Board" description="All open warehouse work in one place. Open a card to work on it in its own screen; the board only reads.">
    <x-slot:actions>
        <x-dl::button size="sm" variant="secondary" icon="fa-dolly" label="Receiving & Putaway" :href="route('v2.screen', 'wh-receiving-putaway')" />
    </x-slot:actions>
    <x-dl::kanban :columns="$board['columns']" :cards="$board['cards']" :add-column="false" :add-card="false" :readonly="true"
        :source="Src::of('tm2inv.wh_task', 'tm2inv.wh_pick_list', 'tm2inv.inv_transfer', 'tm2mst.wh_zone')" />
</x-dl::card>
