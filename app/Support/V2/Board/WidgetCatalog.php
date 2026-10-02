<?php

namespace App\Support\V2\Board;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysWidget;
use App\Support\V2\Board\Widgets\DeadstockValue;
use App\Support\V2\Board\Widgets\MaterialShortage;
use App\Support\V2\Board\Widgets\NewOrders;
use App\Support\V2\Board\Widgets\OnTimeDelivery;
use App\Support\V2\Board\Widgets\OrdersByStatus;
use App\Support\V2\Board\Widgets\OverdueReceivables;
use App\Support\V2\Board\Widgets\QuoteOfDay;
use App\Support\V2\Board\Widgets\ReceivablesAging;
use App\Support\V2\Board\Widgets\RevenueMonth;
use App\Support\V2\Board\Widgets\RevenueTrend;
use App\Support\V2\Board\Widgets\SalesPipeline;
use App\Support\V2\Board\Widgets\SalesRanking;
use App\Support\V2\Board\Widgets\SalesToday;
use App\Support\V2\Board\Widgets\TargetsByArea;
use App\Support\V2\Board\Widgets\TopCustomers;
use App\Support\V2\Board\Widgets\WaitingDecision;

// Katalog widget = kelas widget (daftar di bawah) + baris sys_widget yang dirawat user (judul, kelompok, modul, level minimum,
// frasa asisten, aktif). Kelas baru otomatis mendapat baris dari nilai awal kelasnya -- pola yang sama dengan katalog
// pertanyaan asisten (Assistant\MetricCatalog). Widget baru = satu kelas + satu baris di CLASSES.
final class WidgetCatalog
{
    // Yang boleh membuka Laporan Manajemen boleh memasang widget semua bagian (sama dengan aturan asisten, App\Assistant\ErpAsker).
    private const MANAGEMENT_REPORTS = 'MGMT';

    public const CLASSES = [
        RevenueMonth::class, NewOrders::class, OverdueReceivables::class, OnTimeDelivery::class, SalesToday::class, RevenueTrend::class, TargetsByArea::class,
        SalesPipeline::class, SalesRanking::class, WaitingDecision::class, OrdersByStatus::class, TopCustomers::class, ReceivablesAging::class,
        MaterialShortage::class, DeadstockValue::class, QuoteOfDay::class,
    ];

    /** @var ?array<string, array{widget: Widget, row: SysWidget}> */
    private ?array $rows = null;

    /** @return array<string, array{widget: Widget, row: SysWidget}> widget aktif, kunci = widget_key, urut sort_order */
    public function all(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $saved = SysWidget::query()->get()->keyBy('widget_key');
        $out = [];
        foreach (self::CLASSES as $i => $class) {
            /** @var Widget $widget */
            $widget = new $class;
            $row = $saved->get($widget->key()) ?? SysWidget::create(['widget_key' => $widget->key(), 'title' => $widget->title(), 'description' => $widget->description(),
                'group_name' => $widget->group(), 'module_code' => $widget->module(), 'min_level' => $widget->minLevel(), 'default_size' => $widget->size(),
                'phrases' => implode(', ', $widget->phrases()), 'sort_order' => ($i + 1) * 10, 'activate' => true, 'created_by' => 'board', 'updated_by' => 'board']);
            if ($row->activate) {
                $out[$widget->key()] = ['widget' => $widget, 'row' => $row];
            }
        }
        uasort($out, fn ($a, $b) => $a['row']->sort_order <=> $b['row']->sort_order);

        return $this->rows = $out;
    }

    // Hak pasang: modul widget (atau Laporan Manajemen) + level minimum + hak lihat harga jual untuk widget berisi rupiah penjualan.
    public function allowed(SecUser $user, string $key): bool
    {
        $entry = $this->all()[$key] ?? null;
        if (! $entry) {
            return false;
        }
        if ($user->is_admin) {
            return true;
        }
        $module = $entry['row']->module_code;

        return (! $module || $user->canAccessModule($module) || $user->canAccessModule(self::MANAGEMENT_REPORTS))
            && $user->maxMgmtLevel() >= (int) $entry['row']->min_level
            && (! $entry['widget']->needsSalesPrice() || $user->canViewSalesPrice());
    }

    /** @return array<string, array{widget: Widget, row: SysWidget}> */
    public function for(SecUser $user): array
    {
        return array_filter($this->all(), fn ($entry, $key) => $this->allowed($user, $key), ARRAY_FILTER_USE_BOTH);
    }

    // Widget yang paling cocok dengan teks bebas (perintah asisten): judul atau frasa yang semua katanya ada di teks; yang terpanjang menang.
    public function match(SecUser $user, string $text): ?string
    {
        $words = self::words($text);
        $best = null;
        $bestLen = 0;
        foreach ($this->for($user) as $key => $entry) {
            $phrases = array_filter(array_map('trim', [...explode(',', (string) $entry['row']->phrases), $entry['row']->title, t($entry['row']->title)]));
            foreach ($phrases as $phrase) {
                $need = self::words($phrase);
                if ($need && count($need) > $bestLen && ! array_diff($need, $words)) {
                    $best = $key;
                    $bestLen = count($need);
                }
            }
        }

        return $best;
    }

    /** @return array<int, string> */
    private static function words(string $text): array
    {
        return array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: []));
    }
}
