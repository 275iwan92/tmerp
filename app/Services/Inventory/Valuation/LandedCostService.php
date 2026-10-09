<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use App\Models\Acc\FinVoucher;
use App\Models\Inventory\InvLandedCost;
use App\Models\Inventory\InvLandedCostLine;
use App\Models\Inventory\InvPeriod;
use App\Models\Inventory\StockOnhand;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Services\Acc\JournalPostingService;
use Illuminate\Support\Facades\DB;

// Landed cost yang datang SETELAH penerimaan (D17, V-04): ongkos angkut, bea, asuransi dibagi ke baris penerimaan menurut nilai atau qty.
//   Bagian yang masih ada di gudang  -> menaikkan nilai persediaan item (Dr Persediaan).
//   Bagian yang sudah keluar (dipakai / dikirim) -> Dr Selisih Landed Cost, ditutup ke HPP di akhir periode (closeVariance).
//   Pembagian "masih ada": rasio = min(1, saldo item sekarang / qty yang diterima di baris itu); Average tidak melacak lot, jadi rasio inilah
//   yang dipakai (Odoo AVCO / SAP MAP berperilaku sama: biaya terlambat hanya menyentuh sisa stok).
// Jurnal (kode jurnal PUR, sumber LANDED): Dr Persediaan per akun item + Dr Selisih Landed Cost / Cr Hutang Biaya Landed Cost (total biaya).
// Koreksi nilai atau pembatalan dokumen yang sudah Confirmed TIDAK menimpa jurnal: lewat RepostService (jurnal koreksi / pembalik bertumpuk).
// CATATAN: nilai kartu stok (rantai Average) belum punya baris "khusus nilai", jadi bagian yang masuk persediaan baru ada di buku besar;
// kolom card_synced = 0 dan Daily Reconciliation menampilkannya sebagai "Landed cost not in cards" sampai rantai Average mendukungnya.
final class LandedCostService
{
    public function __construct(private JournalPostingService $journals, private RepostService $reposts) {}

    /** @param array{doc_date?: string, supplier_id?: ?int, cost_type?: string, description?: ?string, amount?: float|string, alloc_basis?: string, notes?: ?string} $in */
    public function create(array $in, string $login): InvLandedCost
    {
        $doc = InvLandedCost::create(['doc_date' => now()->toDateString(), 'cost_type' => 'FREIGHT', 'alloc_basis' => 'VALUE', 'created_by' => $login, 'updated_by' => $login]);

        try {
            return $this->saveHeader($doc, $in, $login);
        } catch (ValuationRefused $e) {
            $doc->delete();

            throw $e;
        }
    }

    public function saveHeader(InvLandedCost $doc, array $in, string $login): InvLandedCost
    {
        $this->assertDraft($doc);
        $type = $in['cost_type'] ?? $doc->cost_type;
        $basis = $in['alloc_basis'] ?? $doc->alloc_basis;
        if (! isset(InvLandedCost::TYPES[$type]) || ! isset(InvLandedCost::BASES[$basis])) {
            throw new ValuationRefused(t('Choose a valid cost type and allocation basis.'));
        }
        $doc->fill(['doc_date' => $in['doc_date'] ?? $doc->doc_date, 'supplier_id' => ($in['supplier_id'] ?? $doc->supplier_id) ?: null, 'cost_type' => $type,
            'description' => isset($in['description']) ? mb_substr(trim((string) $in['description']), 0, 255) : $doc->description, 'alloc_basis' => $basis,
            'amount' => round((float) ($in['amount'] ?? $doc->amount), 2), 'notes' => isset($in['notes']) ? mb_substr(trim((string) $in['notes']), 0, 255) : $doc->notes, 'updated_by' => $login])->save();

        return $doc;
    }

