<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerContact;
use App\Models\Master\ProdProduct;
use App\Models\Prd\QcComplaintReview;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CrmActivity;
use App\Models\Sdi\CrmComplaint;
use App\Models\Sdi\CrmComplaintItem;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// CRM > Complaint & Return (user 2026-10-02: informasi retur / komplain ke Sales, mutu ke QA).
// Sales: Draft -> Confirm (nomor lahir; kategori mutu diteruskan ke QA) -> Close (penyelesaian ke customer) | Cancel (alasan).
// QA (layar Production > Complaint Review): menjawab komplain Confirmed yang needs_qa -> Confirm = jawaban final.
// Kontrak antar modul: Sales tidak mengubah jawaban QA, QA tidak mengubah dokumen Sales; Close menunggu jawaban QA.
// Barang yang kembali ke gudang (stok) belum ditangani di sini: qty_return hanya informasi.
final class CrmComplaints
{
    public const STATUS_TONES = ['DRAFT' => 'neutral', 'CONFIRMED' => 'success', 'CLOSED' => 'info', 'CANCELLED' => 'danger'];

    public const QA_LABELS = ['NONE' => '—', 'WAITING' => 'Waiting QA', 'ANSWERED' => 'QA answered'];

    public const QA_TONES = ['—' => 'neutral', 'Waiting QA' => 'warn', 'QA answered' => 'success'];

    public static function find(int $id): ?CrmComplaint
    {
        return CrmComplaint::query()->with(['customer', 'items', 'review', 'order'])->find($id);
    }

    public static function rows(?int $customerId = null): array
    {
        return CrmComplaint::query()->with(['customer', 'items', 'review'])->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderByDesc('id_complaint')->limit(1000)->get()->map(fn (CrmComplaint $c) => self::row($c))->all();
    }

    private static function row(CrmComplaint $c): array
    {
        return [
            'id' => $c->id_complaint, 'no' => $c->complaint_no ?: 'DRAFT-'.$c->id_complaint, 'customer' => $c->customer?->customer_name ?? '—',
            'date' => $c->complaint_date?->toDateString(), 'type' => t(CrmComplaint::TYPES[$c->complaint_type] ?? $c->complaint_type),
            'category' => t(CrmComplaint::CATEGORIES[$c->category] ?? $c->category), 'request' => $c->requested_action ? t(CrmComplaint::REQUESTS[$c->requested_action] ?? $c->requested_action) : null,
            'ref' => $c->order?->co_no ?: $c->ref_doc_no, 'qa' => $c->stat_confirm && ! $c->stat_cancel ? self::QA_LABELS[$c->qaStage()] : '—',
            'verdict' => $c->review?->stat_confirm ? t(QcComplaintReview::VERDICTS[$c->review->verdict] ?? '') : null,
            'resolution' => $c->resolution ? t(CrmComplaint::RESOLUTIONS[$c->resolution] ?? $c->resolution) : null, 'status' => $c->status(),
            'items' => $c->items->map(fn (CrmComplaintItem $i) => ['product' => $i->product_name, 'qty' => (float) $i->qty_complaint, 'returned' => (float) $i->qty_return,
                'unit' => $i->qty_unit, 'defect' => $i->defect_note])->values()->all(),
        ];
    }

    public static function contactOptions(CrmComplaint $c): array
    {
        return CrmActivities::contactOptions((int) $c->customer_id);
    }

    // Customer Order customer ini (tidak dibatalkan). Order lama (arsip) ditulis di Reference Doc No.
    public static function orderOptions(CrmComplaint $c): array
    {
        return CoOrder::query()->where('customer_id', $c->customer_id)->where('stat_cancel', 0)->whereNotNull('co_no')->orderByDesc('id_order')->limit(200)->pluck('co_no', 'id_order')->all();
    }

    public static function create(int $customerId, string $type): CrmComplaint
    {
        $cust = CustCustomer::query()->findOrFail($customerId);
        $login = Auth::user()->login;

        return CrmComplaint::create(['complaint_date' => now()->toDateString(), 'complaint_type' => isset(CrmComplaint::TYPES[$type]) ? $type : 'COMPLAINT',
            'customer_id' => $cust->id_customer, 'category' => 'QUALITY', 'needs_qa' => true, 'created_by' => $login, 'updated_by' => $login]);
    }

