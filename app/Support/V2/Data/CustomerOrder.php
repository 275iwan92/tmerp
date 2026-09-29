<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\QuoQuotation;
use App\Services\Sales\CoOrderBuilder;
use App\Support\V2\Lookups;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

// Layar Customer Order v2. Aturan & tulis = komponen v1 pages::co.entry yang SAMA (updateHeader/updateItem/addDelivery/
// confirmCo/...), dipanggil per field. 2 jalur masuk (CLAUDE.md §4): INTEGRATED dari Quotation terbit, INSTANT = pilih customer,
// baris PRODUCT wajib Master Product. Confirm -> engine CPDEL (CoOrderBuilder::confirm). Tambahan v2: Hapus draft INSTANT.
final class CustomerOrder
{
    public const STATUS_TONES = ['DRAFT' => 'neutral', 'CONFIRMED' => 'success'];

    public const MODE_TONES = ['INTEGRATED' => 'info', 'INSTANT' => 'violet'];

    public const HEADER = ['co_date', 'customer_po_no', 'ship_address_detail', 'discount_qty_pct', 'notes', 'sales_person_id', 'payment_term', 'is_ppn'];

    public static function component(int $id): Component
    {
        $c = app('livewire')->new('pages::co.entry');
        $c->orderId = $id;

        return $c;
    }

