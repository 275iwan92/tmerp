{{-- Warehouse > Task Board (v2.01, gambar docs/mockups/menu_v201_split.jpg): papan BACA-SAJA pekerjaan gudang gabungan -- tugas gudang (Receive / Putaway / Pick / Move / Count),
     pick list terbuka, transfer In Transit yang berjalan, dan area yang hitung stoknya jatuh tempo -- dalam kolom Open / In progress / Done (7 hari terakhir).
     Kartu tidak digeser atau diubah; tombol Buka di kartu pergi ke layar asalnya. Data: App\Support\V2\Data\WarehouseTaskBoard. Daftar tugas tetap di Receiving & Putaway. --}}
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
