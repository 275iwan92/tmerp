<?php

namespace App\Support\V2\Data;

use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrRequisition;
use App\Services\Pur\PurchaseRequisitionEntryService as Entry;
use App\Support\V2\Data\Concerns\FlowDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Purchase Requisition v2 = tulis data lewat App\Services\Pur\PurchaseRequisitionEntryService (updateHeader / updateLine / addMasterItem /
// addFreeText / submit / decide), aturan yang dulu di komponen v1 pages::pur.pr.entry (diarsipkan T10 tahap 7a).
final class PurchaseRequisition
{
    use FlowDocument;

    public const HEADER = ['i_doc_date', 'i_department_id', 'i_need_date', 'i_notes'];

    public const LINE = ['i_description', 'i_qty', 'i_uom', 'i_est_price', 'i_need_date', 'i_notes', 'i_coa_account_id'];

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'APPROVED' => 'success', 'PARTIALLY_ORDERED' => 'info',
        'FULLY_ORDERED' => 'success', 'CLOSED' => 'neutral', 'REJECTED' => 'danger', 'REVISION_NEEDED' => 'danger', 'CANCELLED' => 'danger'];

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

    // Header + baris dari satu form; hanya nilai yang berubah yang dikirim ke service.
    public static function save(PrRequisition $pr, array $in): void
    {
        abort_unless($pr->status === 'DRAFT', 422, t('The PR is already submitted and cannot be changed.'));
        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $in) && self::str($in[$f]) !== self::str($pr->{$f})) {
                Entry::updateHeader($pr->pr_id, $f, self::str($in[$f]));
            }
        }
        foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
            Entry::removeLine($pr->pr_id, (int) $id);
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
                    Entry::updateLine($pr->pr_id, (int) $id, $f, $v);
                }
            }
        }
    }

    public static function addMaterial(PrRequisition $pr, int $materialId): void
    {
        Entry::addMasterItem($pr->pr_id, $materialId);
    }

    // Balasan = pesan bila ditolak (akun belum dipilih / bukan akun yang bisa diposting); kosong = baris tersimpan.
    public static function addFree(PrRequisition $pr, string $description, string $uom, ?int $accountId): string
    {
        return Entry::addFreeText($pr->pr_id, $description, $uom, $accountId);
    }

    // Confirm = Submit v1: nomor dokumen lahir + masuk antrian approval flow engine. Balasan = pesan bila flow belum dikonfigurasi.
    public static function submit(PrRequisition $pr): string
    {
        return Entry::submit($pr->pr_id);
    }

    // approve | reject | hold oleh approver tahap berjalan (pendingApproval v1). Balasan = pesan error (kosong = sukses).
    public static function decide(PrRequisition $pr, string $action, string $note): string
    {
        return Entry::decide($pr->pr_id, $action, $note);
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
