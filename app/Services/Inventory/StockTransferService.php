<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStockcard;
use App\Models\Master\WhLoc;
use App\Services\Flow\FlowEngineService;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Posting Stock Transfer (pindah stok antar bin / antar gudang) -- satu-satunya penulis kartu stok untuk transfer,
// sejajar dengan StockPostingService (dokumen stok lain). Per baris ditulis DUA baris kartu stok:
//   keluar dari bin asal  (reference_name tm2inv.inv_transfer_line:OUT)
//   masuk ke bin tujuan   (reference_name tm2inv.inv_transfer_line:IN)
// keduanya doc_type 'TF'. Nilai ikut pindah dengan harga rata-rata bin asal; saldo item secara keseluruhan (cur_stock)
// tidak berubah dan TIDAK ada jurnal (pemilik dan nilai sama). Cancel = pindah balik (..:CANCEL_OUT / ..:CANCEL_IN).
// Saldo satu posisi = baris kartu TERAKHIR (doc_date, doc_id) item itu di bin itu -- sama dengan view v_*_onhand.
// Approval (user 2026-10-02: "untuk sementara di-skip tapi fasilitasnya dibuat dan bisa diaktifkan kapan saja"): modul
// flow 'TF' di Setup > Setting Approval. Tidak aktif = Confirm langsung memindahkan stok. Aktif = Confirm mengajukan
// (nomor lahir, stok BELUM pindah); stok baru pindah saat level terakhir menyetujui. Palet berlabel ikut pindah
// (App\Services\Inventory\PalletService).
class StockTransferService
{
    public const FLOW = 'TF';

    // kelas => [model kartu, kolom item]
    private const CARDS = ['RM' => [RmStockcard::class, 'material_id'], 'SFG' => [SfgStockcard::class, 'parts_id'], 'FG' => [FgStockcard::class, 'product_id']];

    public const REF = 'tm2inv.inv_transfer_line';

    // Pesan untuk user: teksnya English dan diterjemahkan kamus, nilai datanya (:n, :item, ...) disisipkan sesudahnya
    // supaya nomor / kode tidak tercatat di kamus.
    private static function msg(string $text, array $values = []): string
    {
        return strtr(t($text), collect($values)->mapWithKeys(fn ($v, $k) => [':'.$k => (string) $v])->all());
    }

