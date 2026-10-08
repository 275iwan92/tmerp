<?php

declare(strict_types=1);

namespace App\Services\Pur;

use App\Models\Inventory\InvShortageRequest;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Models\Sys\SysConstant;
use App\Services\Inventory\QcAuthority;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Permintaan Kirim Kekurangan (Q-13..Q-16, D14, D19): bahan kurang sejak awal diminta dikirim oleh supplier / subkon / customer.
//   DRAFT     dibuat dari dugaan (ShortageDetector); Kepala Gudang menghitung ulang (recount) sebagai bukti, atau PPIC menutupnya sebagai varians pemakaian (dismiss).
//   CONFIRMED PPIC mengonfirmasi (confirm): qty terima pada penerimaan asal turun, baris PO dibuka lagi (backorder), jurnal GR/IR dikoreksi (PV_SHORT),
//             stok dikurangi lewat Stock Out ber-ref PV_SHORT. Subkon memakai SpecialReceiptService::reportShortage; bahan customer hanya qty (tanpa nilai).
//   SENT      Pembelian memberi tahu pemasok; jendela kirim INV_SHORTAGE_WINDOW_DAYS hari mulai hari itu.
//   RECEIVED  kekurangan datang (lewat penerimaan barang biasa dari baris PO yang dibuka lagi);
//   REFUSED   pemasok menolak atau lewat jendela: baris PO ditutup di qty terima (c_line_status CLOSED_SHORT) dan ditandai butuh debit note / potong tagihan.
//   CLOSED    selesai. Toleransi PO sudah diperiksa saat dibuat (dugaan harus di atas toleransi).
// Belum dibangun: pemberitahuan popup ke Pembelian / PPIC, permintaan pembelian (PR) tambahan, debit note otomatis (hanya tanda dan catatan).
final class ShortageRequestService
{
    /** Nilai baru po_line.c_line_status: baris ditutup di qty terima karena kekurangan tidak dikirim. */
    public const LINE_CLOSED = 'CLOSED_SHORT';

    public const SOURCES = ['SUPPLIER' => 'Supplier', 'SUBCON' => 'Subcontractor', 'CUSTOMER' => 'Customer'];

    public const STATUSES = ['DRAFT' => 'Draft', 'CONFIRMED' => 'Confirmed', 'SENT' => 'Sent', 'RECEIVED' => 'Received', 'REFUSED' => 'Refused', 'CLOSED' => 'Closed'];

    public const OUTCOMES = ['USAGE_VARIANCE' => 'Usage variance', 'DELIVERED' => 'Delivered', 'REFUSED' => 'Refused', 'WINDOW_LAPSED' => 'Window lapsed'];

    private const SOURCE_OF_RECEIPT = ['PO' => 'SUPPLIER', 'SUBCON' => 'SUBCON', 'CUSTOMER' => 'CUSTOMER'];

