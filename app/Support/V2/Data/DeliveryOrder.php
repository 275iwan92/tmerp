<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\DoDelivery;
use App\Support\V2\Data\Concerns\FlowDocument;
use App\Support\V2\Lookups;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

// Delivery Order v2 = aturan & tulis data komponen v1 pages::dispatch.do.entry (updateHeader / onAddressPicked / pullSelected /
// updateLineQty / updateLinePrice / removeLine / confirmDo / markShipped / cancelDo -> DispatchService). Baris DO ditarik dari
// jadwal kirim CO (v_co_delivery_outstanding). Surat Jalan = DO CONFIRMED / SHIPPED tanpa harga (layar delivery-notes).
final class DeliveryOrder
{
    use FlowDocument;

    public const HEADER = ['do_date', 'scheduled_ship_date', 'ship_address_detail'];

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'CONFIRMED' => 'success', 'SHIPPED' => 'info', 'CANCELLED' => 'danger'];

    public static function component(int $id): Component
    {
        $c = app('livewire')->new('pages::dispatch.do.entry');
        $c->doId = $id;

        return $c;
    }

    public static function rows(array $statuses = []): array
    {
        $dos = DoDelivery::withCount(['lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')])->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->orderByDesc('id_delivery')->limit(500)->get();
        $cust = CustCustomer::whereIn('id_customer', $dos->pluck('customer_id')->filter()->unique())->pluck('customer_name', 'id_customer');

        return $dos->map(fn ($d) => ['id' => $d->id_delivery, 'no' => $d->do_no ?: '(draft #'.$d->id_delivery.')', 'customer' => $cust[$d->customer_id] ?? '—',
            'date' => $d->do_date?->toDateString(), 'ship' => $d->scheduled_ship_date?->toDateString(), 'shipped' => $d->actual_ship_date?->toDateString(),
            'address' => $d->ship_address_label, 'lines' => $d->lines_count, 'status' => $d->status])->all();
    }

    public static function customerOptions(): array
    {
        return CustCustomer::where('activate', true)->orderBy('customer_name')->pluck('customer_name', 'id_customer')->all();
    }

    public static function create(int $customerId): DoDelivery
    {
        $login = Auth::user()->login;

        return DoDelivery::create(['do_date' => now()->toDateString(), 'customer_id' => $customerId, 'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
    }

    // Jadwal kirim CO CONFIRMED milik customer ini yang masih tersisa (picker v1).
    public static function outstandingOptions(DoDelivery $do): array
    {
        return DB::connection('tm2sdi')->table('v_co_delivery_outstanding')->where('customer_id', $do->customer_id)->where('qty_available', '>', 0)
            ->where('co_status', 'CONFIRMED')->orderBy('co_no')->orderBy('item_description')->get()
            ->mapWithKeys(fn ($r) => [$r->co_delivery_id => $r->co_no.' · '.$r->item_description.' · sisa '.\Devlib\Locale::decimal($r->qty_available, 2).' '.$r->qty_unit
                .($r->ship_date ? ' · kirim '.\Devlib\Locale::d(\Illuminate\Support\Carbon::parse($r->ship_date)) : '')])->all();
    }

    // Header + alamat (lookup wilayah) + qty/harga baris + hapus baris. Balasan = pesan error DispatchService (kosong = OK).
    public static function save(DoDelivery $do, array $in): string
    {
        abort_unless($do->status === 'DRAFT', 422, 'DO sudah Confirm, tidak bisa diubah.');
        $c = self::component($do->id_delivery);
        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $in) && self::changed(self::str($in[$f]), $do->{$f})) {
                $c->updateHeader($f, self::str($in[$f]));
            }
        }
        if (array_key_exists('ship_area', $in)) {
            $cur = $do->ship_area_id ? $do->ship_area_level.':'.$do->ship_area_id : '';
            if ((string) $in['ship_area'] !== $cur) {
                $g = Lookups::geoFind((string) $in['ship_area']);
                $c->onAddressPicked('do-ship-address', $g['id'] ?? null, $g['level'] ?? null, $g['full_label'] ?? null, $g['postal_code'] ?? null);
            }
        }
        foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
            $c->removeLine((int) $id);
        }
        $lines = $do->lines()->get()->keyBy('id_line');
        $err = '';
        foreach ((array) ($in['lines'] ?? []) as $id => $row) {
            $line = $lines->get((int) $id);
            if (! $line) {
                continue;
            }
            if (array_key_exists('qty_do', (array) $row) && self::changed(self::num($row['qty_do']), $line->qty_do)) {
                $c->updateLineQty((int) $id, self::num($row['qty_do']));
                $err = $err ?: $c->pullMessage;
            }
            if (array_key_exists('unit_price', (array) $row) && self::changed(self::num($row['unit_price']), $line->unit_price)) {
                $c->updateLinePrice((int) $id, self::num($row['unit_price']));
                $err = $err ?: $c->pullMessage;
            }
        }

        return $err;
    }

    public static function pull(DoDelivery $do, array $coDeliveryIds): string
    {
        $c = self::component($do->id_delivery);
        $c->checkedDeliveryIds = array_map('intval', $coDeliveryIds);
        $c->pullSelected();

        return $c->pullMessage;
    }

    // confirm | ship | cancel -> [ok, pesan].
    public static function act(DoDelivery $do, string $action, string $reason = ''): array
    {
        $c = self::component($do->id_delivery);
        match ($action) {
            'confirm' => $c->confirmDo(),
            'ship' => $c->markShipped(),
            'cancel' => (function () use ($c, $reason) { $c->cancelReason = $reason; $c->cancelDo(); })(),
        };
        $fresh = DoDelivery::find($do->id_delivery);
        $want = ['confirm' => 'CONFIRMED', 'ship' => 'SHIPPED', 'cancel' => 'CANCELLED'][$action];

        return [$fresh?->status === $want, $c->pullMessage ?: 'Status tidak berubah.'];
    }

    // Draft > Confirmed > Closed (SHIPPED = barang sudah keluar).
    public static function formStatus(DoDelivery $do): string
    {
        return match ($do->status) { 'CONFIRMED' => 'confirmed', 'SHIPPED' => 'closed', 'CANCELLED' => 'cancelled', default => 'draft' };
    }
}