    public static function rows(): array
    {
        $orders = CoOrder::with(['items.deliveries', 'externalCosts', 'quotation:id_quotation,quotation_no'])->orderByDesc('id_order')->limit(500)->get();
        $customers = CustCustomer::whereIn('id_customer', $orders->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');

        return $orders->map(fn (CoOrder $o) => [
            'id' => $o->id_order,
            'no' => $o->co_no,
            'customer' => $customers[$o->customer_id] ?? '—',
            'date' => $o->co_date?->toDateString(),
            'mode' => $o->entry_mode,
            'quotation' => $o->quotation?->quotation_no,
            'po' => $o->customer_po_no,
            'lines' => $o->items->count(),
            'due' => $o->items->where('line_kind', 'PRODUCT')->min('due_date')?->toDateString(),
            'total' => $o->totals()['total_billing'],
            'status' => $o->doc_status,
        ])->all();
    }

    // Quotation terbit yang belum punya CO (1 CO = 1 Quotation) dan belum ditutup LOST.
    public static function quotationChoices(): array
    {
        $taken = CoOrder::whereNotNull('quotation_id')->pluck('quotation_id');
        $quotes = QuoQuotation::with('items.inquiry:id_inquiry,sub_state')->where('doc_status', 'PUBLISHED')->whereNotIn('id_quotation', $taken)
            ->orderByDesc('id_quotation')->limit(200)->get()->filter(fn ($q) => $q->outcome() !== 'LOST');
        $customers = CustCustomer::whereIn('id_customer', $quotes->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');

        return $quotes->map(fn ($q) => ['id' => $q->id_quotation, 'no' => $q->quotation_no, 'customer' => $customers[$q->customer_id] ?? '—',
            'date' => $q->issued_date?->toDateString(), 'lines' => $q->items->count(), 'total' => $q->totals()['grand']])->values()->all();
    }

    public static function customerOptions(): array
    {
        return CustCustomer::where('activate', true)->orderBy('customer_name')->get(['id_customer', 'customer_name', 'customer_code'])
            ->mapWithKeys(fn ($c) => [$c->id_customer => $c->customer_name.($c->customer_code ? ' ('.$c->customer_code.')' : '')])->all();
    }

    public static function createIntegrated(int $quotationId): ?CoOrder
    {
        return CoOrderBuilder::findOrCreateFromQuotation(QuoQuotation::findOrFail($quotationId));
    }

    public static function createInstant(int $customerId): CoOrder
    {
        return CoOrderBuilder::createInstant(CustCustomer::findOrFail($customerId));
    }

    // Header, baris, jadwal kirim, biaya luar dari satu form. Hanya nilai yang berubah yang dikirim ke komponen v1.
    public static function save(CoOrder $o, array $in): void
    {
        abort_unless($o->doc_status === 'DRAFT', 422, 'Customer Order sudah Confirm, tidak bisa diubah.');
        $c = self::component($o->id_order);

        foreach (self::HEADER as $f) {
            if (! array_key_exists($f, $in)) {
                continue;
            }
            $v = $f === 'discount_qty_pct' ? self::num($in[$f]) : self::str($in[$f]);
            $cur = $f === 'is_ppn' ? ($o->is_ppn ? '1' : '0') : self::str($o->{$f});
            if ($v !== $cur && ! (is_numeric($v) && is_numeric($cur) && (float) $v === (float) $cur)) {
                $c->updateHeader($f, $v);
            }
        }

        // Shipment Location = picker wilayah v1 (onAddressPicked): nilai lookup "LEVEL:id".
        if (array_key_exists('ship_area', $in)) {
            $cur = $o->ship_area_id ? $o->ship_area_level.':'.$o->ship_area_id : '';
            if ((string) $in['ship_area'] !== $cur) {
                $g = Lookups::geoFind((string) $in['ship_area']);
                $c->onAddressPicked('co-ship-address', $g['id'] ?? null, $g['level'] ?? null, $g['full_label'] ?? null, $g['postal_code'] ?? null);
            }
        }

        foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
            $c->removeItem((int) $id);
        }
        foreach (array_keys((array) ($in['remove_delivery'] ?? [])) as $id) {
            $c->removeDelivery((int) $id);
        }
        foreach (array_keys((array) ($in['remove_cost'] ?? [])) as $id) {
            $c->removeExternalCost((int) $id);
        }

        $o->load(['items.deliveries', 'externalCosts']);
        // Ganti Master Product baris PRODUCT = pickProduct v1 (baris snapshot quotation hanya ditautkan, harga tetap).
        foreach ((array) ($in['items'] ?? []) as $id => $row) {
            $item = $o->items->firstWhere('id_item', (int) $id);
            if ($item && $item->line_kind === 'PRODUCT' && ! empty($row['product_id']) && (int) $row['product_id'] !== (int) $item->product_id) {
                $c->openProductPicker((int) $id);
                $c->pickProduct((int) $row['product_id']);
            }
        }
        $o->load(['items.deliveries', 'externalCosts']);
        self::diff($o->items->keyBy('id_item'), (array) ($in['items'] ?? []), ['description', 'qty', 'qty_unit', 'unit_price', 'discount_pct', 'due_date'],
            ['qty', 'unit_price', 'discount_pct'], fn ($id, $f, $v) => $c->updateItem($id, $f, $v));
        self::diff($o->items->flatMap->deliveries->keyBy('id_delivery'), (array) ($in['deliveries'] ?? []), ['shipment_type', 'ship_date', 'ship_qty'],
            ['ship_qty'], fn ($id, $f, $v) => $c->updateDelivery($id, $f, $v));
        self::diff($o->externalCosts->keyBy('id_cost'), (array) ($in['costs'] ?? []), ['category', 'description', 'nominal', 'tax_treatment'],
            ['nominal'], fn ($id, $f, $v) => $c->updateExternalCost($id, $f, $v));
    }

    public static function addProduct(CoOrder $o, int $productId): void
    {
        $c = self::component($o->id_order);
        $c->openProductPicker();
        $c->pickProduct($productId);
    }

    public static function addService(CoOrder $o): void
    {
        self::component($o->id_order)->addItem('SERVICE');
    }

    public static function addDelivery(CoOrder $o, int $itemId): void
    {
        self::component($o->id_order)->addDelivery($itemId);
    }

    public static function addCost(CoOrder $o): void
    {
        self::component($o->id_order)->addExternalCost();
    }

    public static function confirm(CoOrder $o): bool
    {
        return CoOrderBuilder::confirm(CoOrder::with('items.deliveries')->findOrFail($o->id_order));
    }

    // Hanya DRAFT INSTANT: CO INTEGRATED sudah menandai inquiry CONVERTED + Master Product (WIN), jadi tidak dihapus di sini.
    public static function delete(CoOrder $o): void
    {
        abort_unless($o->doc_status === 'DRAFT' && $o->isInstant(), 422, 'Hanya draft INSTANT yang bisa dihapus.');
        DB::connection('tm2sdi')->transaction(function () use ($o) {
            $o->deliveries()->delete();
            $o->externalCosts()->delete();
            $o->items()->delete();
            $o->delete();
        });
    }

    private static function diff($current, array $posted, array $fields, array $numeric, callable $update): void
    {
        foreach ($posted as $id => $row) {
            $model = $current->get((int) $id);
            if (! $model) {
                continue;
            }
            foreach ($fields as $f) {
                if (! array_key_exists($f, (array) $row)) {
                    continue;
                }
                $v = in_array($f, $numeric, true) ? self::num($row[$f]) : self::str($row[$f]);
                $cur = self::str($model->{$f});
                if ($v !== $cur && ! (is_numeric($v) && is_numeric($cur) && (float) $v === (float) $cur)) {
                    $update((int) $id, $f, $v);
                }
            }
        }
    }

    private static function str(mixed $v): string
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v);
    }

    private static function num(mixed $v): string
    {
        $v = trim((string) $v);
        $v = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : (substr_count($v, '.') > 1 ? str_replace('.', '', $v) : $v);

        return $v === '' ? '0' : $v;
    }
}
