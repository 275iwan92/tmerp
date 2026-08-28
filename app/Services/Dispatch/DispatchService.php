<?php

namespace App\Services\Dispatch;

use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use Illuminate\Support\Facades\DB;

// dispatch_tier2.md §A/C/D -- satu-satunya jalur SAH utk pullLines/confirm/
// markShipped/cancel, dipanggil SAMA persis dari DO Entry maupun tombol
// ✓ Confirm di grid DO List (dispatch_tier4.md §9 checklist), supaya tidak
// ada duplikasi logic di dua tempat.
class DispatchService
{
    // Tier2 §A.3-A.4: 1 co_delivery_id terpilih = 1 do_line baru (1:1, TIDAK
    // PERNAH digabung -- DISPATCH-02). Guard duplikat: co_delivery_id yang
    // sudah ada di DO ini ditolak, bukan nambah qty ke baris existing.
    // Re-validasi qty_available LIVE dari VIEW (bukan percaya angka dari
    // client) -- baris picker bisa saja sudah berubah sejak halaman dimuat.
    public function pullLines(DoDelivery $do, array $coDeliveryIds, string $actingUser): array
    {
        if ($do->status !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Dokumen sudah tidak berstatus DRAFT.'];
        }

        $errors = [];

        DB::connection('tm2sdi')->transaction(function () use ($do, $coDeliveryIds, $actingUser, &$errors) {
            $existing = DoLine::where('delivery_id', $do->id_delivery)->pluck('co_delivery_id')->filter()->all();
            $nextSeq = ((int) DoLine::where('delivery_id', $do->id_delivery)->max('line_no')) + 1;

            foreach ($coDeliveryIds as $coDeliveryId) {
                if (in_array((int) $coDeliveryId, $existing, true)) {
                    // Tier2 §A.4: sudah ada di DO ini, tolak diam-diam (klik ganda), bukan error keras per-baris.
                    continue;
                }

                $row = DB::connection('tm2sdi')->table('v_co_delivery_outstanding')
                    ->where('co_delivery_id', $coDeliveryId)
                    ->lockForUpdate()
                    ->first();

                if (! $row) {
                    $errors[] = "Baris jadwal #{$coDeliveryId} tidak ditemukan.";

                    continue;
                }

                if ((int) $row->customer_id !== (int) $do->customer_id) {
                    // Tier2 §D.1: guard ulang server-side, jangan percaya filter picker saja.
                    $errors[] = "Baris {$row->co_no} bukan milik customer dokumen ini.";

                    continue;
                }

                if ($row->co_status !== 'CONFIRMED') {
                    $errors[] = "{$row->co_no} belum CONFIRMED, tidak bisa ditarik.";

                    continue;
                }

                if ((float) $row->qty_available <= 0) {
                    $errors[] = "{$row->co_no} — {$row->item_description}: qty sudah habis terserap.";

                    continue;
                }

                $qtyDo = min((float) $row->qty_available, (float) $row->ship_qty);

                $line = DoLine::create([
                    'delivery_id' => $do->id_delivery,
                    'line_no' => $nextSeq++,
                    'co_order_id' => $row->co_order_id,
                    'co_order_item_id' => $row->co_order_item_id,
                    'co_delivery_id' => $row->co_delivery_id,
                    'item_description' => $row->item_description,
                    'qty_do' => $qtyDo,
                    'qty_unit' => $row->qty_unit,
                    'unit_price' => $row->unit_price,
                    'status' => 'PLANNED',
                    'price_overridden' => false,
                    'created_by' => $actingUser,
                    'updated_by' => $actingUser,
                ]);
                $line->recalcSubtotal();
                $line->save();

                $existing[] = (int) $coDeliveryId;
            }
        });

        return ['ok' => empty($errors), 'message' => implode(' ', $errors) ?: null];
    }

