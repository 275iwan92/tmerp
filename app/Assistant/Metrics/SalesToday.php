<?php

namespace App\Assistant\Metrics;

use App\Assistant\ErpAsker;
use App\Models\Sdi\CoOrder;
use Assistant\Assistant;
use Assistant\Contracts\Asker;
use Assistant\Contracts\Metric;
use Assistant\Reply;
use Devlib\Locale;
use Illuminate\Support\Collection;

// "Sales today" (user 2026-10-02): nilai rupiah Customer Order yang masuk hari ini, dijawab terinci Draft / Confirmed / Total.
// CO Cancelled tidak dihitung. Nilai = jumlah subtotal baris (sama dengan Order Value di Customer Order Summary).
// Rupiah hanya untuk yang berhak melihat harga jual (SecUser::canViewSalesPrice, aturan A.9); lainnya jumlah dokumen saja.
final class SalesToday implements Metric
{
    private const REPORT = 'SALES_CO_RPT_SUMMARY';

    public function key(): string
    {
        return 'sales_today';
    }

    public function title(): string
    {
        return 'Sales today';
    }

    public function phrases(): array
    {
        return ['sales hari ini', 'penjualan hari ini', 'omzet hari ini', 'omset hari ini', 'order masuk hari ini', 'order hari ini', 'co hari ini',
            'sales today', 'orders today', 'order value today'];
    }

    public function module(): ?string
    {
        return 'SALES';
    }

    public function minLevel(): int
    {
        return 0;
    }

    public function suggest(): bool
    {
        return true;
    }

    public function answer(Asker $asker, string $question): Reply
    {
        $orders = CoOrder::query()->whereDate('co_date', now()->toDateString())->where('stat_cancel', false)->where('doc_status', '<>', 'CANCELLED')
            ->withSum('items as value', 'subtotal')->get(['id_order', 'doc_status', 'currency_code']);
        $price = $asker instanceof ErpAsker && $asker->user->canViewSalesPrice();
        $documents = fn (Collection $set) => Assistant::text(':n documents', ['n' => $set->count()]);
        $line = fn (string $label, Collection $set) => $price
            ? [Assistant::text($label).', '.$documents($set), Locale::money($set->sum('value'))]
            : [Assistant::text($label), $documents($set)];

        $confirmed = $orders->whereIn('doc_status', ['CONFIRMED', 'CLOSED']);
        $waiting = $orders->where('doc_status', 'SUBMITTED');
        $draft = $orders->whereNotIn('doc_status', ['CONFIRMED', 'CLOSED', 'SUBMITTED']);

        $rows = [$line('Draft', $draft)];
        if ($waiting->isNotEmpty()) {
            $rows[] = $line('Waiting for approval', $waiting);
        }
        array_push($rows, $line('Confirmed', $confirmed), $line('Total', $orders));

        $note = Assistant::text('Customer Orders dated today, without the cancelled ones. Data at :time.', ['time' => Locale::time()]);
        if (! $price) {
            $note = Assistant::text('Your account sees the number of documents; the rupiah value follows the selling-price right.').' '.$note;
        } elseif ($orders->pluck('currency_code')->filter()->unique()->count() > 1) {
            $note = Assistant::text('Orders in more than one currency are added as they are.').' '.$note;
        }

        return (new Reply(Reply::NUMBER, Assistant::text('Sales today').', '.Locale::d(now()),
            big: $price ? Locale::money($orders->sum('value')) : $documents($orders), rows: $rows, note: $note))
            ->open(Assistant::text('Open :screen', ['screen' => 'Customer Order Summary']), route('v2.item', self::REPORT), self::REPORT, 'fa-solid fa-chart-pie');
    }
}
