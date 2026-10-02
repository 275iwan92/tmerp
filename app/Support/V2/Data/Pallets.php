<?php

namespace App\Support\V2\Data;

use App\Models\Inventory\InvPallet;
use App\Models\Master\WhLoc;
use App\Models\Pur\PvReceipt;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\WarehouseStock;
use Devlib\Locale;
use Illuminate\Support\Collection;

// Layar Print Pallet Label (user 2026-10-02: "wajib buat label setiap kedatangan dan setiap habis pemakaian dalam satu
// palet", "fasilitas cetak label palet dan cetak label item saat penerimaan barang"). Menyusun daftar pekerjaan label
// dari App\Services\Inventory\PalletService (selisih saldo stok dan qty palet berlabel) untuk satu gedung, atau untuk
// satu penerimaan barang (SPB Posted) bila layar dibuka dari form penerimaan.
final class Pallets
{
    public const NEED = ['NEW' => 'Arrival label', 'RELABEL' => 'Relabel after use', 'DONE' => 'Labelled'];

    private const NEED_TONES = ['NEW' => 'warn', 'RELABEL' => 'tangerine', 'DONE' => 'success'];

    public const ACTIONS = ['CREATED' => 'Arrival label', 'RELABEL' => 'Relabelled', 'MOVED' => 'Moved', 'CLOSED' => 'Closed', 'RESTACK' => 'Restacked'];

    // Pilihan jenis satuan di form label (kosong = otomatis dari batas sisa).
    public const KIND_OPTIONS = ['' => 'Automatic (remnant when at or below the limit)', 'PALLET' => 'Pallet (full or opened)', 'PACK' => 'Pack (remnant: reams, boxes, cans)'];

    public const EXPIRY = ['EXPIRED' => 'Expired', 'SOON' => 'Expires soon', 'OK' => 'OK', 'NONE' => 'No expiry date'];

    private const EXPIRY_TONES = ['EXPIRED' => 'danger', 'SOON' => 'warn', 'OK' => 'success', 'NONE' => 'neutral'];

    // Teks badge tampil dalam bahasa layar; warna dan saringan grid memakai teks yang sama.
    public static function need(string $code): string
    {
        return t(self::NEED[$code]);
    }

    public static function expiry(string $code): string
    {
        return t(self::EXPIRY[$code]);
    }

    /** @return array<string, string> teks badge => tone */
    public static function needTones(): array
    {
        return collect(self::NEED_TONES)->mapWithKeys(fn (string $tone, string $code) => [self::need($code) => $tone])->all();
    }

    /** @return array<string, string> teks badge => tone */
    public static function expiryTones(): array
    {
        return collect(self::EXPIRY_TONES)->mapWithKeys(fn (string $tone, string $code) => [self::expiry($code) => $tone])->all();
    }

    // Penerimaan yang boleh dilabeli: SPB Posted yang membuat dokumen stok masuk.
    public static function receipt(?int $id): ?PvReceipt
    {
        $pv = $id ? PvReceipt::query()->find($id) : null;

        return $pv && $pv->status === 'POSTED' && $pv->rm_stock_doc_id ? $pv : null;
    }

    // Daftar pekerjaan label. Satu gedung: posisi stok yang label paletnya belum cocok dengan saldo.
    // Satu penerimaan: semua baris penerimaan itu (yang sudah berlabel tampil "Labelled").
    /** @return list<array<string, mixed>> */
    public static function todo(string $home, ?int $warehouseId, ?PvReceipt $pv): array
    {
        if ($pv) {
            return array_values(array_filter(array_map(function (array $line) use ($home, $pv) {
                $p = $line['position'];
                if (! $p) {
                    return null;
                }

                return self::todoRow($p, $home.'?receipt='.$pv->pv_id.'&pos='.$p['key']) + ['received' => $line['received'], 'lot' => $line['lot_no'],
                    'receipt_pallets' => $line['receipt_pallets']->count(), 'receipt_labelled' => (float) $line['receipt_pallets']->sum('qty_initial')];
            }, PalletService::receiptLines($pv))));
        }
        $ids = $warehouseId ? Labels::binIds($warehouseId) : [];

        return PalletService::worklist($ids)->filter(fn (array $p) => $p['need'] !== null)->sortBy(fn (array $p) => $p['location_code'].' '.$p['code'])
            ->map(fn (array $p) => self::todoRow($p, $home.'?wh='.$warehouseId.'&pos='.$p['key']))->values()->all();
    }

    private static function todoRow(array $p, string $url): array
    {
        $code = $p['need'] ?? 'DONE';

        return ['id' => $p['key'], 'open_url' => $url, 'item' => $p['name'], 'code' => $p['code'], 'bin' => $p['location_code'], 'onhand' => $p['qty'], 'unit' => $p['uom'],
            'labelled' => $p['pallet_qty'], 'pallets' => $p['pallets']->count(), 'diff' => round($p['qty'] - $p['pallet_qty'], 4), 'arrived' => $p['arrived'], 'need' => self::need($code),
            'tone' => ['NEW' => 'yellow', 'RELABEL' => 'tangerine'][$code] ?? null];
    }

    // Satu posisi stok + paletnya (kunci WarehouseStock::key); null = posisi itu sudah kosong dan tanpa palet.
    public static function position(string $key): ?array
    {
        $parsed = WarehouseStock::parseKey($key);

        return $parsed ? PalletService::worklist([$parsed['location_id']])->firstWhere('key', $key) : null;
    }

