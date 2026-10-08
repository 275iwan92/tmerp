<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvShortageRequest;
use App\Models\Master\CustCustomer;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PvLine;
use App\Services\Pur\ShortageDetector;
use App\Services\Pur\ShortageRequestService;
use Devlib\Locale;

// Data layar Shortage Request (Inventory > Batch & Quality): pilihan form dan ringkasan satu permintaan. Aturannya di App\Services\Pur\ShortageRequestService.
final class ShortagePage
{
    public static function request(int $id): ?InvShortageRequest
    {
        return InvShortageRequest::query()->find($id);
    }

    /** @return array<string, string> "wo_id:material_id" => WO · bahan · qty keluar bersih */
    public static function issuedOptions(): array
    {
        return ShortageDetector::reconcileOptions();
    }

    /** @return array<int, string> baris penerimaan yang sudah Confirmed (PO, subkon, bahan customer): nomor · jenis · item · qty */
    public static function receiptLineOptions(): array
    {
        return PvLine::query()->with('receipt')->whereHas('receipt', fn ($q) => $q->where('status', 'POSTED')->whereIn('i_receipt_type', ['PO', 'SUBCON', 'CUSTOMER']))
            ->where('i_qty_purchase', '>', 0)->orderByDesc('pv_line_id')->limit(300)->get()
            ->mapWithKeys(fn (PvLine $l) => [$l->pv_line_id => ($l->receipt->doc_no ?: '#'.$l->pv_id).' #'.$l->line_no.' · '.$l->receipt->i_receipt_type.' · '.$l->i_description
                .' · '.Locale::decimal((float) $l->i_qty_purchase, 2).' '.$l->i_uom_purchase])->all();
    }

    public static function party(InvShortageRequest $r): string
    {
        if ($r->source_type === 'CUSTOMER') {
            return (string) CustCustomer::query()->whereKey($r->customer_id)->value('customer_name');
        }

        return (string) SupSupplier::query()->whereKey($r->supplier_id)->value('supplier_name');
    }

    public static function statusLabel(string $status): string
    {
        return ShortageRequestService::STATUSES[$status] ?? $status;
    }
}
