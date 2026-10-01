<?php

namespace App\Support\V2\Data;

use App\Models\Master\SupSupplier;
use App\Models\Pur\PrRequisition;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Services\Dispatch\DispatchService;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Surat Jalan Subkon v2 (backlog02 §19.1, user journey disetujui 2026-09-30): do_delivery ber-do_type SUBCON -- barang
// setengah jadi dikirim ke supplier subkon untuk satu proses. Dibuat otomatis dari ProDev (ProcurementBridge) atau manual
// lewat layar ini. Draft: New / Save / Delete / Confirm; Confirmed: Cancel / Print saja. Tanpa harga. Barang kembali
// dicatat di Procurement (Penerimaan dari Subkon), bukan di sini. Confirm / Cancel = aturan DispatchService yang sama dengan DO.
final class SubconDelivery
{
    public const STATUS_TONES = ['DRAFT' => 'neutral', 'CONFIRMED' => 'success', 'SHIPPED' => 'info', 'CANCELLED' => 'danger'];

    public static function query()
    {
        return DoDelivery::query()->where('do_type', 'SUBCON');
    }

    public static function find(int $id): ?DoDelivery
    {
        return self::query()->with(['supplier', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no'), 'lines.order:id_order,co_no'])->find($id);
    }

    public static function rows(): array
    {
        $dos = self::query()->with(['supplier', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no'), 'lines.order:id_order,co_no'])
            ->orderByDesc('id_delivery')->limit(500)->get();
        $pr = PrRequisition::query()->whereKey($dos->pluck('pr_id')->filter()->unique()->all())->pluck('doc_no', 'pr_id');

        return $dos->map(fn (DoDelivery $d) => [
            'id' => $d->id_delivery, 'no' => $d->do_no ?: 'DRAFT-'.$d->id_delivery, 'supplier' => $d->supplier?->supplier_name, 'process' => $d->subcon_process,
            'co' => $d->lines->pluck('order.co_no')->filter()->unique()->implode(', '), 'pr' => $d->pr_id ? ($pr[$d->pr_id] ?? 'Draft #'.$d->pr_id) : null,
            'date' => $d->do_date?->toDateString(), 'ship' => $d->scheduled_ship_date?->toDateString(), 'status' => $d->status,
            'items' => $d->lines->map(fn (DoLine $l) => ['item' => $l->item_description, 'co' => $l->order?->co_no, 'qty' => (float) $l->qty_do, 'unit' => $l->qty_unit])->values()->all(),
        ])->all();
    }

    public static function supplierOptions(): array
    {
        return PurchaseOrder::suppliers();
    }

    // PR subkon (i_source SUBCONTRACT) yang masih berjalan + PR yang sudah dipakai dokumen ini.
    public static function prOptions(?DoDelivery $do = null): array
    {
        return PrRequisition::query()->where(fn ($q) => $q->where('i_source', 'SUBCONTRACT')->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->when($do?->pr_id, fn ($w) => $w->orWhere('pr_id', $do->pr_id)))
            ->orderByDesc('pr_id')->limit(200)->get(['pr_id', 'doc_no', 'i_notes'])
            ->mapWithKeys(fn ($p) => [$p->pr_id => ($p->doc_no ?: 'Draft #'.$p->pr_id).($p->i_notes ? ' · '.mb_substr($p->i_notes, 0, 60) : '')])->all();
    }

    // Baris product Customer Order CONFIRMED (CO asal barang yang disubkonkan) + baris yang sudah dipakai dokumen ini.
    public static function coItemOptions(?DoDelivery $do = null): array
    {
        $used = $do ? $do->lines->pluck('co_order_item_id')->filter()->all() : [];

        return CoOrderItem::query()->with('order:id_order,co_no,doc_status')->where('line_kind', 'PRODUCT')
            ->where(fn ($q) => $q->whereHas('order', fn ($o) => $o->where('doc_status', 'CONFIRMED'))->orWhereIn('id_item', $used))
            ->orderByDesc('order_id')->limit(300)->get()
            ->mapWithKeys(fn (CoOrderItem $i) => [$i->id_item => ($i->order?->co_no ?? '#'.$i->order_id).' · '.$i->description])->all();
    }

    public static function create(int $supplierId, string $process): DoDelivery
    {
        $s = SupSupplier::query()->findOrFail($supplierId);
        $login = Auth::user()->login;

        return DoDelivery::create(['do_type' => 'SUBCON', 'do_date' => now()->toDateString(), 'supplier_id' => $s->id_supplier, 'subcon_process' => mb_substr(trim($process), 0, 80),
            'ship_address_label' => $s->supplier_name, 'ship_address_detail' => $s->default_address, 'ship_postal_code' => $s->postal_code,
            'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
    }

    // Header + baris (tambah / ubah / hapus). Baris baru tanpa nama barang dilewati.
    public static function save(DoDelivery $do, array $in): void
    {
        abort_unless($do->isSubcon() && $do->status === 'DRAFT', 422, 'This delivery note is already Confirmed and cannot be changed.');
        $login = Auth::user()->login;
        if (array_key_exists('supplier_id', $in) && (int) $in['supplier_id'] && (int) $in['supplier_id'] !== (int) $do->supplier_id) {
            $s = SupSupplier::query()->findOrFail((int) $in['supplier_id']);
            $do->fill(['supplier_id' => $s->id_supplier, 'ship_address_label' => $s->supplier_name, 'ship_address_detail' => $s->default_address, 'ship_postal_code' => $s->postal_code]);
        } elseif (array_key_exists('ship_address_detail', $in)) {
            $do->ship_address_detail = trim((string) $in['ship_address_detail']) ?: null;
        }
        foreach (['do_date', 'scheduled_ship_date'] as $f) {
            if (array_key_exists($f, $in)) {
                $do->{$f} = trim((string) $in[$f]) ?: ($f === 'do_date' ? $do->do_date : null);
            }
        }
        if (array_key_exists('subcon_process', $in)) {
            $do->subcon_process = mb_substr(trim((string) $in['subcon_process']), 0, 80) ?: null;
        }
        if (array_key_exists('pr_id', $in)) {
            $do->pr_id = (int) $in['pr_id'] ?: null;
        }
        if (array_key_exists('notes', $in)) {
            $do->notes = mb_substr(trim((string) $in['notes']), 0, 255) ?: null;
        }
        $do->updated_by = $login;

        DB::connection('tm2sdi')->transaction(function () use ($do, $in, $login) {
            $do->save();
            $lines = $do->lines()->get()->keyBy('id_line');
            foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
                $lines->get((int) $id)?->delete();
            }
            $next = (int) $lines->max('line_no');
            foreach ((array) ($in['lines'] ?? []) as $id => $row) {
                $row = (array) $row;
                if (isset($in['remove'][$id])) {
                    continue;
                }
                $line = is_numeric($id) ? $lines->get((int) $id) : null;
                $name = mb_substr(trim((string) ($row['item_description'] ?? '')), 0, 255);
                if (! $line) {
                    if ($name === '') {
                        continue;
                    }
                    $line = new DoLine(['delivery_id' => $do->id_delivery, 'line_no' => ++$next, 'unit_price' => 0, 'subtotal' => 0, 'price_overridden' => 0,
                        'qty_invoiced' => 0, 'status' => 'PLANNED', 'created_by' => $login]);
                }
                $item = (int) ($row['co_order_item_id'] ?? 0) ?: null;
                $line->fill([
                    'co_order_item_id' => $item, 'co_order_id' => $item ? CoOrderItem::query()->whereKey($item)->value('order_id') : null,
                    'item_description' => $name !== '' ? $name : $line->item_description, 'qty_do' => (float) (Locale::input($row['qty_do'] ?? 0) ?? 0),
                    'qty_unit' => mb_substr(trim((string) ($row['qty_unit'] ?? '')), 0, 20) ?: 'PCS', 'updated_by' => $login,
                ])->save();
            }
        });
    }

    // Syarat Confirm khusus subkon (di luar aturan DispatchService): supplier, proses, dan CO asal tiap baris wajib ada --
    // biaya subkon harus terlacak ke order asalnya (catatan ProcurementBridge).
    public static function confirmErrors(DoDelivery $do): array
    {
        $do->load('lines');
        $errors = [];
        if (! $do->supplier_id) {
            $errors[] = 'Choose the subcontractor.';
        }
        if (trim((string) $do->subcon_process) === '') {
            $errors[] = 'Fill in the subcontract process.';
        }
        $lines = $do->lines->where('status', '<>', 'CANCELLED');
        if ($lines->isEmpty()) {
            $errors[] = 'Add at least one item.';
        }
        if ($lines->contains(fn (DoLine $l) => ! $l->co_order_item_id)) {
            $errors[] = 'Every item needs its Customer Order line.';
        }

        return $errors;
    }

    // confirm | cancel -> pesan salah, null = berhasil.
    public static function act(DoDelivery $do, string $action, string $reason = ''): ?string
    {
        abort_unless($do->isSubcon(), 404);
        $svc = app(DispatchService::class);
        $login = Auth::user()->login;
        if ($action === 'confirm' && ($errors = self::confirmErrors($do))) {
            return implode(' ', $errors);
        }
        $result = $action === 'confirm' ? $svc->confirm($do, $login) : $svc->cancel($do, $reason, $login);

        return $result['ok'] ? null : (string) $result['message'];
    }

    // Delete = hapus permanen, hanya Draft (rulebook C.6).
    public static function delete(DoDelivery $do): void
    {
        abort_unless($do->isSubcon() && $do->status === 'DRAFT', 422, 'Only a Draft can be deleted.');
        DB::connection('tm2sdi')->transaction(function () use ($do) {
            $do->lines()->delete();
            $do->delete();
        });
    }

    public static function formStatus(DoDelivery $do): string
    {
        return match ($do->status) {
            'CONFIRMED' => 'confirmed', 'SHIPPED' => 'closed', 'CANCELLED' => 'cancelled', default => 'draft'
        };
    }
}
