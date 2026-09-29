<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoActivity;
use App\Models\Sdi\QuoQuotation;
use App\Services\Sales\CoOrderBuilder;
use App\Services\Sales\QuotationBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

// Layar Quotation v2. Aturan & tulis = komponen v1 pages::quotation.entry yang SAMA (updateHeader/updateItem/publish/markLost/
// saveActivity/...), dipanggil per field supaya perilaku otomatis v1 (Berlaku s/d & Deadline ikut tanggal dokumen, CC tanpa Attn,
// subtotal ulang) tetap satu sumber. Tambahan v2: Hapus draft & Cancel (quotation PUBLISHED yang belum WIN/LOST).
final class Quotation
{
    public const STATUS_TONES = ['DRAFT' => 'neutral', 'PUBLISHED' => 'warn', 'WON' => 'success', 'LOST' => 'danger', 'CANCELLED' => 'neutral'];

    public const HEADER = ['issued_date', 'valid_until', 'deadline_date', 'payment_term', 'tax_scheme', 'currency_code', 'attn_contact', 'sales_pic', 'notes'];

    public static function component(?int $id): Component
    {
        $c = app('livewire')->new('pages::quotation.entry');
        $c->quotationId = $id;

        return $c;
    }

    // DRAFT | PUBLISHED | WON | LOST | CANCELLED
    public static function status(QuoQuotation $q): string
    {
        if ($q->doc_status !== 'PUBLISHED') {
            return $q->doc_status;
        }

        return $q->outcome() ?? 'PUBLISHED';
    }

    public static function rows(): array
    {
        $quotes = QuoQuotation::with(['items.inquiry:id_inquiry,sub_state', 'activities'])->orderByDesc('id_quotation')->limit(500)->get();
        $customers = CustCustomer::whereIn('id_customer', $quotes->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');

        return $quotes->map(function (QuoQuotation $q) use ($customers) {
            $next = $q->activities->whereNotNull('next_action_date')->sortByDesc('activity_date')->first();

            return [
                'id' => $q->id_quotation,
                'no' => $q->quotation_no,
                'customer' => $customers[$q->customer_id] ?? '—',
                'date' => $q->issued_date?->toDateString(),
                'valid' => $q->valid_until?->toDateString(),
                'sales' => $q->sales_pic,
                'lines' => $q->items->count(),
                'total' => $q->totals()['grand'],
                'followups' => $q->activities->count(),
                'next' => $next?->next_action_date?->toDateString(),
                'status' => self::status($q),
            ];
        })->all();
    }

    // Inquiry siap ditawarkan (= Ready to Quote di Inquiry Hub), opsional hanya 1 customer.
    public static function available(?int $customerId = null): array
    {
        $rows = QuotationBuilder::availableInquiries()->with(['costing.priceTiers', 'tiers'])
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderByDesc('inquiry_date')->limit(200)->get();
        $customers = CustCustomer::whereIn('id_customer', $rows->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');

        return $rows->map(function (IqpInquiry $i) use ($customers) {
            $prices = $i->costing?->priceTiers->pluck('price_per_pcs')->filter();

            return [
                'id' => $i->id_inquiry,
                'no' => $i->inquiry_no,
                'product' => $i->product_draft_name ?: $i->inquiry_no,
                'customer_id' => (int) $i->customer_id,
                'customer' => $customers[$i->customer_id] ?? '—',
                'date' => $i->inquiry_date?->toDateString(),
                'type' => $i->inquiry_type,
                'tiers' => $i->tiers->count(),
                'price' => $prices && $prices->isNotEmpty() ? $prices->min().($prices->max() != $prices->min() ? ' – '.$prices->max() : '') : null,
            ];
        })->all();
    }

    public static function create(array $inquiryIds): QuoQuotation
    {
        return QuotationBuilder::createFromInquiries(array_map('intval', $inquiryIds));
    }

    public static function addInquiries(QuoQuotation $q, array $inquiryIds): void
    {
        abort_unless($q->doc_status === 'DRAFT', 422, 'Quotation sudah tidak bisa diubah.');
        QuotationBuilder::applyInquiries($q, array_map('intval', $inquiryIds));
    }

    // Header + CC + baris dari form. Hanya field yang benar-benar berubah yang dikirim ke updateHeader v1,
    // tanggal dokumen dulu supaya geser otomatis Berlaku s/d & Deadline v1 tetap jalan.
    public static function save(QuoQuotation $q, array $header, ?array $cc, array $items, array $remove = []): void
    {
        abort_unless($q->doc_status === 'DRAFT', 422, 'Quotation sudah tidak bisa diubah.');
        $c = self::component($q->id_quotation);
        $orig = collect(self::HEADER)->mapWithKeys(fn ($f) => [$f => self::str($q->{$f})])->all();

        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $header) && self::str($header[$f]) !== $orig[$f]) {
                $c->updateHeader($f, self::str($header[$f]));
            }
        }

