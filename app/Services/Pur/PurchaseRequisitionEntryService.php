<?php

/*
 * REMARK
 * Tujuan       : Aturan entri Purchase Requisition (dipindah apa adanya dari komponen v1 pages::pur.pr.entry): ubah header / baris, hapus baris, tambah item master / teks bebas, submit ke flow approval, keputusan approver (approve / reject / hold). Harga taksiran baris bahan master = saran PaperPriceEngine.
 * Sumber hulu  : PrRequisition, PrLine, MatRaw, MatCategory, fin_account (tm2acc, akun yang bisa diposting), FlowEngineService, PrAllocationService, PaperPriceEngine.
 * Input        : id PR + nilai field / id baris / id bahan / catatan keputusan.
 * Output       : pr_requisition, pr_line, pr_line_alloc (saat approval final), flow_log. Metode berpesan mengembalikan teks galat ('' = sukses).
 * Dipakai oleh : App\Support\V2\Data\PurchaseRequisition (layar v2 purchase-requisition), tests/Feature/PurchaseRequisitionEntryRulesTest.
 * Status       : Aktif (T10 tahap 7a, 2026-10-22). Menggantikan komponen Livewire pages::pur.pr.entry.
 */

namespace App\Services\Pur;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Services\Flow\FlowEngineService;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class PurchaseRequisitionEntryService
{
    public const HEADER_FIELDS = ['i_doc_date', 'i_department_id', 'i_need_date', 'i_notes'];

    public const LINE_FIELDS = ['i_description', 'i_qty', 'i_uom', 'i_est_price', 'i_department_id', 'i_coa_account_id', 'i_need_date', 'i_notes'];

    // Kolom NOT NULL: pilihan kosong / isian yang dikosongkan diabaikan (nilai lama tetap), bukan disimpan NULL.
    private const REQUIRED_LINE_FIELDS = ['i_description', 'i_qty', 'i_uom', 'i_department_id', 'i_coa_account_id'];

    private static function load(int $prId): PrRequisition
    {
        return PrRequisition::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($prId);
    }

    // Baris flow_log TERAKHIR untuk PR ini kalau masih pending di posisi user login (SUBMITTED atau HOLD); null = bukan approver-nya / sudah diputuskan.
    private static function pendingApproval(int $prId): ?object
    {
        return app(FlowEngineService::class)->pendingForUser('PR', Auth::id())->firstWhere('doc_id', $prId);
    }

    // Pengganti FK lintas-DB ke tm2acc.fin_account (is_postable=1).
    private static function isPostableAccount(mixed $accountId): bool
    {
        return (int) $accountId > 0 && DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->where('account_id', (int) $accountId)->exists();
    }

    private static function recalcTotal(int $prId): void
    {
        PrRequisition::whereKey($prId)->update(['c_est_total' => PrLine::where('pr_id', $prId)->sum('c_est_subtotal')]);
    }

