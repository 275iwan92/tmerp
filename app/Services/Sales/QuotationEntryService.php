<?php

/*
 * REMARK
 * Tujuan       : Aturan entri Quotation (dipindah apa adanya dari komponen v1 pages::quotation.entry): ubah header (Berlaku s/d dan Deadline ikut tanggal dokumen selama belum di-override), CC tanpa Attn, baris bebas, hapus/ubah item, Publish, Lost, follow-up Sales. Semua perubahan hanya saat DRAFT.
 * Sumber hulu  : QuoQuotation, QuoQuotationItem, QuoQuotationCc, QuoActivity, QuotationBuilder::autoHeader, IqpInquiry.
 * Input        : QuoQuotation + nilai field / baris / alasan.
 * Output       : quo_quotation, quo_quotation_item, quo_quotation_cc, quo_activity, iqp_inquiry (sub_state LOST).
 * Dipakai oleh : App\Support\V2\Data\Quotation (layar v2 quotation), tests/Feature/QuotationEntryServiceTest.
 * Status       : Aktif (T10 pindahan v1, 2026-10-20).
 */

namespace App\Services\Sales;

use App\Models\Master\CustCustomerContact;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoActivity;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationCc;
use App\Models\Sdi\QuoQuotationItem;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

final class QuotationEntryService
{
    public const HEADER_FIELDS = ['issued_date', 'valid_until', 'deadline_date', 'payment_term', 'tax_scheme', 'currency_code', 'attn_contact', 'sales_pic', 'notes'];

    public const ITEM_FIELDS = ['description', 'qty', 'qty_unit', 'unit_price', 'discount_pct'];

    public static function editable(QuoQuotation $q): bool
    {
        return $q->doc_status === 'DRAFT';
    }

    // Nilai otomatis header (dari customer + inquiry pertama); kontak default customer menggantikan Attn otomatis.
    public static function autoValues(QuoQuotation $q): array
    {
        if (! $q->customer_id) {
            return [];
        }

        $inquiryId = $q->items->where('source_type', 'INQUIRY_TIER')->pluck('inquiry_id')->filter()->first();
        $auto = QuotationBuilder::autoHeader($q->customer, $inquiryId ? IqpInquiry::find($inquiryId) : null, $q->issued_date);
        $default = CustCustomerContact::where('customer_id', $q->customer_id)->where('activate', true)->where('is_default', true)->value('contact_name');
        $auto['attn_contact'] = $default ?: $auto['attn_contact'];

        return $auto;
    }

    // [field => 'auto'|'override'] dibandingkan dengan nilai otomatis saat ini.
    public static function autoState(QuoQuotation $q): array
    {
        return collect(self::autoValues($q))->map(function ($auto, $field) use ($q) {
            $current = $q->{$field} instanceof DateTimeInterface ? $q->{$field}->format('Y-m-d') : $q->{$field};

            return (string) $current === (string) $auto ? 'auto' : 'override';
        })->all();
    }

    // Kontak default = cust_customer_contact.is_default, cadangan cust_customer.contact_name.
    public static function contactOptions(QuoQuotation $q): array
    {
        $customer = $q->customer;

        if (! $customer) {
            return [];
        }

        $names = CustCustomerContact::where('customer_id', $customer->id_customer)->where('activate', true)
            ->orderByDesc('is_default')->pluck('contact_name')
            ->push($customer->contact_name, $q->attn_contact)
            ->filter()->unique()->values();

        return $names->combine($names)->all();
    }

    // CC = kontak customer yang sama, selain orang Attn.
    public static function ccOptions(QuoQuotation $q): array
    {
        return collect(self::contactOptions($q))->keys()->push(...$q->ccs->pluck('contact_name'))
            ->unique()->reject(fn ($n) => $n === $q->attn_contact)
            ->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all();
    }

    public static function salesOptions(QuoQuotation $q): array
    {
        $names = SalesPerson::where('activate', true)->orderBy('sales_name')->pluck('sales_name')
            ->push($q->sales_pic)->filter()->unique()->values();

        return $names->combine($names)->all();
    }

    public static function updateHeader(QuoQuotation $q, string $field, mixed $value): void
    {
        if (! self::editable($q) || ! in_array($field, self::HEADER_FIELDS, true)) {
            return;
        }

        $changes = [$field => $value === '' ? null : $value];

        // Ganti tanggal dokumen: Berlaku s/d & Deadline ikut bergeser selama belum di-override.
        if ($field === 'issued_date' && $value) {
            $state = self::autoState($q);
            $newAuto = QuotationBuilder::autoHeader(null, null, $value);
            foreach (['valid_until', 'deadline_date'] as $f) {
                if (($state[$f] ?? null) === 'auto') {
                    $changes[$f] = $newAuto[$f];
                }
            }
        }

        $q->update($changes + ['updated_by' => Auth::user()->login]);

        if ($field === 'attn_contact' && $value) {
            $q->ccs()->where('contact_name', $value)->delete();
        }

        self::reload($q);
    }