    // Draft: seluruh isi + baris. Confirmed: hanya penyelesaian (resolution + catatan), sisanya terkunci.
    public static function save(CrmComplaint $c, array $in): void
    {
        $login = Auth::user()->login;
        $text = fn (string $k, int $max) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max) ?: null;
        if ($c->status() === 'CONFIRMED') {
            $c->fill(['resolution' => isset(CrmComplaint::RESOLUTIONS[$in['resolution'] ?? '']) ? $in['resolution'] : null, 'resolution_note' => $text('resolution_note', 255), 'updated_by' => $login])->save();

            return;
        }
        abort_unless($c->status() === 'DRAFT', 422, 'This complaint is closed and cannot be changed.');
        $contacts = self::contactOptions($c);
        $contactId = isset($contacts[(int) ($in['contact_id'] ?? 0)]) ? (int) $in['contact_id'] : null;
        $orders = self::orderOptions($c);
        $category = isset(CrmComplaint::CATEGORIES[$in['category'] ?? '']) ? $in['category'] : 'OTHER';
        $c->fill([
            'complaint_date' => trim((string) ($in['complaint_date'] ?? '')) ?: $c->complaint_date,
            'complaint_type' => isset(CrmComplaint::TYPES[$in['complaint_type'] ?? '']) ? $in['complaint_type'] : $c->complaint_type,
            'contact_id' => $contactId,
            'contact_person' => $text('contact_person', 150) ?: ($contactId ? CustCustomerContact::query()->whereKey($contactId)->value('contact_name') : null),
            'received_via' => isset(CrmActivity::TYPES[$in['received_via'] ?? '']) ? $in['received_via'] : null,
            'category' => $category, 'needs_qa' => $category === 'QUALITY' || ! empty($in['needs_qa']),
            'co_order_id' => isset($orders[(int) ($in['co_order_id'] ?? 0)]) ? (int) $in['co_order_id'] : null, 'ref_doc_no' => $text('ref_doc_no', 60),
            'description' => trim((string) ($in['description'] ?? '')) ?: null,
            'requested_action' => isset(CrmComplaint::REQUESTS[$in['requested_action'] ?? '']) ? $in['requested_action'] : null,
            'updated_by' => $login,
        ]);
        $products = ProdProduct::query()->whereKey(collect((array) ($in['items'] ?? []))->pluck('product_id')->filter()->map(fn ($v) => (int) $v)->all())
            ->get(['id_product', 'product_name', 'product_code'])->keyBy('id_product');