    // Tier2 §D.2: batas atas dinamis = qty_available (VIEW, TIDAK termasuk baris
    // ini sendiri karena view sudah exclude co_delivery_id yg dihitung) + qty_do
    // baris itu sendiri (baris ini "memegang" sebagian qty, harus dikembalikan
    // ke basis perhitungan batas).
    public function updateLineQty(DoLine $line, float $newQty, string $actingUser): array
    {
        $do = $line->delivery;

        if ($do->status !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Dokumen sudah tidak berstatus DRAFT.'];
        }

        if ($newQty <= 0) {
            return ['ok' => false, 'message' => 'Qty harus lebih dari 0.'];
        }

        if ($line->co_delivery_id) {
            $qtyAvailable = (float) (DB::connection('tm2sdi')->table('v_co_delivery_outstanding')
                ->where('co_delivery_id', $line->co_delivery_id)
                ->value('qty_available') ?? 0);

            $ceiling = $qtyAvailable + (float) $line->qty_do;

            if ($newQty > $ceiling) {
                return ['ok' => false, 'message' => "Qty melebihi batas tersedia ({$ceiling})."];
            }
        }

        $line->qty_do = $newQty;
        $line->recalcSubtotal();
        $line->updated_by = $actingUser;
        $line->save();

        return ['ok' => true, 'message' => null];
    }

    // Tier2 §E: override manual, original_unit_price diisi SEKALI saja.
    public function updateLinePrice(DoLine $line, float $newPrice, string $actingUser): array
    {
        $do = $line->delivery;

        if ($do->status !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Dokumen sudah tidak berstatus DRAFT.'];
        }

        if ($newPrice < 0) {
            return ['ok' => false, 'message' => 'Harga tidak boleh negatif.'];
        }

        if (! $line->price_overridden) {
            $line->original_unit_price = $line->unit_price;
        }

        $line->unit_price = $newPrice;
        $line->price_overridden = true;
        $line->recalcSubtotal();
        $line->updated_by = $actingUser;
        $line->save();

        DoLine::where('delivery_id', $do->id_delivery)->where('price_overridden', true)->exists()
            && $do->update(['price_change_flag' => true, 'updated_by' => $actingUser]);

        return ['ok' => true, 'message' => null];
    }

    public function removeLine(DoLine $line): array
    {
        $do = $line->delivery;

        if ($do->status !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Dokumen sudah tidak berstatus DRAFT.'];
        }

        $line->delete();

        return ['ok' => true, 'message' => null];
    }

    // Tier2 §C.2: 4 syarat, SEMUA re-validasi server-side (jangan percaya state client).
    public function confirm(DoDelivery $do, string $actingUser): array
    {
        if ($do->status !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Dokumen sudah tidak berstatus DRAFT.'];
        }

        $lines = $do->lines()->get();

        if ($lines->isEmpty()) {
            return ['ok' => false, 'message' => 'Minimal 1 baris diperlukan sebelum Confirm.'];
        }

        foreach ($lines as $line) {
            if ((float) $line->qty_do <= 0) {
                return ['ok' => false, 'message' => "Baris {$line->item_description}: qty harus lebih dari 0."];
            }
        }

        $result = ['ok' => true, 'message' => null];

        DB::connection('tm2sdi')->transaction(function () use ($do, $lines, $actingUser, &$result) {
            foreach ($lines as $line) {
                if ($line->co_delivery_id) {
                    $qtyAvailable = (float) (DB::connection('tm2sdi')->table('v_co_delivery_outstanding')
                        ->where('co_delivery_id', $line->co_delivery_id)
                        ->lockForUpdate()
                        ->value('qty_available') ?? 0);

                    // Baris ini sendiri sudah dikecualikan dari qty_available (VIEW
                    // menjumlah SEMUA do_line PLANNED/SHIPPED termasuk baris ini) --
                    // jadi re-cek: apakah qty_do baris ini masih <= (qty_available +
                    // qty_do baris ini sendiri), sama basis dgn updateLineQty().
                    $ceiling = $qtyAvailable + (float) $line->qty_do;

                    if ((float) $line->qty_do > $ceiling) {
                        $result = ['ok' => false, 'message' => "Race condition: qty baris {$line->item_description} sudah terserap dokumen lain. Hapus/kurangi baris ini."];

                        throw new \RuntimeException('rollback');
                    }
                }

                $coStatus = DB::connection('tm2sdi')->table('co_order')->where('id_order', $line->co_order_id)->value('doc_status');

                if ($coStatus !== 'CONFIRMED') {
                    $result = ['ok' => false, 'message' => "CO asal baris {$line->item_description} sudah tidak CONFIRMED."];

                    throw new \RuntimeException('rollback');
                }
            }

            $doNo = $this->nextDoNo();

            $do->update([
                'do_no' => $doNo,
                'status' => 'CONFIRMED',
                'updated_by' => $actingUser,
            ]);
        });

        return $result;
    }