    // $names = nama kontak; urutan pilih = urutan cetak. Orang Attn tidak ikut CC.
    public static function updateCc(QuoQuotation $q, array $names): void
    {
        $names = collect($names)->filter()->unique()->reject(fn ($n) => $n === $q->attn_contact)->values();

        if (! self::editable($q) || $names->all() === $q->ccs->pluck('contact_name')->all()) {
            return;
        }

        $login = Auth::user()->login;
        $q->ccs()->delete();
        $names->each(fn ($name, $i) => QuoQuotationCc::create([
            'quotation_id' => $q->id_quotation, 'line_seq' => $i + 1, 'contact_name' => $name, 'created_by' => $login,
        ]));
        self::reload($q);
    }

    public static function addFreeform(QuoQuotation $q): void
    {
        if (! self::editable($q)) {
            return;
        }

        $login = Auth::user()->login;
        QuoQuotationItem::create([
            'quotation_id' => $q->id_quotation,
            'line_seq' => ((int) $q->items()->max('line_seq')) + 1,
            'source_type' => 'FREEFORM',
            'description' => '',
            'qty' => 1,
            'qty_unit' => 'Pcs',
            'unit_price' => 0,
            'discount_pct' => 0,
            'subtotal' => 0,
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        self::reload($q);
    }

    public static function removeItem(QuoQuotation $q, int $itemId): void
    {
        if (! self::editable($q)) {
            return;
        }

        QuoQuotationItem::where('quotation_id', $q->id_quotation)->whereKey($itemId)->delete();
        self::reload($q);
    }

    public static function updateItem(QuoQuotation $q, int $itemId, string $field, mixed $value): void
    {
        if (! self::editable($q) || ! in_array($field, self::ITEM_FIELDS, true)) {
            return;
        }

        $item = QuoQuotationItem::where('quotation_id', $q->id_quotation)->findOrFail($itemId);
        $item->{$field} = $value === '' ? 0 : $value;
        $item->recalcSubtotal();
        $item->updated_by = Auth::user()->login;
        $item->save();
        self::reload($q);
    }

    // DRAFT -> PUBLISHED, hanya bila ada baris.
    public static function publish(QuoQuotation $q): void
    {
        if (! self::editable($q) || $q->items->isEmpty()) {
            return;
        }

        $q->update(['doc_status' => 'PUBLISHED', 'updated_by' => Auth::user()->login]);
        self::reload($q);
    }

    // Sama aturannya dengan markLost() di form Inquiry: semua inquiry di quotation ini yang belum terminal ditandai LOST,
    // alasan disimpan di cancel_reason.
    public static function markLost(QuoQuotation $q, string $reason): void
    {
        $q->loadMissing('items.inquiry');
        if ($q->doc_status !== 'PUBLISHED' || $q->outcome()) {
            return;
        }

        Validator::make(['lostReason' => $reason], ['lostReason' => ['required', 'string', 'max:255']], [], ['lostReason' => 'Alasan kalah'])->validate();

        IqpInquiry::whereIn('id_inquiry', $q->items->pluck('inquiry_id')->filter()->unique())
            ->whereNotIn('sub_state', ['CANCELLED', 'CONVERTED', 'LOST'])
            ->update(['cancel_reason' => $reason, 'sub_state' => 'LOST', 'updated_by' => Auth::user()->login]);

        self::reload($q);
    }

    // Progress follow-up Sales. Kunci galat tetap 'act.*' seperti v1.
    public static function saveActivity(QuoQuotation $q, array $act): void
    {
        $data = Validator::make(['act' => $act], [
            'act.activity_type' => ['required', 'in:'.implode(',', array_keys(QuoActivity::TYPES))],
            'act.activity_date' => ['required', 'date'],
            'act.contact_person' => ['nullable', 'string', 'max:150'],
            'act.result' => ['nullable', 'in:'.implode(',', array_keys(QuoActivity::RESULTS))],
            'act.notes' => ['nullable', 'string', 'max:2000'],
            'act.next_action_date' => ['nullable', 'date'],
            'act.next_action' => ['nullable', 'string', 'max:255'],
        ], [], ['act.activity_date' => 'Tanggal', 'act.activity_type' => 'Jenis'])->validate()['act'];

        $login = Auth::user()->login;
        QuoActivity::create(array_map(fn ($v) => $v === '' ? null : $v, $data) + [
            'quotation_id' => $q->id_quotation, 'created_by' => $login, 'updated_by' => $login,
        ]);
    }

    // Hanya follow-up milik pembuatnya.
    public static function deleteActivity(QuoQuotation $q, int $id): void
    {
        QuoActivity::where('quotation_id', $q->id_quotation)->where('created_by', Auth::user()->login)->whereKey($id)->delete();
    }

    private static function reload(QuoQuotation $q): void
    {
        $q->refresh()->load(['customer', 'ccs', 'items' => fn ($i) => $i->orderBy('line_seq')]);
    }
}
