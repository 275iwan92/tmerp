<?php

/*
 * REMARK
 * Tujuan       : Data layar Delivery Order / Surat Jalan v2: daftar, pilihan, simpan header + baris, tarik jadwal kirim CO, confirm / ship / cancel.
 * Sumber hulu  : do_delivery, do_line, v_co_delivery_outstanding, co_order_delivery; aturan tulis = DeliveryOrderEntryService -> DispatchService.
 * Input        : DoDelivery + kiriman form v2 (header, alamat, qty / harga baris, id jadwal kirim, alasan batal).
 * Output       : Pesan error (kosong = OK) dan status DO; data ditulis oleh service.
 * Dipakai oleh : routes/v2.php (do.*), layar delivery-order dan delivery-notes, grid DeliveryOrderListGrid / DeliveryNoteListGrid, ProofOfDelivery, StageBuilder.
 * Status       : Aktif (T10 tahap 7c, 2026-10-22: komponen v1 pages::dispatch.do.entry tidak dipanggil lagi).
 */

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\DoDelivery;
use App\Services\Dispatch\DeliveryOrderEntryService as Svc;
use App\Support\V2\Data\Concerns\FlowDocument;
use App\Support\V2\Lookups;
use Devlib\Locale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Delivery Order v2. Aturan & tulis data = App\Services\Dispatch\DeliveryOrderEntryService (updateHeader / onAddressPicked / pullSelected /
// updateLineQty / updateLinePrice / removeLine / confirmDo / markShipped / cancelDo -> DispatchService). Baris DO ditarik dari
// jadwal kirim CO (v_co_delivery_outstanding). Surat Jalan = DO CONFIRMED / SHIPPED tanpa harga (layar delivery-notes).
final class DeliveryOrder
{
    use FlowDocument;

    public const HEADER = Svc::HEADER_FIELDS;

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'CONFIRMED' => 'success', 'SHIPPED' => 'info', 'CANCELLED' => 'danger'];

    public static function rows(array $statuses = []): array
    {
        // Surat jalan subkon (do_type SUBCON) punya layarnya sendiri (subcon-delivery-note), tidak ikut daftar DO / Surat Jalan / POD.
        $dos = DoDelivery::with(['lines', 'lines.order:id_order,co_no'])->where('do_type', '<>', 'SUBCON')->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->orderByDesc('id_delivery')->limit(500)->get();
        $cust = CustCustomer::whereIn('id_customer', $dos->pluck('customer_id')->filter()->unique())->pluck('customer_name', 'id_customer');

        return $dos->map(fn ($d) => ['id' => $d->id_delivery, 'no' => $d->do_no ?: '(draft #'.$d->id_delivery.')', 'customer' => $cust[$d->customer_id] ?? '—',
            'date' => $d->do_date?->toDateString(), 'ship' => $d->scheduled_ship_date?->toDateString(), 'shipped' => $d->actual_ship_date?->toDateString(),
            'address' => $d->ship_address_label, 'lines' => $d->lines->where('status', '<>', 'CANCELLED')->count(), 'items' => self::lineRows($d), 'status' => $d->status])->all();
    }

    // Baris anak grid (Expand) DO / Surat Jalan / POD: nama product selalu tampil, tanpa harga. Butuh relasi lines.order.
    public static function lineRows(DoDelivery $d): array
    {
        return $d->lines->where('status', '<>', 'CANCELLED')->map(fn ($l) => ['product' => $l->item_description, 'co' => $l->order?->co_no,
            'qty' => (float) $l->qty_do, 'unit' => $l->qty_unit])->values()->all();
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

    // Jadwal kirim CO CONFIRMED milik customer ini yang masih tersisa (picker v1) DAN sudah di-Post to Production (aturan A.3).
    public static function outstandingOptions(DoDelivery $do): array
    {
        return DB::connection('tm2sdi')->table('v_co_delivery_outstanding')->where('customer_id', $do->customer_id)->where('qty_available', '>', 0)
            ->where('co_status', 'CONFIRMED')->whereIn('co_delivery_id', self::postedIds())->orderBy('co_no')->orderBy('item_description')->get()
            ->mapWithKeys(fn ($r) => [$r->co_delivery_id => $r->co_no.' · '.$r->item_description.' · '.tf('remaining :qty', ['qty' => Locale::decimal($r->qty_available, 2).' '.$r->qty_unit])
                .($r->ship_date ? ' · '.tf('ship :date', ['date' => Locale::d(Carbon::parse($r->ship_date))]) : '')])->all();
    }

    // Header + alamat (lookup wilayah) + qty/harga baris + hapus baris. Balasan = pesan error DispatchService (kosong = OK).
    public static function save(DoDelivery $do, array $in): string
    {
        abort_unless($do->status === 'DRAFT', 422, t('The DO is already confirmed and cannot be changed.'));
        $doId = $do->id_delivery;
        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $in) && self::changed(self::str($in[$f]), $do->{$f})) {
                Svc::updateHeader($doId, $f, self::str($in[$f]));
            }
        }
        if (array_key_exists('ship_area', $in)) {
            $cur = $do->ship_area_id ? $do->ship_area_level.':'.$do->ship_area_id : '';
            if ((string) $in['ship_area'] !== $cur) {
                $g = Lookups::geoFind((string) $in['ship_area']);
                Svc::onAddressPicked($doId, 'do-ship-address', $g['id'] ?? null, $g['level'] ?? null, $g['full_label'] ?? null, $g['postal_code'] ?? null);
            }
        }
        foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
            Svc::removeLine($doId, (int) $id);
        }
        $lines = $do->lines()->get()->keyBy('id_line');
        $err = '';
        foreach ((array) ($in['lines'] ?? []) as $id => $row) {
            $line = $lines->get((int) $id);
            if (! $line) {
                continue;
            }
            if (array_key_exists('qty_do', (array) $row) && self::changed(self::num($row['qty_do']), $line->qty_do)) {
                $message = Svc::updateLineQty($doId, (int) $id, self::num($row['qty_do']));
                $err = $err ?: $message;
            }
            if (array_key_exists('unit_price', (array) $row) && self::changed(self::num($row['unit_price']), $line->unit_price)) {
                $message = Svc::updateLinePrice($doId, (int) $id, self::num($row['unit_price']));
                $err = $err ?: $message;
            }
        }

        return $err;
    }

    // Subquery id jadwal kirim yang sudah di-Post to Production.
    private static function postedIds(): \Closure
    {
        return fn ($q) => $q->from('co_order_delivery')->select('id_delivery')->whereNotNull('posted_at');
    }

    public static function pull(DoDelivery $do, array $coDeliveryIds): string
    {
        $notPosted = DB::connection('tm2sdi')->table('co_order_delivery')->whereIn('id_delivery', array_map('intval', $coDeliveryIds))->whereNull('posted_at')->count();
        if ($notPosted) {
            return t('The chosen delivery schedule is not Posted to Production yet in Customer Plan Delivery.');
        }

        return Svc::pullSelected($do->id_delivery, array_map('intval', $coDeliveryIds));
    }

    // confirm | ship | cancel -> [ok, pesan].
    public static function act(DoDelivery $do, string $action, string $reason = ''): array
    {
        $message = '';
        match ($action) {
            'confirm' => $message = Svc::confirmDo($do->id_delivery),
            'ship' => Svc::markShipped($do->id_delivery),
            'cancel' => $message = Svc::cancelDo($do->id_delivery, $reason),
        };
        $fresh = DoDelivery::find($do->id_delivery);
        $want = ['confirm' => 'CONFIRMED', 'ship' => 'SHIPPED', 'cancel' => 'CANCELLED'][$action];

        return [$fresh?->status === $want, $message ?: t('The status did not change.')];
    }

    // Draft > Confirmed > Closed (SHIPPED = barang sudah keluar).
    public static function formStatus(DoDelivery $do): string
    {
        return match ($do->status) {
            'CONFIRMED' => 'confirmed', 'SHIPPED' => 'closed', 'CANCELLED' => 'cancelled', default => 'draft'
        };
    }
}
