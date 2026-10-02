<?php

namespace App\Assistant\Metrics;

use App\Models\Inventory\StockOnhand;
use App\Models\Sys\SysConstant;
use Assistant\Assistant;
use Assistant\Contracts\Asker;
use Assistant\Contracts\Metric;
use Assistant\Reply;
use Devlib\Locale;

// "Deadstock" (user 2026-10-02): stok yang tidak bergerak lebih dari 6 bulan, dinilai harga beli rata-rata.
// Sumber = view saldo stok (v_rm / v_fg / v_sfg_onhand): value_onhand = nilai saldo kartu stok (harga rata-rata bergerak).
// Satu item dihitung sekali dari pergerakan TERAKHIRNYA di lokasi mana pun yang bisa dipakai; item yang masih ada stoknya
// tetapi tidak punya tanggal pergerakan (saldo warisan) IKUT -- berbeda dari laporan Slow Moving Item yang membuangnya.
// Pergerakan = kolom last_item_movement view saldo: Stock Transfer (pindah bin / gudang) TIDAK dihitung sebagai gerak,
// jadi stok mati tidak "hidup lagi" karena dipindah (sama dengan Warehouse Map, App\Services\Inventory\WarehouseStock).
final class DeadstockValue implements Metric
{
    private const CLASSES = ['RM' => ['Raw Material', 'INV_SLOW_RM'], 'SFG' => ['Semi Finished Goods', 'INV_SLOW_SFG'], 'FG' => ['Finished Goods', 'INV_SLOW_FG']];

    public function key(): string
    {
        return 'deadstock_value';
    }

    public function title(): string
    {
        return 'Deadstock value';
    }

    public function phrases(): array
    {
        return ['deadstock', 'dead stock', 'stok mati', 'stock mati', 'barang mati', 'stok tidak bergerak', 'stok tidak jalan', 'stok menganggur', 'stok lama'];
    }

    public function module(): ?string
    {
        return 'INV';
    }

    public function minLevel(): int
    {
        return 0;
    }

    public function suggest(): bool
    {
        return true;
    }

    public static function months(): int
    {
        return max(1, (int) SysConstant::number('ASSISTANT_DEADSTOCK_MONTHS', 6));
    }

    // unvalued = item deadstock yang kartu stoknya tidak punya nilai (harga rata-rata kosong): rupiahnya belum bisa dipercaya.
    /** @return array<string, array{items: int, value: float, unvalued: int}> per kelas stok (RM / SFG / FG) */
    public static function totals(): array
    {
        $cutoff = now()->subMonths(self::months())->toDateString();
        $out = [];
        foreach (array_keys(self::CLASSES) as $class) {
            $key = StockOnhand::ITEM_KEYS[$class];
            $dead = StockOnhand::of($class)->where('is_usable', true)->groupBy($key)
                ->selectRaw("{$key} AS row_id, SUM(qty_onhand) AS qty, SUM(value_onhand) AS value, MAX(last_item_movement) AS moved")
                ->havingRaw('SUM(qty_onhand) > 0')->get()
                ->filter(function ($r) use ($cutoff) {
                    $moved = substr((string) $r->moved, 0, 10);

                    return $moved === '' || str_starts_with($moved, '0000') || $moved < $cutoff;
                });
            $out[$class] = ['items' => $dead->count(), 'value' => (float) $dead->sum('value'), 'unvalued' => $dead->filter(fn ($r) => (float) $r->value <= 0)->count()];
        }

        return $out;
    }

    public function answer(Asker $asker, string $question): Reply
    {
        $totals = self::totals();
        $rows = [];
        foreach (self::CLASSES as $class => [$label]) {
            $rows[] = [Assistant::text($label).', '.Assistant::text(':n items', ['n' => $totals[$class]['items']]), Locale::money($totals[$class]['value'])];
        }
        $note = Assistant::text('Stock still on hand in usable locations whose last movement was before :date, or that never moved, valued at the average purchase price. Data at :time.',
            ['date' => Locale::d(now()->subMonths(self::months())), 'time' => Locale::time()]);
        // Angka yang kurang tidak boleh tampil seolah lengkap: item tanpa nilai stok disebut terang-terangan.
        if ($unvalued = array_sum(array_column($totals, 'unvalued'))) {
            $note = Assistant::text(':n of :all deadstock items have no stock value on their stock card, so the rupiah total is too low.',
                ['n' => $unvalued, 'all' => array_sum(array_column($totals, 'items'))]).' '.$note;
        }
        $reply = new Reply(Reply::NUMBER, Assistant::text('Deadstock: no movement for more than :n months', ['n' => self::months()]),
            big: Locale::money(array_sum(array_column($totals, 'value'))), rows: $rows, note: $note);
        foreach (self::CLASSES as [$label, $code]) {
            $reply->open(Assistant::text('Open :screen', ['screen' => 'Slow Moving Item, '.$label]), route('v2.item', $code), $code, 'fa-solid fa-hourglass-half');
        }

        return $reply;
    }
}
