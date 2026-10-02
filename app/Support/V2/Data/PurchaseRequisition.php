<?php

namespace App\Support\V2\Data;

use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrRequisition;
use App\Services\Flow\FlowEngineService;
use App\Support\V2\Data\Concerns\FlowDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

// Purchase Requisition v2 = aturan & tulis data komponen v1 pages::pur.pr.entry (updateHeader / updateLine / addMasterItem /
// addFreeText / submit / approveDoc / rejectDoc / holdDoc), supaya v1 & v2 memakai satu sumber aturan.
final class PurchaseRequisition
{
    use FlowDocument;

    public const HEADER = ['i_doc_date', 'i_department_id', 'i_need_date', 'i_notes'];

    public const LINE = ['i_description', 'i_qty', 'i_uom', 'i_est_price', 'i_need_date', 'i_notes', 'i_coa_account_id'];

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'APPROVED' => 'success', 'PARTIALLY_ORDERED' => 'info',
        'FULLY_ORDERED' => 'success', 'CLOSED' => 'neutral', 'REJECTED' => 'danger', 'REVISION_NEEDED' => 'danger', 'CANCELLED' => 'danger'];

    public static function component(int $id): Component
    {
        $c = app('livewire')->new('pages::pur.pr.entry');
        $c->prId = $id;

        return $c;
    }

    public static function rows(): array
    {
        $prs = PrRequisition::with('lines')->orderByDesc('pr_id')->limit(500)->get();
        $dept = OrgDepartment::whereIn('department_id', $prs->pluck('i_department_id')->unique())->pluck('department_name', 'department_id');

        return $prs->map(fn ($p) => ['id' => $p->pr_id, 'no' => $p->doc_no ?: '(draft #'.$p->pr_id.')', 'dept' => $dept[$p->i_department_id] ?? '—',
            'date' => $p->i_doc_date?->toDateString(), 'need' => $p->i_need_date?->toDateString(), 'lines' => $p->lines->count(),
            // Baris anak grid (Expand): nama item selalu tampil, tanpa harga.
            'items' => $p->lines->map(fn ($l) => ['code' => $l->c_item_code, 'name' => $l->i_description, 'qty' => (float) $l->i_qty, 'unit' => $l->i_uom,
                'need' => $l->i_need_date?->toDateString(), 'ordered' => (float) $l->c_qty_ordered, 'note' => $l->i_notes])->all(),
            'total' => (float) $p->c_est_total, 'status' => $p->status])->all();
    }

    public static function departments(): array
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id')->all();
    }

    // Akun yang bisa diposting -- daftar yang sama dengan coaAccounts komponen v1 (baris PR bisa beban maupun aset).
    public static function accounts(): array
    {
        return DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->orderBy('account_code')
            ->get(['account_id', 'account_code', 'account_name'])->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    public static function create(int $departmentId): PrRequisition
    {
        $login = Auth::user()->login;

        return PrRequisition::create(['i_doc_date' => now()->toDateString(), 'i_source' => 'MANUAL', 'i_department_id' => $departmentId,
            'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
    }

    // Header + baris dari satu form; hanya nilai yang berubah yang dikirim ke komponen v1.
    public static function save(PrRequisition $pr, array $in): void
    {
        abort_unless($pr->status === 'DRAFT', 422, t('The PR is already submitted and cannot be changed.'));
        $c = self::component($pr->pr_id);
        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $in) && self::str($in[$f]) !== self::str($pr->{$f})) {
                $c->updateHeader($f, self::str($in[$f]));
            }
        }
        foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
            $c->removeLine((int) $id);
        }
        $lines = $pr->lines()->get()->keyBy('pr_line_id');
        foreach ((array) ($in['lines'] ?? []) as $id => $row) {
            $line = $lines->get((int) $id);
            if (! $line) {
                continue;
            }
            foreach (self::LINE as $f) {
                if (! array_key_exists($f, (array) $row)) {
                    continue;
                }
                $v = in_array($f, ['i_qty', 'i_est_price'], true) ? self::num($row[$f]) : self::str($row[$f]);
                $cur = self::str($line->{$f});
                if ($v !== $cur && ! (is_numeric($v) && is_numeric($cur) && (float) $v === (float) $cur)) {
                    $c->updateLine((int) $id, $f, $v);
                }
            }
        }
    }

    public static function addMaterial(PrRequisition $pr, int $materialId): void
    {
        self::component($pr->pr_id)->addMasterItem($materialId);
    }

    // Balasan = pesan bila ditolak (akun belum dipilih / bukan akun yang bisa diposting); kosong = baris tersimpan.
    public static function addFree(PrRequisition $pr, string $description, string $uom, ?int $accountId): string
    {
        $c = self::component($pr->pr_id);
        $c->freeDescription = $description;
        $c->freeUom = $uom;
        $c->freeAccountId = (string) $accountId;
        $c->addFreeText();

        return $c->freeError;
    }

    // Confirm = Submit v1: nomor dokumen lahir + masuk antrian approval flow engine. Balasan = pesan bila flow belum dikonfigurasi.
    public static function submit(PrRequisition $pr): string
    {
        $c = self::component($pr->pr_id);
        $c->submit(app(FlowEngineService::class));

        return $c->blockedMessage;
    }

    // approve | reject | hold oleh approver tahap berjalan (pendingApproval v1). Balasan = pesan error (kosong = sukses).
    public static function decide(PrRequisition $pr, string $action, string $note): string
    {
        $c = self::component($pr->pr_id);
        $c->approvalNote = $note;
        match ($action) {
            'approve' => $c->approveDoc(app(FlowEngineService::class)),
            'reject' => $c->rejectDoc(app(FlowEngineService::class)),
            'hold' => $c->holdDoc(app(FlowEngineService::class)),
        };

        return $c->approvalError;
    }

    public static function pending(PrRequisition $pr): ?object
    {
        return self::pendingFor('PR', $pr->pr_id);
    }

    public static function log(PrRequisition $pr): array
    {
        return self::flowLog('PR', $pr->pr_id);
    }

    public static function delete(PrRequisition $pr): void
    {
        abort_unless($pr->status === 'DRAFT', 422, t('Only a DRAFT PR can be deleted.'));
        $pr->delete();
    }

    // Status v1 -> status Form DevLib (Draft > Confirmed > Approval > Approved > Closed).
    public static function formStatus(PrRequisition $pr, bool $held): string
    {
        return match ($pr->status) {
            'DRAFT' => 'draft', 'SUBMITTED' => $held ? 'hold' : 'approval', 'APPROVED', 'PARTIALLY_ORDERED' => 'approved',
            'FULLY_ORDERED', 'CLOSED' => 'closed', 'REJECTED', 'REVISION_NEEDED' => 'rejected', 'CANCELLED' => 'cancelled', default => 'draft',
        };
    }
}
