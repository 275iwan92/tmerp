<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockReversalService;
use Devlib\Locale;

// Stock Card semua kelas stok untuk satu periode (user 2026-10-03): kepala = nama item lalu periode; rinci = Date, Location, BB, In, Out, Adj, EB.
// RUMUS BERSAMBUNG (user 2026-10-08: "kalau terakhir EB 5000 berikutnya sambung BB nya ya 5000"):
//   BB = saldo ITEM (semua lokasi) sebelum baris itu, EB = BB + In - Out + Adj, dan BB baris berikutnya = EB baris sebelumnya.
//   (Dulu BB / EB = saldo satu lokasi, jadi baris berurutan dari lokasi berbeda tidak bersambung dan Total tidak cocok dengan baris di atasnya.)
//   Stock Transfer = Out di lokasi asal dan In di lokasi tujuan (dua baris), jadi saldo item turun lalu naik kembali dan totalnya tetap.
//   Saldo satu lokasi (qty_eb kartu stok) tetap ada sebagai kolom opsional "Location Balance" (tersembunyi secara default).
//   Baris Total tiap item: BB = saldo semua lokasi sebelum periode = BB baris pertama, EB = saldo semua lokasi di akhir periode = EB baris terakhir.
// Versi gudang: TANPA harga dan nilai -- kolomnya tidak ikut di baris sama sekali (inventory_tier1 bagian 7, Sisi B).
// Kartu satu item: isi kotak Item (kode atau nama) lalu Show; cetakannya = kartu stok item itu.
class StockCardAllReport extends AllStockReport
{
    // Batas baris di layar: di atas ini periode dipersempit atau item diisi (grid memuat semua baris sekaligus).
    private const MAX_ROWS = 20000;

    private const TOTAL = 'Total';

    private bool $truncated = false;

    /** @var array<string, list<array<string, mixed>>> baris per periode + saringan (layar, cetakan, dan ringkasan memakai hasil yang sama) */
    private array $built = [];

    public function slug(): string
    {
        return 'inv-rpt-card';
    }

    public function title(): string
    {
        return 'Stock Card';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clipboard-list';
    }

    public function params(): array
    {
        return ['item' => 'Item'];
    }

    public function guide(): ?string
    {
        return $this->truncated ? 'Only the first 20,000 rows are shown. Shorten the period or fill in Item, then click Show.'
            : 'Stock card per item for the period. Each row is one movement: BB = balance of the item before it (the same as the EB of the row above), In, Out, Adj, EB = balance after it. The Total row covers all locations of the item. For one item, type its code or name in Item and click Show.';
    }

    // Kotak cari grid hanya membaca kolom yang tampil (tanggal, lokasi, angka); item dipilih lewat kotak Item.
    public function search(): string
    {
        return 'Search...';
    }

