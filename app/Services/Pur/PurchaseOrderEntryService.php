<?php

/*
 * REMARK
 * Tujuan       : Aturan entri Purchase Order (dipindah apa adanya dari komponen v1 pages::pur.po.entry): ubah header / harga + diskon baris, tarik alokasi PR, hitung ulang pajak, submit ke flow approval, keputusan approver bertingkat, Kirim ke Supplier, hapus draft (alokasi PR dilepas).
 * Sumber hulu  : PoOrder (recalcTax: PPN pada tanggal PO), PoLine, PrLineAlloc, PoBuilderService::addAllocation, FlowEngineService (modul PO atau POC untuk PO Kontan).
 * Input        : id PO + nilai field / id baris / id alokasi / catatan keputusan.
 * Output       : po_order, po_line, pr_line_alloc, flow_log. Metode berpesan mengembalikan teks galat ('' = sukses).
 * Dipakai oleh : App\Support\V2\Data\PurchaseOrder (layar v2 purchase-order), tests/Feature/PurchaseOrderEntryRulesTest.
 * Status       : Aktif (T10 tahap 7a, 2026-10-22). Menggantikan komponen Livewire pages::pur.po.entry.
 */

namespace App\Services\Pur;

use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrLineAlloc;
use App\Services\Flow\FlowEngineService;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;

final class PurchaseOrderEntryService
{
    public const HEADER_FIELDS = ['i_doc_date', 'i_po_type', 'i_procure_type', 'i_payment_term', 'i_delivery_date', 'i_ship_to', 'i_notes'];

    public const LINE_FIELDS = ['i_price', 'i_disc_pct'];

    private static function load(int $poId): PoOrder
    {
        return PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($poId);
    }

    // Baris flow_log TERAKHIR untuk PO ini kalau masih pending di posisi user login (SUBMITTED atau HOLD); null = bukan approver-nya / sudah diputuskan.
    private static function pendingApproval(PoOrder $po): ?object
    {
        return app(FlowEngineService::class)->pendingForUser($po->flowModule(), Auth::id())->firstWhere('doc_id', $po->po_id);
    }

    private static function recalcTax(int $poId): void
    {
        $po = PoOrder::with('lines')->findOrFail($poId);
        $po->recalcTax();
        $po->save();
    }

    public static function addAllocation(int $poId, int $allocId): void
    {
        $po = self::load($poId);
        if ($po->status !== 'DRAFT') {
            return;
        }

        $alloc = PrLineAlloc::findOrFail($allocId);
        PoBuilderService::addAllocation($po, $alloc);

        self::recalcTax($poId);
    }

    public static function updateLine(int $poId, int $lineId, string $field, mixed $value): void
    {
        if (self::load($poId)->status !== 'DRAFT') {
            return;
        }

        if (! in_array($field, self::LINE_FIELDS, true)) {
            return;
        }

        $line = PoLine::where('po_id', $poId)->findOrFail($lineId);
        $line->{$field} = $value === '' ? 0 : $value;
        $line->recalcNet();
        $line->updated_by = Auth::user()->login;
        $line->save();

        self::recalcTax($poId);
    }

    public static function updateHeader(int $poId, string $field, mixed $value): void
    {
        $po = self::load($poId);
        if ($po->status !== 'DRAFT') {
            return;
        }

        if (! in_array($field, self::HEADER_FIELDS, true)) {
            return;
        }

        $po->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
    }

    /** Confirm = Submit: nomor dokumen lahir + masuk antrian approval. @return string pesan bila flow belum dikonfigurasi ('' = beres) */
    public static function submit(int $poId): string
    {
        $po = self::load($poId);
        if ($po->status !== 'DRAFT' || $po->lines->isEmpty()) {
            return '';
        }

        self::recalcTax($poId);
        $login = Auth::user()->login;
        $po = PoOrder::findOrFail($poId);

        $po->update([
            'doc_no' => PoOrder::nextDocNo(),
            'status' => 'SUBMITTED',
            'updated_by' => $login,
        ]);

        $result = app(FlowEngineService::class)->submit($po->flowModule(), $poId, 'SUBMITTED', (float) $po->c_grand_total);

        return $result === null
            ? Translator::t('proc_po_entry_no_flow_config', 'Tidak ada konfigurasi approval aktif untuk modul PO. Dokumen tersimpan sebagai SUBMITTED tapi belum masuk antrian approval siapapun — hubungi Admin.')
            : '';
    }

    // APPROVED -> Kirim ke Supplier -> SENT (aksi manual, bukan otomatis saat approve terakhir -- officer yang menegaskan PO benar-benar
    // sudah dikirim). Status SENT ini yang jadi syarat PO muncul di worklist PV.
    public static function sendToSupplier(int $poId): void
    {
        $po = self::load($poId);
        if ($po->status !== 'APPROVED') {
            return;
        }

        $po->update(['status' => 'SENT', 'sent_at' => now(), 'updated_by' => Auth::user()->login]);
    }

    /**
     * Keputusan approver tahap berjalan. Hold tidak mengubah status (PO tetap SUBMITTED di antrian approver ini).
     *
     * @param  'approve'|'reject'|'hold'  $action
     * @return string pesan galat ('' = sukses atau tidak ada yang diputuskan)
     */
    public static function decide(int $poId, string $action, string $note): string
    {
        $po = self::load($poId);
        $pending = self::pendingApproval($po);
        if (! $pending) {
            return '';
        }

        $flow = app(FlowEngineService::class);

        if ($action === 'approve') {
            $result = $flow->approve($po->flowModule(), $poId, $pending->flow_step_id, (float) $pending->amount_snapshot);
            $po->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

            return '';
        }

        if ($action === 'reject') {
            if (trim($note) === '') {
                return Translator::t('proc_entry_reject_reason_required', 'Alasan penolakan wajib diisi.');
            }

            $result = $flow->reject($po->flowModule(), $poId, $pending->flow_step_id, $note, (float) $pending->amount_snapshot);
            $po->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

            return '';
        }

        if (trim($note) === '') {
            return Translator::t('proc_entry_hold_reason_required', 'Catatan alasan Hold wajib diisi.');
        }

        $flow->hold($po->flowModule(), $poId, $pending->flow_step_id, $note, (float) $pending->amount_snapshot);

        return '';
    }

    public static function delete(int $poId): void
    {
        $po = self::load($poId);
        if ($po->status !== 'DRAFT') {
            return;
        }

        // Lepas alokasi yang sudah ditarik supaya bisa dipakai PO lain lagi.
        PrLineAlloc::whereIn('alloc_id', $po->lines->pluck('alloc_id')->filter())
            ->update(['c_is_converted' => false]);

        $po->delete();
    }
}
