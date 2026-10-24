<?php

namespace App\Services\Dispatch;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use Illuminate\Support\Facades\DB;

// dispatch_tier2.md §A/C/D -- satu-satunya jalur SAH utk pullLines/confirm/
// markShipped/cancel, dipanggil SAMA persis dari DO Entry maupun tombol
// ✓ Confirm di grid DO List (dispatch_tier4.md (diarsipkan) §9 checklist), supaya tidak
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
            return ['ok' => false, 'message' => t('The document no longer has status DRAFT.')];
        }

        $errors = [];

        DB::connection('tm2sdi')->transaction(function () use ($do, $coDeliveryIds, $actingUser, &$errors) {
            $existing = DoLine::where('delivery_id', $do->id_delivery)->pluck('co_delivery_id')->filter()->all();
            $nextSeq = ((int) DoLine::where('delivery_id', $do->id_delivery)->max('line_no')) + 1;
            // Flag PPN DO ikut CO-nya (backlog01 UI-09): baris pertama menentukan,
            // CO dengan status PPN berbeda ditolak -- 1 DO tidak boleh campur.
            $hasLines = ! empty($existing);

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
                    $errors[] = tf('Schedule line #:id is not found.', ['id' => $coDeliveryId]);

                    continue;
                }

                if ((int) $row->customer_id !== (int) $do->customer_id) {
                    // Tier2 §D.1: guard ulang server-side, jangan percaya filter picker saja.
                    $errors[] = tf('Line :no does not belong to the customer of this document.', ['no' => $row->co_no]);

                    continue;
                }

                if ($row->co_status !== 'CONFIRMED') {
                    $errors[] = tf(':no is not CONFIRMED yet, it cannot be pulled.', ['no' => $row->co_no]);

                    continue;
                }

                if ((float) $row->qty_available <= 0) {
                    $errors[] = tf(':no — :item: the qty is already fully taken.', ['no' => $row->co_no, 'item' => $row->item_description]);

                    continue;
                }

                $coPpn = (bool) CoOrder::whereKey($row->co_order_id)->value('is_ppn');
                if ($hasLines && $coPpn !== (bool) $do->is_ppn) {
                    $errors[] = tf(':no :tax -- this document is :doc_tax; they cannot be mixed in 1 DO.', ['no' => $row->co_no, 'tax' => $coPpn ? 'PPN' : 'Non PPN', 'doc_tax' => $do->is_ppn ? 'PPN' : 'Non PPN']);

                    continue;
                }
                if (! $hasLines) {
                    $do->update(['is_ppn' => $coPpn]);
                    $hasLines = true;
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

                $this->copyShipAddress($do, (int) $row->co_order_id);

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
            return ['ok' => false, 'message' => t('The document no longer has status DRAFT.')];
        }

        if ($newQty <= 0) {
            return ['ok' => false, 'message' => t('The qty must be more than 0.')];
        }

        if ($line->co_delivery_id) {
            $qtyAvailable = (float) (DB::connection('tm2sdi')->table('v_co_delivery_outstanding')
                ->where('co_delivery_id', $line->co_delivery_id)
                ->value('qty_available') ?? 0);

            $ceiling = $qtyAvailable + (float) $line->qty_do;

            if ($newQty > $ceiling) {
                return ['ok' => false, 'message' => tf('The qty exceeds the available limit (:qty).', ['qty' => $ceiling])];
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
            return ['ok' => false, 'message' => t('The document no longer has status DRAFT.')];
        }

        if ($newPrice < 0) {
            return ['ok' => false, 'message' => t('The price cannot be negative.')];
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
            return ['ok' => false, 'message' => t('The document no longer has status DRAFT.')];
        }

        $line->delete();

        return ['ok' => true, 'message' => null];
    }

    // Tier2 §C.2: 4 syarat, SEMUA re-validasi server-side (jangan percaya state client). Penolakan di dalam transaksi =
    // DomainException (rollback); pesannya dikembalikan sebagai hasil, bukan halaman 500 (spesifikasi D.3).
    public function confirm(DoDelivery $do, string $actingUser): array
    {
        return $this->guarded($do, 'DRAFT', t('The document no longer has status DRAFT.'), function (DoDelivery $do) use ($actingUser) {
            $lines = $do->lines()->get();

            if ($lines->isEmpty()) {
                throw new \DomainException(t('At least 1 line is required before Confirm.'));
            }

            foreach ($lines as $line) {
                if ((float) $line->qty_do <= 0) {
                    throw new \DomainException(tf('Line :item: the qty must be more than 0.', ['item' => $line->item_description]));
                }
            }

            // Surat jalan subkon (do_type SUBCON) tujuannya pemasok, bukan alamat kirim CO; CO Pickup (diambil sendiri) tidak punya alamat tujuan (sementara, menunggu keputusan Jo).
            $methods = CoOrder::whereIn('id_order', $lines->pluck('co_order_id')->filter())->pluck('shipment_method');
            $pickupOnly = $methods->isNotEmpty() && $methods->every(fn ($m) => $m === 'PICKUP');
            if (! $do->isSubcon() && ! $pickupOnly && trim((string) $do->ship_address_label) === '' && trim((string) $do->ship_address_detail) === '') {
                throw new \DomainException(t('The ship-to address is required before Confirm (Delivery tab).'));
            }

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
                        throw new \DomainException(tf('Race condition: the qty of line :item has already been taken by another document. Delete/reduce this line.', ['item' => $line->item_description]));
                    }
                }

                // Surat jalan subkon (do_type SUBCON) tidak berasal dari CO.
                if (! $line->co_order_id) {
                    continue;
                }

                $coStatus = DB::connection('tm2sdi')->table('co_order')->where('id_order', $line->co_order_id)->value('doc_status');

                if ($coStatus !== 'CONFIRMED') {
                    throw new \DomainException(tf('The source CO of line :item is no longer CONFIRMED.', ['item' => $line->item_description]));
                }
            }

            $do->update([
                'do_no' => $this->nextDoNo(),
                'status' => 'CONFIRMED',
                'updated_by' => $actingUser,
            ]);
        }, numbered: true);
    }

    // Tier2 §C.3: satu aksi tunggal, seluruh baris DO sekaligus, satu transaction.
    public function markShipped(DoDelivery $do, string $actingUser): array
    {
        return $this->guarded($do, 'CONFIRMED', t('The document must have status CONFIRMED.'), function (DoDelivery $do) use ($actingUser) {
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
    }

    // Tier2 §C.4: dari DRAFT atau CONFIRMED saja, wajib alasan. qty_available
    // pulih otomatis (baris CANCELLED dikecualikan dari VIEW), tidak perlu
    // aksi manual ke co_order_delivery.
    public function cancel(DoDelivery $do, string $reason, string $actingUser): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'message' => t('The cancellation reason is required.')];
        }

        return $this->guarded($do, ['DRAFT', 'CONFIRMED'], t('A SHIPPED/CANCELLED document cannot be cancelled.'), function (DoDelivery $do) use ($reason, $actingUser) {
            DoLine::where('delivery_id', $do->id_delivery)->update(['status' => 'CANCELLED', 'updated_by' => $actingUser]);

            $do->update([
                'status' => 'CANCELLED',
                'notes' => $reason,
                'updated_by' => $actingUser,
            ]);
        });
    }

    // Satu transaksi: baris DO dikunci, status dicek pada baris TERKUNCI (bukan objek lama di luar transaksi: klik ganda /
    // dua pengguna, yang kedua melihat status baru dan ditolak), lalu $work. $numbered = butuh nomor DO baru: kunci bernama
    // dipegang sampai commit supaya dua Confirm tidak berebut nomor (lockForUpdate pada nol baris tidak mengunci).
    private function guarded(DoDelivery $do, string|array $allowed, string $deniedMessage, \Closure $work, bool $numbered = false): array
    {
        $db = DB::connection('tm2sdi');
        $lock = 'dl_do_no_'.now()->format('y');
        $held = false;

        try {
            if ($numbered) {
                $held = (bool) ($db->selectOne('select get_lock(?, 10) as got', [$lock])->got ?? 0);
                if (! $held) {
                    return ['ok' => false, 'message' => t('The DO number is being issued by another user. Try again in a moment.')];
                }
            }

            $db->transaction(function () use ($do, $allowed, $deniedMessage, $work) {
                $locked = DoDelivery::whereKey($do->id_delivery)->lockForUpdate()->firstOrFail();

                if (! in_array($locked->status, (array) $allowed, true)) {
                    throw new \DomainException($deniedMessage);
                }

                $work($locked);
            });
        } catch (\DomainException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            if ($held) {
                $db->selectOne('select release_lock(?) as freed', [$lock]);
            }
        }

        $do->refresh();

        return ['ok' => true, 'message' => null];
    }

    // Alamat kirim DO = alamat kirim CO sumber baris pertama; hanya bila DO belum punya alamat (pilihan pengguna tidak ditimpa).
    private function copyShipAddress(DoDelivery $do, int $coOrderId): void
    {
        if ($do->ship_area_id || trim((string) $do->ship_address_label) !== '' || trim((string) $do->ship_address_detail) !== '') {
            return;
        }

        $co = CoOrder::find($coOrderId);
        if ($co) {
            $do->update($co->only(['ship_area_id', 'ship_area_level', 'ship_address_label', 'ship_address_detail', 'ship_postal_code']));
        }
    }

    // dispatch_tier3.md §5: pola manual MAX+1/tahun (sys_sequence TIDAK ada
    // di codebase ini, dicek dulu -- sama pola PoOrder::nextDocNo()/
    // PvReceipt::nextDocNo()). Dipanggil hanya dari confirm() yang memegang kunci bernama.
    protected function nextDoNo(): string
    {
        $prefix = 'DO/'.now()->format('y').'/';

        $last = DoDelivery::where('do_no', 'like', "{$prefix}%")
            ->orderByDesc('do_no')
            ->value('do_no');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