    /**
     * @param  array{wo_material: string, pv_line_id: int|string, qty_output_good: float|string, qty_waste_std?: float|string|null, need_date?: ?string, notes?: ?string}  $in
     */
    public static function create(array $in, string $login): InvShortageRequest
    {
        [$woId, $materialId] = array_map('intval', array_pad(explode(':', (string) ($in['wo_material'] ?? '')), 2, 0));
        $facts = ShortageDetector::reconcile($woId, $materialId) ?? throw new InvalidArgumentException(t('Choose a work order and material that has material issued.'));
        $line = PvLine::query()->with('receipt')->find((int) ($in['pv_line_id'] ?? 0)) ?? throw new InvalidArgumentException(t('Choose the receipt line the material came with.'));
        $pv = $line->receipt;
        if (! $pv || $pv->status !== 'POSTED' || ! isset(self::SOURCE_OF_RECEIPT[$pv->i_receipt_type])) {
            throw new InvalidArgumentException(t('The receipt line must belong to a Confirmed receipt.'));
        }
        $waste = isset($in['qty_waste_std']) && $in['qty_waste_std'] !== '' ? (float) $in['qty_waste_std'] : $facts['waste_standard'];
        $output = (float) ($in['qty_output_good'] ?? 0);
        $tol = ShortageDetector::tolerancePct($line->po_line_id ? (int) $line->po_line_id : null);
        $check = ShortageDetector::evaluate($facts['issued_net'], $output, $waste, $tol);
        if ($check['candidate'] <= 0) {
            throw new InvalidArgumentException(t('Output plus standard waste already cover the material issued. A difference in waste is a usage variance, not a shortage.'));
        }
        if (! $check['over_tolerance']) {
            throw new InvalidArgumentException(t('The difference is within the PO tolerance, so it is not a shortage.'));
        }
        $source = self::SOURCE_OF_RECEIPT[$pv->i_receipt_type];

        return InvShortageRequest::query()->create([
            'request_no' => self::nextNo(), 'source_type' => $source, 'supplier_id' => $pv->i_supplier_id, 'customer_id' => $pv->i_customer_id, 'po_id' => $line->c_po_id, 'po_line_id' => $line->po_line_id,
            'pv_id' => $pv->pv_id, 'pv_line_id' => $line->pv_line_id, 'co_id' => $pv->i_co_id, 'wo_id' => $woId, 'wo_no' => $facts['wo_no'], 'material_id' => $materialId,
            'material_code' => $facts['material_code'], 'material_name' => $facts['material_name'], 'uom_code' => $facts['uom'], 'qty_issued_net' => $facts['issued_net'], 'qty_output_good' => $output,
            'qty_waste_std' => $waste, 'qty_candidate' => $check['candidate'], 'tol_pct' => $tol, 'need_date' => ($in['need_date'] ?? '') !== '' ? $in['need_date'] : now()->toDateString(),
            'status' => 'DRAFT', 'notes' => isset($in['notes']) ? mb_substr(trim((string) $in['notes']), 0, 255) : null, 'created_by' => $login, 'updated_by' => $login,
        ]);
    }

    // Kepala Gudang menghitung / menimbang ulang: qty hasil hitung dan kekurangan yang terbukti (paling banyak sebesar dugaan).
    public static function recount(InvShortageRequest $req, float $countedQty, ?float $weight, float $confirmedShort, string $note, string $login): InvShortageRequest
    {
        if (! QcAuthority::canRecount($login)) {
            throw new InvalidArgumentException(t('Only the head of the warehouse can record the recount.'));
        }
        self::assertStatus($req, ['DRAFT']);
        if ($confirmedShort <= 0 || $confirmedShort > (float) $req->qty_candidate + 0.0001) {
            throw new InvalidArgumentException(tf('The proven shortage must be more than 0 and not more than the suspected :qty.', ['qty' => Locale::decimal((float) $req->qty_candidate, 2)]));
        }
        if (mb_strlen(trim($note)) < 5) {
            throw new InvalidArgumentException(t('Describe how the material was recounted or weighed.'));
        }
        $req->forceFill(['recount_qty' => $countedQty, 'recount_weight' => $weight, 'recount_note' => mb_substr(trim($note), 0, 255), 'recount_by' => $login, 'recount_at' => now(),
            'qty_short' => $confirmedShort, 'updated_by' => $login])->save();

        return $req;
    }

    // Tanpa bukti hitung ulang, selisih tetap varians pemakaian: PPIC menutup permintaan.
    public static function dismiss(InvShortageRequest $req, string $note, string $login): InvShortageRequest
    {
        if (! QcAuthority::canConfirmShortage($login)) {
            throw new InvalidArgumentException(t('Only PPIC can confirm or dismiss a shortage request.'));
        }
        self::assertStatus($req, ['DRAFT']);

        return self::finish($req, 'CLOSED', 'USAGE_VARIANCE', $note, $login);
    }