    // Semua baris stok penerimaan PO yang sudah POSTED ditambahkan ke dokumen (baris yang sudah ada dilewati).
    public function addReceipt(InvLandedCost $doc, int $pvId, string $login): int
    {
        $this->assertDraft($doc);
        $pv = PvReceipt::query()->find($pvId);
        if (! $pv || $pv->status !== 'POSTED') {
            throw new ValuationRefused(t('Only a posted goods receipt can carry a landed cost.'));
        }
        $have = $doc->lines()->pluck('pv_line_id')->all();
        $added = 0;
        foreach (PvLine::query()->where('pv_id', $pvId)->where('is_stock', 1)->whereNotNull('item_id')->where('c_qty_accepted', '>', 0)->orderBy('line_no')->get() as $line) {
            if (in_array($line->pv_line_id, $have, true)) {
                continue;
            }
            $doc->lines()->create(['line_no' => (int) $doc->lines()->max('line_no') + 1, 'pv_id' => $pvId, 'pv_line_id' => $line->pv_line_id, 'item_id' => $line->item_id,
                'item_code' => $line->c_item_code, 'item_name' => mb_substr((string) $line->i_description, 0, 255), 'uom_code' => $line->i_uom_stock,
                'qty_received' => (float) $line->c_qty_accepted, 'base_value' => round((float) $line->c_qty_accepted * (float) $line->c_unit_price, 2), 'created_by' => $login]);
            $added++;
        }
        if ($added === 0) {
            throw new ValuationRefused(t('That receipt has no stock line left to add.'));
        }

        return $added;
    }

    public function removeLine(InvLandedCost $doc, int $lineId): void
    {
        $this->assertDraft($doc);
        $doc->lines()->whereKey($lineId)->delete();
    }

    /**
     * Pembagian biaya (murni hitung, tidak menyimpan). Selisih pembulatan masuk ke baris terakhir sehingga jumlah bagian = total biaya.
     *
     * @return array{lines: list<array<string, mixed>>, to_stock: float, to_variance: float}
     */
    public function allocate(InvLandedCost $doc, ?float $amount = null): array
    {
        $amount = round($amount ?? (float) $doc->amount, 2);
        $lines = $doc->lines()->get();
        if ($lines->isEmpty()) {
            return ['lines' => [], 'to_stock' => 0.0, 'to_variance' => 0.0];
        }
        $byQty = $doc->alloc_basis === 'QTY';
        $basis = fn (InvLandedCostLine $l) => $byQty ? (float) $l->qty_received : (float) $l->base_value;
        $total = (float) $lines->sum($basis);
        if ($total <= 0) {
            throw new ValuationRefused(t('The lines have no value or quantity to share the cost by.'));
        }
        $received = $lines->groupBy('item_id')->map(fn ($set) => (float) $set->sum('qty_received'));
        $onhand = [];
        $out = [];
        $given = 0.0;
        foreach ($lines->values() as $i => $line) {
            $share = $i === $lines->count() - 1 ? round($amount - $given, 2) : round($amount * $basis($line) / $total, 2);
            $given += $share;
            $onhand[$line->item_id] ??= self::onhand((int) $line->item_id);
            $ratio = $received[$line->item_id] > 0 ? min(1.0, $onhand[$line->item_id] / $received[$line->item_id]) : 0.0;
            $toStock = round($share * $ratio, 2);
            $out[] = ['id' => $line->id_landed_cost_line, 'item_id' => (int) $line->item_id, 'qty_on_hand' => $onhand[$line->item_id], 'allocated' => $share,
                'to_stock' => $toStock, 'to_variance' => round($share - $toStock, 2)];
        }

        return ['lines' => $out, 'to_stock' => round(array_sum(array_column($out, 'to_stock')), 2), 'to_variance' => round(array_sum(array_column($out, 'to_variance')), 2)];
    }

    public function confirm(InvLandedCost $doc, string $login): FinVoucher
    {
        $this->assertDraft($doc);
        if ((float) $doc->amount <= 0 || $doc->lines()->doesntExist()) {
            throw new ValuationRefused(t('Enter the cost amount and add at least one receipt line before Confirm.'));
        }
        $this->assertDate($doc);

        $plan = $this->allocate($doc);
        $lines = $this->journalLines($doc, $plan['lines']);
        $voucher = null;
        DB::connection('tm2inv')->transaction(function () use ($doc, $plan, $lines, $login, &$voucher) {
            $no = 'LC'.date('y').'-';
            $last = InvLandedCost::query()->where('doc_no', 'like', $no.'%')->lockForUpdate()->max('doc_no');
            $doc->doc_no = $no.str_pad((string) (($last ? (int) substr($last, strlen($no)) : 0) + 1), 5, '0', STR_PAD_LEFT);
            $voucher = $this->journals->post('PUR', 'LANDED', (int) $doc->id_landed_cost, 'Landed cost '.$doc->doc_no.($doc->description ? ' — '.$doc->description : ''),
                $lines, $login, $doc->doc_date->toDateString(), ['reference_no' => $doc->doc_no]);
            $this->saveAllocation($doc, $plan);
            $doc->fill(['stat_confirm' => true, 'voucher_id' => $voucher->voucher_id, 'confirmed_at' => now(), 'confirmed_by' => $login, 'card_synced' => false,
                'amount_to_stock' => $plan['to_stock'], 'amount_to_variance' => $plan['to_variance'], 'updated_by' => $login])->save();
        });

        return $voucher;
    }

