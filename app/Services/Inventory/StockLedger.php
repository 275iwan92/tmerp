<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\InvMovement;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStockcard;
use Carbon\CarbonImmutable;

// Buku stok SATU PERIODE untuk laporan (Stock Card, Stock Summary, Stock Recapitulation): saldo awal tiap posisi, pergerakan
// di dalam periode, saldo akhir -- jumlah dan nilai, semua kelas stok (RM / SFG / FG). Hanya MEMBACA kartu stok; yang
// menulis kartu tetap StockPostingService dan StockTransferService.
//   posisi      = satu item di satu bin (FG: product + part), kunci "KELAS|item|part|bin".
//   saldo       = baris kartu TERAKHIR (doc_date, doc_id) posisi itu -- sama dengan view v_*_onhand.
//   saldo awal  = saldo tepat sebelum hari pertama periode; saldo akhir = saldo di akhir hari terakhir periode.
//   nilai gerak = nilai posisi sesudah baris itu dikurangi nilai sebelumnya (harga rata-rata bergerak), jadi
//                 nilai awal + masuk - keluar + penyesuaian = nilai akhir selalu cocok.
//   transfer    = pindah bin / gudang (doc_type TF): mengubah posisi, TIDAK dihitung masuk atau keluar item.
final class StockLedger
{
    // kelas => [model kartu, kolom item, kolom kunci saldo]
    private const CARDS = ['RM' => [RmStockcard::class, 'material_id', 'material_id'], 'SFG' => [SfgStockcard::class, 'parts_id', 'parts_id'],
        'FG' => [FgStockcard::class, 'product_id', 'c_sku_key']];

    private const ZERO = ['opening_qty' => 0.0, 'in' => 0.0, 'out' => 0.0, 'adj' => 0.0, 'transfer' => 0.0, 'closing_qty' => 0.0, 'opening_value' => 0.0,
        'in_value' => 0.0, 'out_value' => 0.0, 'adj_value' => 0.0, 'transfer_value' => 0.0, 'closing_value' => 0.0, 'moves' => 0];

    /** @var array<string, self> satu buku per periode per permintaan (beberapa laporan / cetakan memakai periode yang sama) */
    private static array $made = [];

    /** @var ?array<string, array<string, mixed>> */
    private ?array $positions = null;

    /** @var ?list<array<string, mixed>> */
    private ?array $movements = null;