    public function groupBy(): ?string
    {
        return 'card';
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    // Susunan kolom berubah 2026-10-03 dan 2026-10-08 (kolom opsional Location Balance): pilihan kolom / urutan yang tersimpan di browser untuk susunan lama tidak dipakai lagi.
    public function props(): array
    {
        return ['id' => 'inv-rpt-card-chain'] + parent::props();
    }

    public function rows(): array
    {
        [$from, $to] = $this->period();
        $cacheKey = $from->toDateString().':'.$to->toDateString().':'.$this->param('item');
        if (isset($this->built[$cacheKey])) {
            return $this->built[$cacheKey];
        }

        $ledger = $this->ledger();
        $items = $ledger->items();
        $this->loadMeta($items);
        $find = mb_strtolower($this->param('item'));
        $cancelled = $this->cancelledDocs($ledger->movements());
        $moved = [];
        foreach ($ledger->movements() as $m) {
            $moved[StockLedger::itemKey($m['class'], $m['item_id'])][] = $m;
        }

        // Tanpa saringan: item yang bergerak di periode ini. Dengan saringan: semua item yang cocok, juga yang hanya punya saldo.
        $cards = [];
        foreach ($items as $key => $i) {
            $meta = $this->meta($i['class'], $i['item_id']);
            $match = $find === '' ? isset($moved[$key]) : str_contains(mb_strtolower($meta['code'].' '.$meta['name']), $find);
            if ($match) {
                $cards[$meta['code'].' '.$key] = [$key, $i, $meta];
            }
        }
        ksort($cards);

        $rows = [];
        $period = Locale::date($from).' – '.Locale::date($to);
        foreach ($cards as [$key, $i, $meta]) {
            // Kepala kartu = nama item, lalu periode (kode dan satuan menyertai nama).
            $base = ['card' => implode(' · ', array_filter([$meta['name'], $meta['code'], $i['uom'], $period])), 'item' => $meta['name'], 'item_code' => $meta['code'],
                'class' => self::CLASSES[$i['class']], 'unit' => $i['uom']];
            $in = $out = $adj = 0.0;
            // Saldo berjalan item (semua lokasi): mulai dari saldo awal item, tiap baris menyambung ke EB baris sebelumnya.
            $running = round((float) $i['opening_qty'], 4);
            foreach ($moved[$key] ?? [] as $m) {
                $in += $m['qty_in'];
                $out += $m['qty_out'];
                $adj += $m['adj'];
                $bb = $running;
                $running = round($bb + $m['qty_in'] - $m['qty_out'] + $m['adj'], 4);
                $rows[] = ['id' => $m['class'].'-'.$m['doc_id'], 'doc_date' => $m['date'], 'location' => implode(' · ', array_filter([$m['warehouse'], $m['location']])),
                    'bb' => $bb, 'in' => $m['qty_in'], 'out' => $m['qty_out'], 'adj' => $m['adj'], 'eb' => $running, 'loc_eb' => $m['location_balance'], 'doc_no' => $this->docLabel($m, $cancelled), 'move' => $m['kind'],
                    'lot' => $m['lot'], 'ref_no' => $m['ref_no'], 'warehouse' => $m['warehouse'], 'tone' => null, 'unit' => $m['uom'] ?: $i['uom']] + $base;
            }
            // Rantai harus berakhir di saldo akhir semua lokasi; bila data lama tidak konsisten, baris Total diberi warna bahaya (tidak disembunyikan).
            $mismatch = abs($running - (float) $i['closing_qty']) > 0.0001;
            $rows[] = ['id' => 'T-'.$key, 'doc_date' => null, 'location' => self::TOTAL, 'bb' => $i['opening_qty'], 'in' => $in, 'out' => $out, 'adj' => $adj, 'eb' => $i['closing_qty'], 'loc_eb' => null,
                'doc_no' => null, 'move' => self::TOTAL, 'lot' => null, 'ref_no' => null, 'warehouse' => null, 'tone' => $mismatch ? 'danger' : 'accent'] + $base;
            if (count($rows) > self::MAX_ROWS) {
                $this->truncated = true;
                break;
            }
        }

        return $this->built[$cacheKey] = $rows;
    }

    // Dokumen yang dibatalkan dan pembaliknya tetap tampil di kartu (rantai tetap bersambung); diberi tanda di Doc No.
    private function cancelledDocs(array $movements): array
    {
        $nos = collect($movements)->pluck('doc_no')->filter()->unique()->all();
        if (! $nos) {
            return [];
        }

        return collect([RmStock::class, SfgStock::class, FgStock::class])
            ->flatMap(fn ($h) => $h::query()->whereIn('doc_no', $nos)->where('stat_cancel', true)->pluck('doc_no'))->flip()->all();
    }

    private function docLabel(array $m, array $cancelled): ?string
    {
        return match (true) {
            $m['doc_cat'] === StockReversalService::DOC_CAT => $m['doc_no'].' · '.t('Reversal'),
            isset($cancelled[$m['doc_no']]) => $m['doc_no'].' · '.t('Cancelled'),
            default => $m['doc_no'],
        };
    }

    // Urutan baris = urutan kartu (tanggal, lalu urutan posting): kolom tidak bisa diurutkan. BB, In, Out, Adj, EB = istilah
    // tetap di semua bahasa (raw: tidak diterjemahkan kamus).
    public function columns(): array
    {
        $fixed = ['sortable' => false];

        return [
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'] + $fixed,
            ['key' => 'location', 'label' => 'Location'] + $fixed,
            ['key' => 'bb', 'label' => raw('BB'), 'type' => 'number'] + $fixed,
            ['key' => 'in', 'label' => raw('In'), 'type' => 'number'] + $fixed,
            ['key' => 'out', 'label' => raw('Out'), 'type' => 'number'] + $fixed,
            ['key' => 'adj', 'label' => raw('Adj'), 'type' => 'number'] + $fixed,
            ['key' => 'eb', 'label' => raw('EB'), 'type' => 'number'] + $fixed,
            ['key' => 'loc_eb', 'label' => 'Location Balance', 'type' => 'number', 'visible' => false] + $fixed,
            ['key' => 'card', 'label' => 'Item', 'visible' => false] + $fixed,
            ['key' => 'doc_no', 'label' => 'Doc No.', 'visible' => false] + $fixed,
            ['key' => 'move', 'label' => 'Movement', 'type' => 'badge', 'visible' => false, 'tones' => ['In' => 'success', 'Out' => 'danger', 'Adjustment' => 'warn', 'Opening' => 'neutral',
                'Value adjustment' => 'info', 'Transfer in' => 'info', 'Transfer out' => 'info', self::TOTAL => 'neutral']] + $fixed,
            ['key' => 'lot', 'label' => 'Lot No.', 'visible' => false] + $fixed,
            ['key' => 'ref_no', 'label' => 'Reference', 'visible' => false] + $fixed,
            ['key' => 'unit', 'label' => 'Unit', 'visible' => false] + $fixed,
            ['key' => 'warehouse', 'label' => 'Warehouse', 'visible' => false] + $fixed,
            ['key' => 'class', 'label' => 'Class', 'visible' => false] + $fixed,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'In', 'where' => ['move' => 'In'], 'tone' => 'success'],
            ['label' => 'Out', 'where' => ['move' => 'Out'], 'tone' => 'danger'],
            ['label' => 'Adjustment', 'where' => ['move' => 'Adjustment'], 'tone' => 'warn'],
            ['label' => 'Transfer', 'where' => ['move' => ['Transfer in', 'Transfer out']], 'tone' => 'info'],
        ];
    }