        if ($cc !== null) {
            $c->updateCc(json_encode(array_values($cc)));
        }

        foreach ($remove as $id) {
            $c->removeItem((int) $id);
        }

        $current = $q->items()->get()->keyBy('id_item');
        foreach ($items as $id => $row) {
            $item = $current->get((int) $id);
            if (! $item || in_array((string) $id, array_map('strval', $remove), true)) {
                continue;
            }
            foreach (['description', 'qty', 'qty_unit', 'unit_price', 'discount_pct'] as $f) {
                if (! array_key_exists($f, $row)) {
                    continue;
                }
                $v = in_array($f, ['qty', 'unit_price', 'discount_pct'], true) ? self::num($row[$f]) : trim((string) $row[$f]);
                if ((string) $v !== self::str($item->{$f}) && ! (is_numeric($v) && is_numeric($item->{$f}) && (float) $v === (float) $item->{$f})) {
                    $c->updateItem((int) $id, $f, $v);
                }
            }
        }
    }

    public static function addLine(QuoQuotation $q): void
    {
        self::component($q->id_quotation)->addFreeform();
    }

    public static function confirm(QuoQuotation $q): void
    {
        abort_unless($q->doc_status === 'DRAFT', 422, 'Hanya DRAFT yang bisa di-Confirm.');
        abort_if($q->items()->doesntExist(), 422, 'Quotation belum punya baris.');
        self::component($q->id_quotation)->publish();
    }

    // Draft belum pernah dikirim ke customer -> boleh dihapus; inquiry-nya kembali Ready to Quote.
    public static function delete(QuoQuotation $q): void
    {
        abort_unless($q->doc_status === 'DRAFT', 422, 'Hanya DRAFT yang bisa dihapus.');
        DB::connection('tm2sdi')->transaction(function () use ($q) {
            $q->items()->delete();
            $q->ccs()->delete();
            $q->activities()->delete();
            $q->delete();
        });
    }

    public static function cancel(QuoQuotation $q, string $reason): void
    {
        abort_unless(self::status($q) === 'PUBLISHED', 422, 'Hanya quotation terbit yang belum WIN/LOST yang bisa dibatalkan.');
        $q->update(['doc_status' => 'CANCELLED', 'stat_cancel' => true, 'cancel_reason' => $reason, 'updated_by' => Auth::user()->login]);
    }

    public static function lost(QuoQuotation $q, string $reason): void
    {
        abort_unless(self::status($q) === 'PUBLISHED', 422, 'Quotation ini sudah ditutup.');
        $c = self::component($q->id_quotation);
        $c->lostReason = $reason;
        $c->markLost();
    }

    // WIN = Customer Order lahir (1 CO = 1 Quotation), inquiry CONVERTED -> Master Product.
    public static function win(QuoQuotation $q): ?int
    {
        abort_unless(in_array(self::status($q), ['PUBLISHED', 'WON'], true), 422, 'Quotation ini tidak bisa menjadi Customer Order.');

        return CoOrderBuilder::findOrCreateFromQuotation($q)?->id_order;
    }

    public static function addActivity(QuoQuotation $q, array $act): void
    {
        $c = self::component($q->id_quotation);
        $c->act = $act + ['contact_person' => null, 'result' => '', 'notes' => '', 'next_action_date' => '', 'next_action' => ''];
        $c->saveActivity();
    }

    public static function deleteActivity(QuoQuotation $q, int $id): void
    {
        self::component($q->id_quotation)->deleteActivity($id);
    }

    private static function str(mixed $v): string
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v);
    }

    // "10.000" / "1,5" (tampilan id-ID) -> "10000" / "1.5"; "1250.75" apa adanya.
    private static function num(mixed $v): string
    {
        $v = trim((string) $v);
        $v = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : (substr_count($v, '.') > 1 ? str_replace('.', '', $v) : $v);

        return $v === '' ? '0' : $v;
    }
}
