{{-- Customer Plan Delivery v2. Flow & aturan = tmerp pages/co/delivery-plan.blade.php (lewat App\Support\V2\Data\Cpdel).
     Alur layar: GRID jadwal kirim semua CO CONFIRMED (baris awal dibuat engine saat CO Confirm) -> klik 2x baris ->
     Pindah tanggal / Split (pecah qty ke tanggal lain) / Gabung dengan baris lain CO + product yang sama (ke tanggal paling awal).
     Tidak ada Confirm di sini: baris menjadi SHIPPED saat Delivery Order terbit. --}}
@php
    use App\Models\Sdi\CoOrderDelivery;
    use App\Support\V2\Data\Cpdel;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'cpdel');
    $d = request('id') ? Cpdel::query()->find((int) request('id')) : null;
    abort_if(request('id') && ! $d, 404);
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp

<x-mp::sales.flow-steps current="shipping" note="Shipping schedule per confirmed order. Split or join lines when the field situation changes; Delivery Order marks them SHIPPED." />

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $d)
    <x-dl::grid id="cpdel-list" :rows="Cpdel::rows()" search="Cari no. CO, customer, product..." actions="worklist" :active-key="null" :new-record="null"
        :open-url="$home.'?id={id}'" group-by="co"
        source="{{ Src::of('tm2sdi.co_order_delivery', 'tm2sdi.co_order (CONFIRMED)', 'tm2sdi.co_order_item.due_date') }}"
        :columns="[
            ['key' => 'item', 'label' => 'Product', 'type' => 'identity', 'code' => 'co'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'ship_date', 'label' => 'Tanggal kirim', 'type' => 'date'],
            ['key' => 'qty', 'label' => 'Qty kirim', 'type' => 'number', 'sum' => true],
            ['key' => 'type', 'label' => 'Jenis'],
            ['key' => 'due', 'label' => 'Due date', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Cpdel::STATUS_TONES],
            ['key' => 'co', 'label' => 'No. CO', 'visible' => false],
        ]"
        :stats="[
            ['label' => 'Semua'],
            ['label' => 'Terjadwal', 'where' => ['status' => ['PLANNED', 'TERLAMBAT']], 'tone' => 'warn'],
            ['label' => 'Terlambat', 'where' => ['status' => 'TERLAMBAT'], 'tone' => 'danger'],
            ['label' => 'Terkirim', 'where' => ['status' => 'SHIPPED'], 'tone' => 'success'],
        ]"
        :filters="['customer' => 'Customer', 'status' => 'Status']" />
@else
    @php
        $late = Cpdel::late($d);
        $siblings = Cpdel::siblings($d);
        $planned = $d->status === 'PLANNED';
    @endphp
    <x-dl::card>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
            <span class="mp-chips">
                <x-dl::badge :label="$d->order?->co_no" />
                <x-dl::badge :label="$late ? 'TERLAMBAT' : $d->status" :tone="Cpdel::STATUS_TONES[$late ? 'TERLAMBAT' : $d->status] ?? 'neutral'" />
            </span>
            <x-dl::button variant="secondary" icon="send" label="Buka Customer Order" :href="route('v2.screen', 'customer-order').'?id='.$d->order_id" />
        </x-dl::action-bar>
        <h2 class="mp-bigname">{{ $d->item?->description }}</h2>
        <x-dl::form.row cols="4">
            <x-dl::field.display label="Tanggal kirim" :value="$d->ship_date?->format('d-M-Y') ?? '—'" :source="Src::of('tm2sdi.co_order_delivery.ship_date')" />
            <x-dl::field.display label="Qty kirim" :value="$num($d->ship_qty).' '.($d->item?->qty_unit ?? '')" :source="Src::of('tm2sdi.co_order_delivery.ship_qty')" />
            <x-dl::field.display label="Due date (janji ke customer)" :value="$d->item?->due_date?->format('d-M-Y') ?? '—'" :source="Src::of('tm2sdi.co_order_item.due_date')" />
            <x-dl::field.display label="Qty order" :value="$num($d->item?->qty).' '.($d->item?->qty_unit ?? '')" :source="Src::of('tm2sdi.co_order_item.qty')" />
        </x-dl::form.row>
        @if ($late)<x-dl::alert tone="danger" message="Tanggal kirim melewati due date atau due date sudah lewat." />@endif
    </x-dl::card>

    @if ($planned)
        <form method="post" action="{{ route('v2.cpdel.split', $d->id_delivery) }}" data-dl-guard>
            @csrf
            <x-dl::card title="Pindah tanggal / Split" description="Qty kosong = pindahkan seluruh baris ke tanggal baru. Qty diisi = qty itu dipecah jadi baris baru di tanggal baru, sisanya tetap.">
                <x-dl::form.row cols="2">
                    <x-dl::field.date name="ship_date" label="Tanggal kirim baru" required :value="old('ship_date', $d->ship_date?->toDateString())" :source="Src::of('tm2sdi.co_order_delivery.ship_date')" />
                    <x-dl::field.decimal name="split_qty" label="Qty dipecah (opsional)" :decimals="0" :hint="'Kurang dari '.$num($d->ship_qty)" :source="Src::of('tm2sdi.co_order_delivery.ship_qty', 'split_from_id')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
            </x-dl::card>
        </form>

        <form method="post" action="{{ route('v2.cpdel.merge') }}">
            @csrf
            <input type="hidden" name="ids[]" value="{{ $d->id_delivery }}">
            <x-dl::card title="Gabung" description="Satukan dengan baris lain dari CO & product yang sama; hasilnya di tanggal paling awal.">
                @if ($siblings->isEmpty())
                    <x-dl::empty-state title="Tidak ada baris lain untuk digabung" />
                @else
                    <x-dl::field.checkboxes name="ids" label="Gabungkan dengan" :options="$siblings->mapWithKeys(fn ($s) => [$s->id_delivery => ($s->ship_date?->format('d-M-Y') ?? '—').' · '.$num($s->ship_qty).' '.($d->item?->qty_unit ?? '')])->all()" :source="Src::of('tm2sdi.co_order_delivery')" />
                    <x-dl::action-bar><x-dl::button preset="apply" label="Gabung" type="submit" /></x-dl::action-bar>
                @endif
            </x-dl::card>
        </form>
    @endif

    <x-dl::card title="Semua jadwal kirim product ini">
        <x-dl::table :columns="['date' => 'Tanggal kirim', 'qty' => 'Qty', 'type' => 'Jenis', 'status' => 'Status', 'from' => 'Pecahan dari']"
            :rows="CoOrderDelivery::where('order_id', $d->order_id)->where('item_id', $d->item_id)->orderBy('ship_date')->get()->map(fn ($s) => ['date' => $s->ship_date?->format('d-M-Y') ?? '—',
                'qty' => $num($s->ship_qty), 'type' => $s->shipment_type, 'status' => $s->status.($s->id_delivery === $d->id_delivery ? ' (baris ini)' : ''), 'from' => $s->split_from_id ? '#'.$s->split_from_id : '—'])->all()"
            source="{{ Src::of('tm2sdi.co_order_delivery') }}" />
    </x-dl::card>
@endif