    // Biaya berubah sesudah Confirm (tagihan forwarder dikoreksi): jurnal lama dibiarkan, jurnal koreksi bertumpuk memuat selisihnya.
    public function revise(InvLandedCost $doc, float $newAmount, string $reason, string $login): ?FinVoucher
    {
        $this->assertConfirmed($doc);
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new ValuationRefused(t('The reason is required, at least 10 characters.'));
        }
        if ($newAmount <= 0) {
            throw new ValuationRefused(t('The new amount must be more than zero. To remove the cost, cancel the document.'));
        }
        $plan = $this->allocate($doc, $newAmount);
        $original = FinVoucher::withoutGlobalScopes()->findOrFail($doc->voucher_id);
        $voucher = null;
        DB::connection('tm2inv')->transaction(function () use ($doc, $plan, $newAmount, $original, $reason, $login, &$voucher) {
            $voucher = $this->reposts->correct($original, $this->journalLines($doc, $plan['lines'], $newAmount), $reason, $login, ['type' => 'LANDED', 'id' => (int) $doc->id_landed_cost, 'no' => $doc->doc_no]);
            $this->saveAllocation($doc, $plan);
            $doc->fill(['amount' => $newAmount, 'amount_to_stock' => $plan['to_stock'], 'amount_to_variance' => $plan['to_variance'], 'updated_by' => $login])->save();
        });