    // Tier2 §C.3: satu aksi tunggal, seluruh baris DO sekaligus, satu transaction.
    public function markShipped(DoDelivery $do, string $actingUser): array
    {
        if ($do->status !== 'CONFIRMED') {
            return ['ok' => false, 'message' => 'Dokumen harus berstatus CONFIRMED.'];
        }

        DB::connection('tm2sdi')->transaction(function () use ($do, $actingUser) {
            $lines = $do->lines()->where('status', 'PLANNED')->get();

            DoLine::where('delivery_id', $do->id_delivery)->where('status', 'PLANNED')
                ->update(['status' => 'SHIPPED', 'updated_by' => $actingUser]);

            $do->update([
                'status' => 'SHIPPED',
                'actual_ship_date' => now(),
                'updated_by' => $actingUser,
            ]);

            foreach ($lines->pluck('co_delivery_id')->filter()->unique() as $coDeliveryId) {
                $shipQty = (float) CoOrderDelivery::whereKey($coDeliveryId)->value('ship_qty');
                $shippedSum = (float) DoLine::where('co_delivery_id', $coDeliveryId)->where('status', 'SHIPPED')->sum('qty_do');

                if ($shippedSum >= $shipQty) {
                    CoOrderDelivery::whereKey($coDeliveryId)->update(['status' => 'SHIPPED', 'updated_by' => $actingUser]);
                }
            }
        });

        return ['ok' => true, 'message' => null];
    }

    // Tier2 §C.4: dari DRAFT atau CONFIRMED saja, wajib alasan. qty_available
    // pulih otomatis (baris CANCELLED dikecualikan dari VIEW), tidak perlu
    // aksi manual ke co_order_delivery.
    public function cancel(DoDelivery $do, string $reason, string $actingUser): array
    {
        if (! in_array($do->status, ['DRAFT', 'CONFIRMED'], true)) {
            return ['ok' => false, 'message' => 'Dokumen SHIPPED/CANCELLED tidak bisa dibatalkan.'];
        }

        if (trim($reason) === '') {
            return ['ok' => false, 'message' => 'Alasan pembatalan wajib diisi.'];
        }

        DB::connection('tm2sdi')->transaction(function () use ($do, $reason, $actingUser) {
            DoLine::where('delivery_id', $do->id_delivery)->update(['status' => 'CANCELLED', 'updated_by' => $actingUser]);

            $do->update([
                'status' => 'CANCELLED',
                'notes' => $reason,
                'updated_by' => $actingUser,
            ]);
        });

        return ['ok' => true, 'message' => null];
    }

    // dispatch_tier3.md §5: pola manual MAX+1/tahun (sys_sequence TIDAK ada
    // di codebase ini, dicek dulu -- sama pola PoOrder::nextDocNo()/
    // PvReceipt::nextDocNo()).
    protected function nextDoNo(): string
    {
        $prefix = 'DO/'.now()->format('y').'/';

        $last = DoDelivery::where('do_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('do_no')
            ->value('do_no');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