    // PPIC mengonfirmasi (butuh bukti hitung ulang): koreksi penerimaan asal, PO dan jurnal.
    public static function confirm(InvShortageRequest $req, string $login): InvShortageRequest
    {
        if (! QcAuthority::canConfirmShortage($login)) {
            throw new InvalidArgumentException(t('Only PPIC can confirm or dismiss a shortage request.'));
        }
        self::assertStatus($req, ['DRAFT']);
        if (! $req->recount_at || (float) $req->qty_short <= 0) {
            throw new InvalidArgumentException(t('The head of the warehouse must record the recount first. Without proof the difference stays a usage variance.'));
        }
        $line = PvLine::query()->with('receipt')->findOrFail($req->pv_line_id);
        $warning = self::correctReceipt($req, $line, $login);
        $req->forceFill(['status' => 'CONFIRMED', 'confirmed_by' => $login, 'confirmed_at' => now(), 'stock_warning' => $warning !== null ? mb_substr($warning, 0, 255) : null, 'updated_by' => $login])->save();

        return $req;
    }

    // Pembelian memberitahu pemasok; jendela kirim mulai hari ini.
    public static function send(InvShortageRequest $req, string $login): InvShortageRequest
    {
        self::assertFollowUp($login);
        self::assertStatus($req, ['CONFIRMED']);
        $req->forceFill(['status' => 'SENT', 'sent_at' => now(), 'sent_by' => $login, 'window_until' => now()->addDays((int) SysConstant::number('INV_SHORTAGE_WINDOW_DAYS', 7))->toDateString(),
            'updated_by' => $login])->save();

        return $req;
    }

    public static function receive(InvShortageRequest $req, string $note, string $login): InvShortageRequest
    {
        self::assertFollowUp($login);
        self::assertStatus($req, ['SENT']);

        return self::finish($req, 'RECEIVED', 'DELIVERED', $note, $login);
    }

    // Pemasok menolak (atau jendela lewat): baris PO ditutup di qty terima, butuh debit note / potong tagihan.
    public static function refuse(InvShortageRequest $req, string $note, string $login, string $outcome = 'REFUSED'): InvShortageRequest
    {
        self::assertFollowUp($login);
        self::assertStatus($req, ['CONFIRMED', 'SENT']);
        if ($req->po_line_id) {
            DB::connection('tm2pur')->table('po_line')->where('po_line_id', $req->po_line_id)->update(['c_line_status' => self::LINE_CLOSED, 'updated_by' => $login]);
            if ($req->po_id) {
                PvPostingService::recalcPo((int) $req->po_id);
            }
        }
        $req->forceFill(['debit_note_required' => $req->source_type !== 'CUSTOMER'])->save();

        return self::finish($req, 'REFUSED', $outcome, $note !== '' ? $note : t('Closed at the received quantity: ask the supplier for a debit note or deduct it from the invoice.'), $login);
    }

    public static function close(InvShortageRequest $req, string $login): InvShortageRequest
    {
        self::assertFollowUp($login);
        self::assertStatus($req, ['RECEIVED', 'REFUSED']);
        $req->forceFill(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $login, 'updated_by' => $login])->save();