    public static function addMasterItem(int $prId, int $materialId): void
    {
        $pr = self::load($prId);
        if ($pr->status !== 'DRAFT') {
            return;
        }

        $material = MatRaw::findOrFail($materialId);
        $prefix1 = MatCategory::level(1)->where('category_name', $material->category1)->first();
        $login = Auth::user()->login;

        $nextLineNo = ((int) $pr->lines()->max('line_no')) + 1;

        $line = PrLine::create([
            'pr_id' => $prId,
            'line_no' => $nextLineNo,
            'i_item_source' => 'MASTER_ITEM',
            'item_id' => $material->material_id,
            'c_item_code' => $material->material_code,
            'i_description' => $material->material_name,
            'i_qty' => 1,
            'i_uom' => $material->uom_purchase ?: 'PCS',
            'i_est_price' => app(PaperPriceEngine::class)->priceFor($material, $material->suggest_supplier_id) ?? $material->last_unitprice,
            'i_department_id' => $pr->i_department_id,
            'i_coa_account_id' => $prefix1?->default_coa_account_id,
            'is_stock' => $prefix1?->stock_class !== 'SERVICE',
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $line->recalcSubtotal();
        $line->save();

        self::recalcTotal($prId);
    }

    /** @return string pesan galat bila ditolak (akun belum dipilih / bukan akun yang bisa diposting); '' = baris tersimpan atau diabaikan */
    public static function addFreeText(int $prId, string $description, string $uom, mixed $accountId): string
    {
        $pr = self::load($prId);

        if ($pr->status !== 'DRAFT' || trim($description) === '') {
            return '';
        }

        // pr_line.i_coa_account_id NOT NULL, dan teks bebas tak punya kategori yang menuntun ke akun (beda dgn Item Master) --
        // jadi akun dipilih saat menambah, bukan sesudah baris tersimpan.
        if (! self::isPostableAccount($accountId)) {
            return t('Choose the account for the free-text item.');
        }

        $login = Auth::user()->login;
        $nextLineNo = ((int) $pr->lines()->max('line_no')) + 1;

        PrLine::create([
            'pr_id' => $prId,
            'line_no' => $nextLineNo,
            'i_item_source' => 'FREE_TEXT',
            'item_id' => null,
            'i_description' => $description,
            'i_qty' => 1,
            'i_uom' => $uom ?: '-',
            'i_department_id' => $pr->i_department_id,
            'i_coa_account_id' => (int) $accountId,
            'is_stock' => false,
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        self::recalcTotal($prId);

        return '';
    }

    public static function updateLine(int $prId, int $lineId, string $field, mixed $value): void
    {
        if (self::load($prId)->status !== 'DRAFT') {
            return;
        }

        if (! in_array($field, self::LINE_FIELDS, true)) {
            return;
        }

        if (($value === '' || $value === null) && in_array($field, self::REQUIRED_LINE_FIELDS, true)) {
            return;
        }

        if ($field === 'i_coa_account_id' && ! self::isPostableAccount($value)) {
            return;
        }

        $line = PrLine::where('pr_id', $prId)->findOrFail($lineId);
        $line->{$field} = $value === '' ? null : $value;
        $line->recalcSubtotal();
        $line->updated_by = Auth::user()->login;
        $line->save();

        self::recalcTotal($prId);
    }

    public static function removeLine(int $prId, int $lineId): void
    {
        if (self::load($prId)->status !== 'DRAFT') {
            return;
        }

        PrLine::where('pr_id', $prId)->where('pr_line_id', $lineId)->delete();
        self::recalcTotal($prId);
    }

    public static function updateHeader(int $prId, string $field, mixed $value): void
    {
        $pr = self::load($prId);
        if ($pr->status !== 'DRAFT') {
            return;
        }

        if (! in_array($field, self::HEADER_FIELDS, true)) {
            return;
        }

        $pr->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
    }

    /** Confirm = Submit: nomor dokumen lahir + masuk antrian approval. @return string pesan bila flow belum dikonfigurasi ('' = beres) */
    public static function submit(int $prId): string
    {
        $pr = self::load($prId);
        if ($pr->status !== 'DRAFT' || $pr->lines->isEmpty() || ! $pr->i_department_id) {
            return '';
        }

        self::recalcTotal($prId);
        $login = Auth::user()->login;
        $amount = (float) PrRequisition::whereKey($prId)->value('c_est_total');

        $pr->update([
            'doc_no' => PrRequisition::nextDocNo(),
            'status' => 'SUBMITTED',
            'updated_by' => $login,
        ]);

        $result = app(FlowEngineService::class)->submit('PR', $prId, 'SUBMITTED', $amount);

        return $result === null
            ? Translator::t('proc_pr_entry_no_flow_config', 'Tidak ada konfigurasi approval aktif untuk modul PR. Dokumen tersimpan sebagai SUBMITTED tapi belum masuk antrian approval siapapun — hubungi Admin.')
            : '';
    }

    /**
     * Keputusan approver tahap berjalan. Hold tidak mengubah status (PR tetap SUBMITTED di antrian approver ini).
     *
     * @param  'approve'|'reject'|'hold'  $action
     * @return string pesan galat ('' = sukses atau tidak ada yang diputuskan)
     */
    public static function decide(int $prId, string $action, string $note): string
    {
        $pending = self::pendingApproval($prId);
        if (! $pending) {
            return '';
        }

        $flow = app(FlowEngineService::class);

        if ($action === 'approve') {
            $result = $flow->approve('PR', $prId, $pending->flow_step_id, (float) $pending->amount_snapshot);

            $pr = self::load($prId);
            $pr->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

            // Flow selesai (tak ada step lanjutan) -> siapkan alokasi default per baris supaya PO Entry (ambil dari pr_line_alloc) selalu punya sumber.
            if ($result['final']) {
                PrAllocationService::createDefaultAllocationsForPr($pr);
            }

            return '';
        }

        if ($action === 'reject') {
            if (trim($note) === '') {
                return Translator::t('proc_entry_reject_reason_required', 'Alasan penolakan wajib diisi.');
            }

            $result = $flow->reject('PR', $prId, $pending->flow_step_id, $note, (float) $pending->amount_snapshot);

            self::load($prId)->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

            return '';
        }

        if (trim($note) === '') {
            return Translator::t('proc_entry_hold_reason_required', 'Catatan alasan Hold wajib diisi.');
        }

        $flow->hold('PR', $prId, $pending->flow_step_id, $note, (float) $pending->amount_snapshot);

        return '';
    }
}
