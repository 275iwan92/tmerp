<?php

namespace Tests\Support;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpInquiry;
use App\Services\Sales\InquiryWriter;
use App\Support\V2\InquiryPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/*
 * REMARK
 * Tujuan       : Pembuat Inquiry NEW untuk tes lewat jalur yang sama dengan layar v2 (isian form -> InquiryPayload -> InquiryWriter), tanpa HTTP. Pengganti Livewire::test('pages::inquiry.form') v1.
 * Sumber hulu  : App\Support\V2\InquiryPayload (validasi dan bentuk input), App\Services\Sales\InquiryWriter (simpan, confirm).
 * Input        : Nama produk draft; isian form (nama field v2) yang menimpa bawaan; $tune untuk mengubah array payload sebelum simpan (mis. diskon).
 * Output       : IqpInquiry tersimpan (DRAFT); confirm() mengunci SUBMITTED. Pemanggil membersihkan datanya sendiri.
 * Dipakai oleh : tests/Feature (Costing*, ProCostV2, Inquiry*).
 * Status       : Aktif (T10 tahap 4, 2026-10-21).
 */
final class InquiryDriver
{
    public static function create(string $name, array $fields = [], ?callable $tune = null): IqpInquiry
    {
        $qty = Arr::pull($fields, 'qty', 1000);
        $in = $fields + [
            'inquiry_date' => now()->toDateString(), 'source' => 'EMAIL', 'customer_id' => CustCustomer::firstOrFail()->id_customer,
            'product_draft_name' => $name, 'type_code' => 'RTE_BOX',
            // Seperti form v2 baru: tiga tier, yang pertama UTAMA (qty pertama dari $fields['qty']).
            'tiers' => [['qty' => $qty, 'is_primary' => 1], ['qty' => 50000], ['qty' => 200000]],
            'parts' => [['part_name' => 'Body', 'part_type' => 'DC', 'qty_per_sku' => 1, 'cfg_color' => '4/4']],
        ];
        Validator::make($in, InquiryPayload::rules())->validate();
        $payload = InquiryPayload::from(Request::create('/', 'POST', $in), null);
        if ($tune) {
            $payload = $tune($payload) ?? $payload;
        }

        return self::writer()->save($payload, null);
    }

    public static function confirm(IqpInquiry $inquiry): IqpInquiry
    {
        self::writer()->confirm($inquiry);

        return $inquiry->refresh();
    }

    public static function writer(): InquiryWriter
    {
        return new InquiryWriter(Auth::user()->login);
    }
}