        return $req;
    }

    // Permintaan SENT yang lewat jendela kirim ditutup seperti ditolak. Kembalian = jumlah permintaan yang ditutup.
    public static function closeLapsed(string $login): int
    {
        $n = 0;
        foreach (InvShortageRequest::query()->where('status', 'SENT')->whereDate('window_until', '<', now()->toDateString())->get() as $req) {
            self::refuse($req, tf('The supplier did not send the shortfall within :days days.', ['days' => (int) SysConstant::number('INV_SHORTAGE_WINDOW_DAYS', 7)]), $login, 'WINDOW_LAPSED');
            $n++;
        }

        return $n;
    }

    // Penerimaan supplier ini sudah dikoreksi permintaan kirim kekurangan (stok dan jurnal koreksi terpisah), jadi tidak boleh di-Cancel utuh.
    public static function hasCorrections(PvReceipt $pv): bool
    {
        return InvShortageRequest::query()->where('pv_id', $pv->pv_id)->where('source_type', 'SUPPLIER')->where('status', '<>', 'DRAFT')
            ->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '<>', 'USAGE_VARIANCE'))->exists();
    }

    // Koreksi qty terima pada penerimaan asal. Kembalian = peringatan stok / jurnal (null bila beres).
    private static function correctReceipt(InvShortageRequest $req, PvLine $line, string $login): ?string
    {
        $pv = $line->receipt;
        $stockShort = (float) $req->qty_short;
        if ($stockShort > (float) ($pv->i_receipt_type === 'PO' ? $line->c_qty_accepted : $line->i_qty_purchase) + 0.0001) {
            throw new InvalidArgumentException(t('The shortage is more than the quantity received on the receipt line.'));
        }
        $ratio = (float) $line->i_qty_stock > 0 && (float) $line->i_qty_purchase > 0 ? (float) $line->i_qty_purchase / (float) $line->i_qty_stock : 1.0;
        $purchaseShort = round($stockShort * $ratio, 4);
        $note = mb_substr("Shortage request {$req->request_no}: WO {$req->wo_no}", 0, 120);

        if ($pv->i_receipt_type === 'SUBCON') {
            SpecialReceiptService::reportShortage($pv, (int) $line->pv_line_id, $purchaseShort, $note, $login);

            return null;
        }
        DB::connection('tm2pur')->transaction(function () use ($line, $pv, $stockShort, $purchaseShort, $note, $login) {
            $accepted = $pv->i_receipt_type === 'PO' ? max(0.0, (float) $line->c_qty_accepted - $stockShort) : (float) $line->c_qty_accepted;
            $line->forceFill(['i_qty_purchase' => (float) $line->i_qty_purchase - $purchaseShort, 'i_qty_stock' => max(0.0, (float) $line->i_qty_stock - $stockShort),
                'c_qty_accepted' => $accepted, 'c_qty_short' => (float) $line->c_qty_short + $purchaseShort, 'c_line_value' => round($accepted * (float) $line->c_unit_price, 2),
                'i_short_note' => mb_substr(trim(($line->i_short_note ? $line->i_short_note.' | ' : '').$note), 0, 255), 'updated_by' => $login])->save();
            if ($line->c_po_id) {
                PvPostingService::recalcPo((int) $line->c_po_id);
            }
        });
        if ($pv->i_receipt_type !== 'PO') {
            return null;
        }
        $warnings = array_filter([PvPostingService::postShortageJournal($pv, $line, $stockShort, $login), PvPostingService::correctStockForShortage($pv, $line, $stockShort, $login)]);

        return $warnings === [] ? null : implode(' ', $warnings);
    }

    private static function finish(InvShortageRequest $req, string $status, string $outcome, string $note, string $login): InvShortageRequest
    {
        $closing = $status === 'CLOSED';
        $req->forceFill(['status' => $status, 'outcome' => $outcome, 'outcome_note' => mb_substr(trim($note), 0, 255), 'updated_by' => $login]
            + ($closing ? ['closed_at' => now(), 'closed_by' => $login] : []))->save();

        return $req;
    }

    private static function assertStatus(InvShortageRequest $req, array $allowed): void
    {
        if (! in_array($req->status, $allowed, true)) {
            throw new InvalidArgumentException(tf('This request is :status, so this step is not possible.', ['status' => t(self::STATUSES[$req->status] ?? $req->status)]));
        }
    }

    private static function assertFollowUp(string $login): void
    {
        if (! QcAuthority::canFollowUp($login)) {
            throw new InvalidArgumentException(t('Only Purchasing can follow up a shortage request.'));
        }
    }

    // Nomor permintaan: SR + 2 digit tahun + 5 digit urut (SR2600001).
    private static function nextNo(): string
    {
        $prefix = 'SR'.now()->format('y');
        $last = InvShortageRequest::query()->where('request_no', 'like', $prefix.'%')->orderByDesc('request_no')->value('request_no');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