        DB::connection('tm2sdi')->transaction(function () use ($c, $in, $login, $products) {
            $c->save();
            $items = $c->items()->get()->keyBy('id_complaint_item');
            foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
                $items->get((int) $id)?->delete();
            }
            $next = (int) $items->max('line_no');
            foreach ((array) ($in['items'] ?? []) as $id => $row) {
                $row = (array) $row;
                if (isset($in['remove'][$id])) {
                    continue;
                }
                $item = is_numeric($id) ? $items->get((int) $id) : null;
                $product = $products->get((int) ($row['product_id'] ?? 0));
                $name = mb_substr(trim((string) ($row['product_name'] ?? '')), 0, 255) ?: (string) $product?->product_name;
                if (! $item) {
                    if ($name === '') {
                        continue;
                    }
                    $item = new CrmComplaintItem(['complaint_id' => $c->id_complaint, 'line_no' => ++$next, 'created_by' => $login]);
                }
                $item->fill([
                    'product_id' => $product?->id_product, 'product_name' => $name !== '' ? $name : $item->product_name,
                    'qty_complaint' => (float) (Locale::input($row['qty_complaint'] ?? 0) ?? 0), 'qty_return' => (float) (Locale::input($row['qty_return'] ?? 0) ?? 0),
                    'qty_unit' => mb_substr(trim((string) ($row['qty_unit'] ?? '')), 0, 20) ?: null,
                    'defect_note' => mb_substr(trim((string) ($row['defect_note'] ?? '')), 0, 255) ?: null, 'updated_by' => $login,
                ])->save();
            }
        });
    }

    public static function confirmErrors(CrmComplaint $c): array
    {
        $c->load('items');
        $errors = [];
        if (trim((string) $c->description) === '') {
            $errors[] = 'Describe what the customer complains about.';
        }
        if ($c->items->isEmpty()) {
            $errors[] = 'Add at least one product.';
        }
        if ($c->items->contains(fn (CrmComplaintItem $i) => (float) $i->qty_complaint <= 0)) {
            $errors[] = 'Every product needs the quantity complained about.';
        }
        if ($c->complaint_type === 'RETURN' && $c->items->sum(fn (CrmComplaintItem $i) => (float) $i->qty_return) <= 0) {
            $errors[] = 'A Sales Return needs the quantity that came back.';
        }
        if ($c->items->contains(fn (CrmComplaintItem $i) => (float) $i->qty_return > (float) $i->qty_complaint)) {
            $errors[] = 'Returned quantity cannot be more than the quantity complained about.';
        }

        return $errors;
    }

    // confirm | cancel | close -> pesan salah, null = berhasil.
    public static function act(CrmComplaint $c, string $action, string $reason = ''): ?string
    {
        $login = Auth::user()->login;
        if ($action === 'confirm') {
            if ($c->status() !== 'DRAFT') {
                return 'Only a Draft can be confirmed.';
            }
            if ($errors = self::confirmErrors($c)) {
                return implode(' ', array_map(fn (string $e) => t($e), $errors));
            }
            DB::connection('tm2sdi')->transaction(fn () => $c->forceFill(['complaint_no' => CrmComplaint::nextNo(), 'stat_confirm' => true, 'confirmed_at' => now(),
                'confirmed_by' => $login, 'updated_by' => $login])->save());

            return null;
        }
        if ($c->status() !== 'CONFIRMED') {
            return 'Only a Confirmed complaint can be cancelled or closed.';
        }
        if ($action === 'close') {
            if ($c->qaStage() === 'WAITING') {
                return 'QA has not answered this complaint yet.';
            }
            if (! $c->resolution) {
                return 'Choose the resolution given to the customer before closing.';
            }
            $c->forceFill(['stat_close' => true, 'closed_at' => now(), 'closed_by' => $login, 'updated_by' => $login])->save();

            return null;
        }
        if (trim($reason) === '') {
            return 'Fill in the reason for cancelling.';
        }
        $c->forceFill(['stat_cancel' => true, 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'updated_by' => $login])->save();

        return null;
    }

    // Delete = hapus permanen, hanya Draft (rulebook C.6); baris ikut terhapus (FK cascade).
    public static function delete(CrmComplaint $c): void
    {
        abort_unless($c->status() === 'DRAFT', 422, 'Only a Draft can be deleted.');
        $c->delete();
    }

    // Status untuk x-dl::form: Confirmed yang belum Closed = 'working' (isi terkunci per field, penyelesaian masih bisa diisi).
    public static function formStatus(CrmComplaint $c): string
    {
        return ['DRAFT' => 'draft', 'CONFIRMED' => 'working', 'CLOSED' => 'closed', 'CANCELLED' => 'cancelled'][$c->status()];
    }

    public static function log(CrmComplaint $c): array
    {
        $name = fn (?string $login) => $login ? (CrmActivities::people(collect([$login]))[$login] ?? $login) : null;

        return array_values(array_filter([
            $c->stat_cancel ? ['at' => $c->updated_at?->toDateTimeString(), 'by' => $name($c->updated_by), 'action' => 'Cancelled', 'note' => $c->cancel_reason] : null,
            $c->stat_close ? ['at' => $c->closed_at?->toDateTimeString(), 'by' => $name($c->closed_by), 'action' => 'Closed', 'note' => $c->resolution_note] : null,
            $c->review?->stat_confirm ? ['at' => $c->review->reviewed_at?->toDateTimeString(), 'by' => $name($c->review->reviewed_by), 'action' => 'QA answered', 'note' => null] : null,
            $c->stat_confirm ? ['at' => $c->confirmed_at?->toDateTimeString(), 'by' => $name($c->confirmed_by), 'action' => 'Confirmed', 'note' => null] : null,
            ['at' => $c->created_at?->toDateTimeString(), 'by' => $name($c->created_by), 'action' => 'Created', 'note' => null],
        ]));
    }

    // ---------- QA (Production > Complaint Review) ----------

    public static function reviewRows(): array
    {
        return CrmComplaint::query()->with(['customer', 'items', 'review'])->where('stat_confirm', 1)->where('stat_cancel', 0)->where('needs_qa', 1)
            ->orderByDesc('id_complaint')->limit(1000)->get()->map(fn (CrmComplaint $c) => self::row($c) + [
                'qa_state' => $c->review?->stat_confirm ? 'ANSWERED' : 'TO_REVIEW',
                'disposition' => $c->review?->disposition ? t(QcComplaintReview::DISPOSITIONS[$c->review->disposition] ?? '') : null,
                'reviewed_at' => $c->review?->reviewed_at?->toDateString(),
            ])->all();
    }

    public static function reviewable(int $complaintId): ?CrmComplaint
    {
        $c = self::find($complaintId);

        return $c && $c->stat_confirm && ! $c->stat_cancel && $c->needs_qa ? $c : null;
    }

    public static function saveReview(CrmComplaint $c, array $in, bool $confirm): ?string
    {
        $login = Auth::user()->login;
        $r = $c->review ?? new QcComplaintReview(['complaint_id' => $c->id_complaint, 'complaint_no' => $c->complaint_no, 'created_by' => $login]);
        if ($r->stat_confirm) {
            return 'This answer is already Confirmed and cannot be changed.';
        }
        $r->fill([
            'verdict' => isset(QcComplaintReview::VERDICTS[$in['verdict'] ?? '']) ? $in['verdict'] : null,
            'root_cause' => trim((string) ($in['root_cause'] ?? '')) ?: null, 'corrective_action' => trim((string) ($in['corrective_action'] ?? '')) ?: null,
            'preventive_action' => trim((string) ($in['preventive_action'] ?? '')) ?: null,
            'disposition' => isset(QcComplaintReview::DISPOSITIONS[$in['disposition'] ?? '']) ? $in['disposition'] : null, 'updated_by' => $login,
        ]);
        if ($confirm) {
            if (! $r->verdict || ! $r->root_cause) {
                $r->save();

                return 'Fill in the verdict and the root cause before Confirm.';
            }
            if ($c->items->sum(fn (CrmComplaintItem $i) => (float) $i->qty_return) > 0 && (! $r->disposition || $r->disposition === 'NONE')) {
                $r->save();

                return 'Goods came back: choose what happens to them (Rework, Scrap or Back to stock).';
            }
            $r->fill(['stat_confirm' => true, 'reviewed_at' => now(), 'reviewed_by' => $login]);
        }
        $r->save();

        return null;
    }
}
