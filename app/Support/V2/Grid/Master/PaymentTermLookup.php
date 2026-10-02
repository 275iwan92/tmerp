<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\FinPaymentTerm;
use Illuminate\Database\Eloquent\Builder;

// Master Payment Terms (tm2mst.fin_payment_term): syarat bayar di Master Customer / Supplier, Quotation, Customer Order dan
// jatuh tempo tagihan (Receivables / Payables = tanggal + Days). Dulu tidak ada layar isinya (audit 2026-10-02).
class PaymentTermLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-payment-term';
    }

    public function title(): string
    {
        return 'Payment Terms';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-invoice-dollar';
    }

    public function guide(): ?string
    {
        return 'Terms chosen in Master Customer, Master Supplier, Quotation and Customer Order. Days = credit days used for the invoice due date; '
            .'leave it empty when no due date applies. A saved code cannot be changed because documents refer to it; deactivate it instead.';
    }

    protected function query(): Builder
    {
        return FinPaymentTerm::query()->orderBy('sort_order')->orderBy('payment_term_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'payment_term_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '150px'],
            ['key' => 'term_label', 'label' => 'Term Name', 'required' => true, 'width' => '300px'],
            ['key' => 'days', 'label' => 'Days', 'type' => 'number', 'decimals' => 0, 'width' => '100px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'decimals' => 0, 'width' => '100px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        $in = $this->upper($in, 'payment_term_code');
        // Urutan kosong = paling bawah (bawaan kolom), bukan NULL: kolomnya NOT NULL.
        if (($in['sort_order'] ?? '') === '' || ($in['sort_order'] ?? null) === null) {
            $in['sort_order'] = 999;
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['payment_term_code' => ['string', 'max:20', 'regex:/^[A-Z0-9_\-]+$/'], 'term_label' => ['string', 'max:60'],
            'days' => ['integer', 'min:0', 'max:65535'], 'sort_order' => ['integer', 'min:-32768', 'max:32767']];
    }
}