    private function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return self::$made[$from->toDateString().':'.$to->toDateString()] ??= new self($from->startOfDay(), $to->endOfDay());
    }

    public static function forget(): void
    {
        self::$made = [];
    }

    public static function itemKey(string $class, int $itemId): string
    {
        return $class.'|'.$itemId;
    }

    // Jenis gerak satu baris kartu: Transfer in / Transfer out / Adjustment / In / Out / Opening (baris tanpa jumlah).
    public static function kind(?string $docType, float $in, float $out, float $adj): string
    {
        return match (true) {
            $docType === 'TF' => $in > 0 ? 'Transfer in' : 'Transfer out',
            $adj != 0.0 => 'Adjustment',
            $in > 0 => 'In',
            $out > 0 => 'Out',
            default => 'Opening',
        };
    }

    /** @return list<array<string, mixed>> baris kartu di dalam periode, urut tanggal lalu id, dengan nilai gerak dan saldo berjalan item */
    public function movements(): array
    {
        $this->build();

        return $this->movements;
    }

    /** @return array<string, array<string, mixed>> posisi (item x bin) yang punya saldo awal atau bergerak di periode ini */
    public function positions(): array
    {
        $this->build();

        return $this->positions;
    }

    /** @return array<string, array<string, mixed>> satu baris per item (kunci "KELAS|item"): jumlah semua posisinya */
    public function items(): array
    {
        $items = [];
        foreach ($this->positions() as $p) {
            $key = self::itemKey($p['class'], $p['item_id']);
            $items[$key] ??= ['class' => $p['class'], 'item_id' => $p['item_id'], 'uom' => $p['uom']] + self::ZERO;
            foreach (array_keys(self::ZERO) as $k) {
                $items[$key][$k] += $p[$k];
            }
            $items[$key]['uom'] ??= $p['uom'];
        }

        return $items;
    }

    private function build(): void
    {
        if ($this->positions !== null) {
            return;
        }
        $positions = $this->opening();
        $itemBalance = [];
        foreach ($positions as $p) {
            $key = self::itemKey($p['class'], $p['item_id']);
            $itemBalance[$key] = ($itemBalance[$key] ?? 0.0) + $p['opening_qty'];
        }

        $movements = [];
        $rows = InvMovement::query()->whereBetween('doc_date', [$this->from->toDateTimeString(), $this->to->toDateTimeString()])->orderBy('doc_date')->orderBy('doc_id')->get();
        foreach ($rows as $r) {
            $class = (string) $r->stock_class;
            $itemId = (int) $r->item_id;
            $posKey = self::positionKey($class, $itemId, $class === 'FG' ? (int) $r->parts_id : 0, (int) $r->location_id);
            $positions[$posKey] ??= ['class' => $class, 'item_id' => $itemId, 'location_id' => (int) $r->location_id, 'warehouse_id' => (int) $r->warehouse_id, 'uom' => $r->uom_code] + self::ZERO;
            $p = &$positions[$posKey];

            $in = (float) $r->qty_in;
            $out = (float) $r->qty_out;
            $adj = (float) $r->qty_adj;
            $kind = self::kind($r->doc_type, $in, $out, $adj);
            $valueAfter = $r->value_eb !== null ? (float) $r->value_eb : (float) $r->qty_eb * (float) $r->avg_unitprice;
            $value = $valueAfter - $p['closing_value'];
            $transfer = str_starts_with($kind, 'Transfer');

            if ($transfer) {
                $p['transfer'] += $in - $out;
                $p['transfer_value'] += $value;
            } else {
                $p['in'] += $in;
                $p['out'] += $out;
                $p['adj'] += $adj;
                $p['moves']++;
                match ($kind) {
                    'Adjustment' => $p['adj_value'] += $value,
                    'Out' => $p['out_value'] -= $value,
                    default => $p['in_value'] += $value,
                };
            }
            $p['closing_qty'] = (float) $r->qty_eb;
            $p['closing_value'] = $valueAfter;
            $p['uom'] ??= $r->uom_code;
            unset($p);

            $itemKey = self::itemKey($class, $itemId);
            $itemBalance[$itemKey] = ($itemBalance[$itemKey] ?? 0.0) + ($transfer ? 0.0 : $in - $out + $adj);
            $movements[] = ['class' => $class, 'doc_id' => (int) $r->doc_id, 'date' => $r->doc_date?->toDateString(), 'doc_no' => $r->doc_no, 'doc_cat' => $r->doc_cat, 'kind' => $kind,
                'item_id' => $itemId, 'item_code' => $r->item_code, 'item_name' => $r->item_name, 'warehouse' => $r->warehouse_name, 'location' => $r->location_name, 'lot' => $r->lot_no,
                'ref_no' => $r->ref_doc_no, 'in' => $transfer ? 0.0 : $in, 'out' => $transfer ? 0.0 : $out, 'adj' => $adj, 'transfer' => $transfer ? $in - $out : 0.0,
                'location_balance' => (float) $r->qty_eb, 'item_balance' => $itemBalance[$itemKey], 'value' => $value, 'avg_price' => $r->avg_unitprice === null ? null : (float) $r->avg_unitprice,
                'uom' => $r->uom_code];
        }

        $this->positions = $positions;
        $this->movements = $movements;
    }

    private static function positionKey(string $class, int $itemId, int $partId, int $locationId): string
    {
        return $class.'|'.$itemId.'|'.$partId.'|'.$locationId;
    }

    // Saldo tiap posisi tepat sebelum periode: baris kartu terakhir (doc_date, doc_id) sebelum hari pertama. Posisi kosong dibuang.
    private function opening(): array
    {
        $positions = [];
        $stamp = fn (string $alias) => "CONCAT(DATE_FORMAT({$alias}.doc_date, '%Y%m%d%H%i%s'), LPAD({$alias}.doc_id, 12, '0'))";
        foreach (self::CARDS as $class => [$model, $item, $key]) {
            $table = (new $model)->getTable();
            $last = $model::query()->from($table.' as x')->where('x.doc_date', '<', $this->from->toDateTimeString())->groupBy('x.'.$key, 'x.location_id')
                ->selectRaw("x.{$key} AS k, x.location_id AS loc, MAX({$stamp('x')}) AS mx");
            $rows = $model::query()->from($table.' as sc')
                ->joinSub($last, 'l', fn ($j) => $j->on('sc.'.$key, '=', 'l.k')->on('sc.location_id', '=', 'l.loc')->whereRaw($stamp('sc').' = l.mx'))
                ->selectRaw("sc.{$item} AS item_id, ".($class === 'FG' ? 'IFNULL(sc.parts_id, 0)' : '0').' AS part_id, sc.location_id, sc.warehouse_id, sc.qty_eb, sc.value_eb, sc.avg_unitprice, sc.uom_code')
                ->get();
            foreach ($rows as $r) {
                $qty = (float) $r->qty_eb;
                $value = $r->value_eb !== null ? (float) $r->value_eb : $qty * (float) $r->avg_unitprice;
                if ($qty == 0.0 && $value == 0.0) {
                    continue;
                }
                $positions[self::positionKey($class, (int) $r->item_id, (int) $r->part_id, (int) $r->location_id)] = ['class' => $class, 'item_id' => (int) $r->item_id,
                    'location_id' => (int) $r->location_id, 'warehouse_id' => (int) $r->warehouse_id, 'uom' => $r->uom_code]
                    + ['opening_qty' => $qty, 'closing_qty' => $qty, 'opening_value' => $value, 'closing_value' => $value] + self::ZERO;
            }
        }

        return $positions;
    }
}