    public function filters(): array
    {
        return ['move' => 'Movement', 'class' => 'Class', 'warehouse' => 'Warehouse', 'location' => 'Location'];
    }

    public function groups(): array
    {
        return ['card' => 'Item'];
    }

    // Cetakan: baris Total tiap item diberi sorot. Kartu satu item: kriteria dibuka dengan nama item, lalu periode.
    public function printData(): array
    {
        $data = parent::printData();
        $data['columns'] = array_map(fn (array $c) => $c['key'] === 'location' ? $c + ['rowTones' => [self::TOTAL => 'warn']] : $c, $data['columns']);
        // Rows = jumlah pergerakan (baris Total bukan pergerakan).
        $data['criteria']['Rows'] = Locale::number(count(array_filter($data['rows'], fn (array $r) => $r['move'] !== self::TOTAL)));
        $one = $this->singleCard();
        if ($one) {
            $data['criteria'] = ['Item' => raw($one['item'].' ('.$one['item_code'].')')] + array_diff_key($data['criteria'], ['Item' => true]) + ['Unit' => raw((string) $one['unit'])];
        }

        return $data;
    }

    // Kartu satu item: ringkasan BB, In, Out, Adj, EB item itu. Banyak item: tanpa ringkasan (satuan tiap item berbeda).
    public function printSummary(): array
    {
        $one = $this->singleCard();

        return $one ? [['label' => 'BB', 'value' => $one['bb']], ['label' => 'In', 'value' => $one['in']], ['label' => 'Out', 'value' => $one['out']],
            ['label' => 'Adj', 'value' => $one['adj'], 'signed' => true], ['label' => 'EB', 'value' => $one['eb']]] : [];
    }

    /** @return ?array<string, mixed> baris Total bila laporan memuat tepat satu item */
    private function singleCard(): ?array
    {
        $totals = array_values(array_filter($this->rows(), fn (array $r) => $r['move'] === self::TOTAL));

        return count($totals) === 1 ? $totals[0] : null;
    }
}