    // Yang membuat dokumen belum bisa di-Confirm (kosong = boleh). Dipakai layar (pesan) dan confirm().
    /** @return list<string> */
    public function confirmErrors(InvTransfer $t): array
    {
        $t->load('lines');
        $errors = [];
        if ($t->lines->isEmpty()) {
            return [self::msg('Add at least one line.')];
        }
        $bins = WhLoc::query()->whereKey($t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id'))->filter()->unique()->all())->get()->keyBy('location_id');
        $need = [];
        foreach ($t->lines as $l) {
            $n = $l->line_no;
            $to = $bins->get($l->to_location_id);
            $need[$l->positionKey()] = ($need[$l->positionKey()] ?? 0) + $l->qty;
            if ($l->qty <= 0) {
                $errors[] = self::msg('Line :n: fill in the quantity.', ['n' => $n]);
            }
            if (! $to) {
                $errors[] = self::msg('Line :n: choose the destination bin.', ['n' => $n]);

                continue;
            }
            if ((int) $to->warehouse_id !== (int) $t->to_warehouse_id) {
                $errors[] = self::msg('Line :n: the destination bin is not in the destination warehouse.', ['n' => $n]);
            }
            if (! $to->is_usable || ! $to->activate) {
                $errors[] = self::msg('Line :n: the destination bin :bin is blocked.', ['n' => $n, 'bin' => $to->location_code]);
            }
            if ((int) $l->to_location_id === (int) $l->from_location_id) {
                $errors[] = self::msg('Line :n: the destination bin is the same as the source bin.', ['n' => $n]);
            }
        }
        foreach ($t->lines->unique(fn (InvTransferLine $l) => $l->positionKey()) as $l) {
            $onhand = $this->balance($l->stock_class, (int) $l->item_id, $l->part_id, (int) $l->from_location_id);
            if ($need[$l->positionKey()] > $onhand + 0.00005) {
                $errors[] = self::msg(':item: only :qty on hand in the source bin.', ['item' => $l->item_code, 'qty' => Locale::decimal($onhand, 4)]);
            }
        }

        return $errors;
    }

    // Confirm dari layar. Kembalian = pesan salah, null = berhasil (status sesudahnya: CONFIRMED atau SUBMITTED).
    public function confirm(InvTransfer $transfer, string $login): ?string
    {
        return app(FlowEngineService::class)->activeConfig(self::FLOW) ? $this->submit($transfer, $login) : $this->post($transfer, $login);
    }

    // Approval aktif: nomor lahir dan dokumen masuk antrian pemeriksa; stok belum pindah. Tidak ada level yang cocok
    // dengan nilainya = langsung pindah.
    public function submit(InvTransfer $transfer, string $login): ?string
    {
        try {
            DB::connection('tm2inv')->transaction(function () use ($transfer, $login) {
                $t = InvTransfer::query()->lockForUpdate()->findOrFail($transfer->id_transfer);
                if ($t->status() !== 'DRAFT') {
                    throw new RuntimeException(self::msg('Only a Draft can be confirmed.'));
                }
                if ($errors = $this->confirmErrors($t)) {
                    throw new RuntimeException(implode(' ', $errors));
                }
                $t->forceFill(['transfer_no' => $t->transfer_no ?: InvTransfer::nextNo(), 'flow_status' => 'SUBMITTED', 'updated_by' => $login])->save();
                $res = app(FlowEngineService::class)->submit(self::FLOW, (int) $t->id_transfer, 'SUBMITTED', $this->value($t));
                if (! $res || ! $res['pending_step_id']) {
                    $this->apply($t, $login, 'APPROVED');
                }
            });
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    // Stok pindah. $approved = dipanggil sesudah persetujuan terakhir (dokumen berstatus SUBMITTED), selain itu dari Draft.
    public function post(InvTransfer $transfer, string $login, bool $approved = false): ?string
    {
        try {
            DB::connection('tm2inv')->transaction(function () use ($transfer, $login, $approved) {
                $t = InvTransfer::query()->lockForUpdate()->findOrFail($transfer->id_transfer);
                if ($t->status() !== ($approved ? 'SUBMITTED' : 'DRAFT')) {
                    throw new RuntimeException(self::msg($approved ? 'This transfer is not waiting for approval.' : 'Only a Draft can be confirmed.'));
                }
                if ($errors = $this->confirmErrors($t)) {
                    throw new RuntimeException(implode(' ', $errors));
                }
                $this->apply($t, $login, $approved ? 'APPROVED' : null);
            });
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    // Nilai dokumen untuk batas approval: qty x harga rata-rata bin asal (yang tidak bernilai dihitung 0).
    public function value(InvTransfer $t): float
    {
        return round((float) $t->lines->sum(fn (InvTransferLine $l) => $l->qty
            * (float) ($this->last($l->stock_class, (int) $l->item_id, $l->part_id, (int) $l->from_location_id)->first()?->avg_unitprice ?? 0)), 2);
    }

    // Tulis kartu stok semua baris, tandai Confirmed, pindahkan palet. Dipanggil di dalam transaksi, dokumen sudah dikunci.
    private function apply(InvTransfer $t, string $login, ?string $flowStatus): void
    {
        $bins = WhLoc::query()->whereKey($t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id'))->unique()->all())->get()->keyBy('location_id');
        $dead = WarehouseStock::positions($t->lines->pluck('from_location_id')->unique()->all())->where('dead', true)->pluck('key')->flip();
        foreach ($t->lines as $l) {
            $cost = $this->move($l, (int) $l->from_location_id, (int) $l->to_location_id, $bins, 'OUT', 'IN', $login);
            $l->forceFill(['unit_cost' => $cost, 'updated_by' => $login,
                'was_dead_stock' => $dead->has(WarehouseStock::key($l->stock_class, (int) $l->item_id, $l->part_id, (int) $l->from_location_id))])->save();
        }
        $t->forceFill(['transfer_no' => $t->transfer_no ?: InvTransfer::nextNo(), 'stat_confirm' => true, 'confirmed_by' => $login, 'confirmed_at' => now(),
            'flow_status' => $flowStatus, 'updated_by' => $login])->save();
        PalletService::followTransfer($t, $login);
    }

    // Cancel = stok dikembalikan ke bin asal (bila masih ada di bin tujuan), alasan wajib. Dokumen yang masih menunggu
    // approval dibatalkan tanpa gerakan stok (stoknya memang belum pindah).
    public function cancel(InvTransfer $transfer, string $reason, string $login): ?string
    {
        if (trim($reason) === '') {
            return self::msg('Fill in the reason for cancelling.');
        }
        try {
            DB::connection('tm2inv')->transaction(function () use ($transfer, $reason, $login) {
                $t = InvTransfer::query()->with('lines')->lockForUpdate()->findOrFail($transfer->id_transfer);
                if (! in_array($t->status(), ['CONFIRMED', 'SUBMITTED'], true)) {
                    throw new RuntimeException(self::msg('Only a Confirmed transfer can be cancelled.'));
                }
                if ($t->status() === 'CONFIRMED') {
                    $bins = WhLoc::query()->whereKey($t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id'))->unique()->all())->get()->keyBy('location_id');
                    foreach ($t->lines as $l) {
                        $this->move($l, (int) $l->to_location_id, (int) $l->from_location_id, $bins, 'CANCEL_OUT', 'CANCEL_IN', $login);
                    }
                    PalletService::revertTransfer($t, $login);
                }
                $t->forceFill(['stat_cancel' => true, 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'updated_by' => $login])->save();
            });
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    // Saldo sekarang satu posisi (baris kartu terakhir).
    public function balance(string $class, int $itemId, ?int $partId, int $locationId): float
    {
        return (float) ($this->last($class, $itemId, $partId, $locationId)->first()?->qty_eb ?? 0);
    }

    private function last(string $class, int $itemId, ?int $partId, int $locationId): Builder
    {
        [$card, $itemKey] = self::CARDS[$class];
        $q = $card::query()->where($itemKey, $itemId)->where('location_id', $locationId);
        if ($class === 'FG') {
            $partId ? $q->where('parts_id', $partId) : $q->whereNull('parts_id');
        }

        return $q->orderByDesc('doc_date')->orderByDesc('doc_id');
    }

    // Dua baris kartu untuk satu baris transfer. Kembalian = harga rata-rata asal (nilai yang ikut pindah), null = tidak bernilai.
    private function move(InvTransferLine $l, int $fromId, int $toId, $bins, string $outTag, string $inTag, string $login): ?float
    {
        [$card, $itemKey] = self::CARDS[$l->stock_class];
        $itemId = (int) $l->item_id;
        $qty = (float) $l->qty;
        $from = $this->last($l->stock_class, $itemId, $l->part_id, $fromId)->lockForUpdate()->first();
        $to = $this->last($l->stock_class, $itemId, $l->part_id, $toId)->lockForUpdate()->first();
        $balFrom = (float) ($from?->qty_eb ?? 0);
        if ($balFrom + 0.00005 < $qty) {
            $code = $bins->get($fromId)?->location_code ?? '#'.$fromId;
            throw new RuntimeException(self::msg(':item: not enough stock in :bin (:qty on hand).', ['item' => $l->item_code, 'bin' => $code, 'qty' => Locale::decimal($balFrom, 4)]));
        }
        // Baris baru selalu yang terakhir di kedua bin (saldo = baris terakhir), juga bila ada dokumen bertanggal ke depan.
        $at = collect([now(), $from?->doc_date, $to?->doc_date])->filter()->map(fn ($d) => Carbon::parse($d))->max();
        $avg = $from?->avg_unitprice === null ? null : (float) $from->avg_unitprice;
        $base = fn (?Model $prev, int $locId) => [
            'previous_doc_id' => $prev?->getKey(), 'doc_date' => $at, $itemKey => $itemId, 'doc_type' => 'TF', 'location_id' => $locId,
            'warehouse_id' => $bins->get($locId)?->warehouse_id, 'lot_no' => $l->lot_no, 'uom_code' => $l->uom_code ?: $from?->uom_code,
            'reference_detail_id' => $l->id_transfer_line, 'qty_adj' => 0, 'period_id' => null, 'create_by' => $login, 'update_by' => $login,
        ] + match ($l->stock_class) {
            'FG' => ['parts_id' => $l->part_id],
            'SFG' => ['product_id' => $from?->product_id],
            default => [],
        };

        $ebFrom = $balFrom - $qty;
        (new $card)->forceFill($base($from, $fromId) + ['reference_name' => self::REF.':'.$outTag, 'qty' => -$qty, 'qty_bb' => $balFrom, 'qty_in' => 0, 'qty_out' => $qty,
            'qty_eb' => $ebFrom, 'avg_unitprice' => $avg, 'value_eb' => $avg === null ? null : $ebFrom * $avg])->save();

        $balTo = (float) ($to?->qty_eb ?? 0);
        $avgTo = $to?->avg_unitprice === null ? null : (float) $to->avg_unitprice;
        $ebTo = $balTo + $qty;
        // Harga rata-rata bin tujuan = rata-rata tertimbang isi lama + yang datang (yang tidak bernilai dihitung 0).
        $newAvg = $avg === null && $avgTo === null ? null : (($balTo * (float) $avgTo) + ($qty * (float) $avg)) / max($ebTo, 0.0001);
        (new $card)->forceFill($base($to, $toId) + ['reference_name' => self::REF.':'.$inTag, 'qty' => $qty, 'qty_bb' => $balTo, 'qty_in' => $qty, 'qty_out' => 0,
            'qty_eb' => $ebTo, 'avg_unitprice' => $newAvg, 'value_eb' => $newAvg === null ? null : $ebTo * $newAvg])->save();

        return $avg;
    }
}