    // Baris awal isian "palet baru": satu palet berisi semua yang belum berlabel (petugas memecahnya dengan + Pallet).
    // Dari penerimaan barang: $qty = yang diterima penerimaan itu dan belum dilabeli (bin bisa berisi stok lain yang juga belum berlabel).
    /** @return list<array<string, mixed>> */
    public static function newRows(array $position, ?string $lot, ?string $arrived, ?float $qty = null): array
    {
        $qty = $qty === null || $qty <= 0 ? $position['unlabelled'] : min($qty, $position['unlabelled']);

        return [['qty' => $qty, 'supplier_pallet_code' => '', 'lot_no' => $lot ?? '', 'expiry_date' => PalletService::suggestedExpiry($position, $arrived)]];
    }

    /** @return array<string, string> pilihan jenis satuan, teks dalam bahasa layar */
    public static function kindOptions(): array
    {
        return array_map(fn (string $label) => t($label), self::KIND_OPTIONS);
    }

    public static function kind(InvPallet $p): string
    {
        return t($p->isPack() ? 'Remnant' : 'Pallet');
    }

    // Sisa yang ditaruh di tumpukan lain: bin berubah, jadi lewat Stock Transfer (dibuat lalu di-Confirm); satuan itu menjadi
    // paling atas di bin tujuan. Gagal (stok tidak cukup, bin diblokir, approval aktif menolak) = draft dihapus, label tetap.
    /** @return array{ok: bool, message: string} */
    public static function moveTo(InvPallet $pallet, WhLoc $bin): array
    {
        $t = StockTransfer::createFromPallet($pallet);
        $t->lines()->update(['to_location_id' => $bin->location_id]);
        $t->forceFill(['to_warehouse_id' => $bin->warehouse_id, 'reason' => 'CONSOLIDATE', 'notes' => t('Remnant after use: moved to the remnant pile.')])->save();
        $error = StockTransfer::act($t->refresh(), 'confirm');
        if ($error) {
            $t->delete();

            return ['ok' => false, 'message' => str_replace([':bin', ':error'], [$bin->location_code, $error], t('Relabelled. The move to :bin could not be done: :error'))];
        }
        // Approval Stock Transfer aktif: stok dan satuan baru pindah sesudah persetujuan terakhir.
        $text = $t->refresh()->status() === 'SUBMITTED' ? 'Relabelled. The move to :bin waits for approval (Stock Transfer :no).' : 'Relabelled and moved to :bin (Stock Transfer :no).';

        return ['ok' => true, 'message' => str_replace([':bin', ':no'], [$bin->location_code, (string) $t->transfer_no], t($text))];
    }

    // Baris grid palet.
    /** @param  Collection<int, InvPallet>  $pallets */
    public static function rows(Collection $pallets, string $home): array
    {
        $bins = WhLoc::query()->whereKey($pallets->pluck('location_id')->unique()->all())->pluck('location_code', 'location_id');
        $soon = WarehouseStock::expirySoonDays();

        return $pallets->map(function (InvPallet $p) use ($bins, $soon, $home) {
            $code = $p->expiry_date ? ($p->expiryState($soon) ?? 'OK') : 'NONE';

            return ['id' => $p->id_pallet, 'open_url' => $home.'?pallet='.$p->id_pallet, 'no' => $p->pallet_no, 'item' => $p->item_name, 'code' => $p->item_code,
                'kind' => self::kind($p), 'stack' => $p->stack_no,
                'bin' => $bins[$p->location_id] ?? '—', 'qty' => $p->qty, 'initial' => $p->qty_initial, 'unit' => $p->uom_code, 'arrived' => $p->arrived_at?->toDateString(),
                'supplier_code' => $p->supplier_pallet_code, 'lot' => $p->lot_no, 'receipt' => $p->ref_doc_no, 'expiry' => $p->expiry_date?->toDateString(), 'expiry_state' => self::expiry($code),
                'labels' => $p->label_count, 'status' => $p->isActive() ? 'Active' : 'Closed', 'tone' => ['EXPIRED' => 'danger', 'SOON' => 'yellow'][$code] ?? null];
        })->values()->all();
    }

    /** @return Collection<int, InvPallet> palet aktif satu gedung */
    public static function ofWarehouse(int $warehouseId): Collection
    {
        return InvPallet::query()->where('status', 'ACTIVE')->whereIn('location_id', Labels::binIds($warehouseId) ?: [0])->orderBy('arrived_at')->orderBy('id_pallet')->limit(2000)->get();
    }

    /** @return array<int, string> id palet => "PL2600012 | 6,000 LBR | datang 02-Okt-2026" */
    public static function options(Collection $pallets): array
    {
        return $pallets->mapWithKeys(fn (InvPallet $p) => [$p->id_pallet => $p->pallet_no.' | '.($p->stack_no ? t('Stack').' '.$p->stack_no.' | ' : '').Locale::decimal($p->qty, 2).' '.$p->uom_code.' | '.Locale::d($p->arrived_at)
            .($p->supplier_pallet_code ? ' | '.$p->supplier_pallet_code : '')])->all();
    }

    // Riwayat satu palet (lacak): dibuat, label ulang, pindah, ditutup.
    /** @return list<array<string, mixed>> */
    public static function history(InvPallet $p): array
    {
        $bins = WhLoc::query()->whereKey($p->logs->pluck('from_location_id')->merge($p->logs->pluck('to_location_id'))->filter()->unique()->all())->pluck('location_code', 'location_id');
        $actions = array_map(fn (string $a) => t($a), self::ACTIONS);

        return $p->logs->map(fn ($l) => ['id' => $l->id_pallet_log, 'at' => $l->created_at?->toDateTimeString(), 'action' => $actions[$l->action] ?? $l->action, 'before' => $l->qty_before, 'after' => $l->qty_after,
            'from' => $bins[$l->from_location_id] ?? null, 'to' => $bins[$l->to_location_id] ?? null, 'ref' => $l->ref_no, 'note' => $l->note, 'by' => $l->created_by])->values()->all();
    }
}