        return $voucher;
    }

    public function cancel(InvLandedCost $doc, string $reason, string $login): FinVoucher
    {
        $this->assertConfirmed($doc);
        if (mb_strlen(trim($reason)) < 10) {
            throw new ValuationRefused(t('The cancellation reason is required, at least 10 characters.'));
        }
        if ($doc->variance_voucher_id) {
            throw new ValuationRefused(t('The variance of this landed cost was already closed to cost of goods sold; it cannot be cancelled.'));
        }
        $original = FinVoucher::withoutGlobalScopes()->findOrFail($doc->voucher_id);
        $reversal = $this->reposts->reverse($original, 'Cancel '.$doc->doc_no.': '.trim($reason), $login, ['type' => 'LANDED', 'id' => (int) $doc->id_landed_cost, 'no' => $doc->doc_no]);
        $doc->fill(['stat_cancel' => true, 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'updated_by' => $login])->save();

        return $reversal;
    }

    // Akhir periode (YYYYMM): selisih landed cost seluruh dokumen Confirmed periode itu dipindah ke HPP. Satu jurnal; tiap dokumen ditandai.
    public function closeVariance(string $periodCode, string $login, ?array $onlyDocIds = null): ?FinVoucher
    {
        if (! preg_match('/^\d{6}$/', $periodCode)) {
            throw new ValuationRefused(t('The period must be written as YYYYMM.'));
        }
        $from = substr($periodCode, 0, 4).'-'.substr($periodCode, 4, 2).'-01';
        $to = date('Y-m-t', strtotime($from));
        $docs = InvLandedCost::query()->where('stat_confirm', 1)->where('stat_cancel', 0)->whereNull('variance_voucher_id')->whereBetween('doc_date', [$from, $to])
            ->where('amount_to_variance', '>', 0)->when($onlyDocIds !== null, fn ($q) => $q->whereIn('id_landed_cost', $onlyDocIds))->get();
        $total = round((float) $docs->sum('amount_to_variance'), 2);
        if ($total <= 0) {
            return null;
        }
        $voucher = $this->journals->post('GEN', 'LANDED_CLOSE', (int) $periodCode, 'Landed cost variance to COGS '.$periodCode, [
            ['account_id' => ValuationAccounts::id('INV_ACC_LANDED_COGS'), 'debit' => $total, 'narration' => 'Selisih landed cost '.$periodCode],
            ['account_id' => ValuationAccounts::id('INV_ACC_LANDED_VARIANCE'), 'credit' => $total, 'narration' => 'Selisih landed cost '.$periodCode],
        ], $login, $to, ['reference_no' => 'LC-CLOSE-'.$periodCode]);
        InvLandedCost::query()->whereIn('id_landed_cost', $docs->pluck('id_landed_cost'))->update(['variance_voucher_id' => $voucher->voucher_id]);

        return $voucher;
    }

    /** @return list<array<string, mixed>> baris jurnal: Dr Persediaan per akun item, Dr Selisih, Cr Hutang biaya */
    private function journalLines(InvLandedCost $doc, array $plan, ?float $amount = null): array
    {
        $byAccount = [];
        foreach ($plan as $p) {
            if ($p['to_stock'] <= 0) {
                continue;
            }
            $account = ValuationAccounts::stockFor($p['item_id']);
            if (! $account) {
                throw new ValuationRefused(tf('Item #:id has no Inventory account (item or category); the landed cost was not posted.', ['id' => $p['item_id']]));
            }
            $byAccount[$account] = ($byAccount[$account] ?? 0.0) + $p['to_stock'];
        }
        $lines = [];
        foreach ($byAccount as $account => $value) {
            $lines[] = ['account_id' => $account, 'debit' => round($value, 2), 'narration' => 'Landed cost '.($doc->doc_no ?: 'draft')];
        }
        $variance = round(array_sum(array_column($plan, 'to_variance')), 2);
        if ($variance > 0) {
            $lines[] = ['account_id' => ValuationAccounts::id('INV_ACC_LANDED_VARIANCE'), 'debit' => $variance, 'narration' => 'Landed cost on stock already issued '.($doc->doc_no ?: 'draft')];
        }
        $lines[] = ['account_id' => ValuationAccounts::id('INV_ACC_LANDED_PAYABLE'), 'credit' => round($amount ?? (float) $doc->amount, 2), 'narration' => 'Landed cost '.($doc->doc_no ?: 'draft')];

        return $lines;
    }

    private function saveAllocation(InvLandedCost $doc, array $plan): void
    {
        foreach ($plan['lines'] as $p) {
            InvLandedCostLine::query()->whereKey($p['id'])->update(['qty_on_hand' => $p['qty_on_hand'], 'allocated' => $p['allocated'], 'to_stock' => $p['to_stock'], 'to_variance' => $p['to_variance']]);
        }
    }

    // Saldo item sekarang di semua bin (kelas RM; landed cost melekat pada penerimaan bahan).
    private static function onhand(int $materialId): float
    {
        return (float) StockOnhand::of('RM')->where('material_id', $materialId)->sum('qty_onhand');
    }

    private function assertDate(InvLandedCost $doc): void
    {
        $closed = InvPeriod::closedThrough();
        if ($closed && $doc->doc_date->lessThanOrEqualTo($closed)) {
            throw new ValuationRefused(tf('The inventory period up to :date is closed; use a later document date.', ['date' => $closed->toDateString()]));
        }
        $received = PvReceipt::query()->whereIn('pv_id', $doc->lines()->pluck('pv_id'))->max('i_doc_date');
        if ($received && $doc->doc_date->toDateString() < substr((string) $received, 0, 10)) {
            throw new ValuationRefused(t('The landed cost date cannot be earlier than the goods receipt date.'));
        }
    }

    private function assertDraft(InvLandedCost $doc): void
    {
        if ($doc->status() !== 'DRAFT') {
            throw new ValuationRefused(t('Only a Draft landed cost can be changed.'));
        }
    }

    private function assertConfirmed(InvLandedCost $doc): void
    {
        if ($doc->status() !== 'CONFIRMED') {
            throw new ValuationRefused(t('Only a Confirmed landed cost can be revised or cancelled.'));
        }
    }
}
