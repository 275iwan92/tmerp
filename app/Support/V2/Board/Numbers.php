<?php

namespace App\Support\V2\Board;

use App\Models\Master\CustCustomer;
use App\Models\Master\SalesPerson;
use App\Models\Master\SysHoliday;
use App\Models\Sdi\CoOrder;
use App\Models\Sys\SysKpiTarget;
use App\Support\V2\Data\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Angka opening screen: SATU tempat hitung untuk semua widget, supaya angka yang sama di dua widget pasti sama dan bisa diuji
// (tests/Feature/OpeningBoardTest). Definisi (ditulis juga di docs/md_for_systems/openingboard_review.md):
//   Order value     = jumlah subtotal baris Customer Order berstatus Confirmed / Closed, menurut tanggal CO; Cancelled tidak dihitung
//   New orders      = jumlah dokumen Customer Order menurut tanggal CO, semua status kecuali Cancelled
//   New customers   = customer yang Customer Order pertamanya (tidak batal) jatuh di periode itu
//   On-time         = Delivery Order customer yang berangkat (actual_ship_date) di periode itu, tepat bila <= janji kirim baris CPDEL
//   Overdue         = sisa tagihan customer (v1_fin_ar_aging) yang lewat jatuh tempo, per hari ini
// Relasi ke tm2mst (nama customer / sales person) = query terpisah, dicocokkan di PHP (aturan lintas database).
final class Numbers
{
    public const CONFIRMED = ['CONFIRMED', 'CLOSED'];

    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(public readonly CarbonImmutable $now) {}

    public static function make(): self
    {
        return new self(CarbonImmutable::now(config('app.timezone')));
    }

    private function once(string $key, callable $fn): mixed
    {
        return $this->memo[$key] ??= $fn();
    }

    // Tanggal 1 bulan ke-$back sebelum bulan berjalan.
    public function month(int $back = 0): CarbonImmutable
    {
        return $this->now->startOfMonth()->subMonthsNoOverflow($back);
    }

    /** @return Collection<int, object{id_order: int, co_date: string, doc_status: string, customer_id: ?int, sales_person_id: ?int, value: float}> CO tidak batal di rentang tanggal */
    public function orders(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->once('orders:'.$from->toDateString().':'.$to->toDateString(), fn () => CoOrder::query()
            ->whereBetween('co_date', [$from->toDateString(), $to->toDateString()])->where('stat_cancel', false)->where('doc_status', '<>', 'CANCELLED')
            ->withSum('items as value', 'subtotal')->get(['id_order', 'co_date', 'doc_status', 'customer_id', 'sales_person_id'])
            ->map(fn (CoOrder $o) => (object) ['id_order' => (int) $o->id_order, 'co_date' => substr((string) $o->co_date, 0, 10), 'doc_status' => (string) $o->doc_status,
                'customer_id' => $o->customer_id, 'sales_person_id' => $o->sales_person_id, 'value' => (float) $o->value]));
    }

    public function confirmed(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->orders($from, $to)->whereIn('doc_status', self::CONFIRMED);
    }

    /** @return array<string, float> 'Y-m' => order value, $months bulan terakhir termasuk bulan berjalan (urut lama -> baru) */
    public function monthlyOrderValue(int $months = 12): array
    {
        $rows = $this->confirmed($this->month($months - 1), $this->now->endOfMonth())->groupBy(fn ($o) => substr($o->co_date, 0, 7));
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = $this->month($i)->format('Y-m');
            $out[$key] = (float) ($rows->get($key)?->sum('value') ?? 0);
        }

        return $out;
    }

    public function newCustomers(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->once('newcust:'.$from->toDateString().':'.$to->toDateString(), fn () => CoOrder::query()->where('stat_cancel', false)
            ->where('doc_status', '<>', 'CANCELLED')->whereNotNull('customer_id')->groupBy('customer_id')->selectRaw('customer_id, MIN(co_date) AS first_date')->get()
            ->filter(fn ($r) => substr((string) $r->first_date, 0, 10) >= $from->toDateString() && substr((string) $r->first_date, 0, 10) <= $to->toDateString())->count());
    }

    /** @return array{shipped: int, on_time: int} Delivery Order customer yang berangkat di rentang itu */
    public function onTime(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('ontime:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            // do_line -> co_order_delivery di database yang sama (tm2sdi): satu query, tanpa lintas database.
            $rows = DB::connection('tm2sdi')->table('do_delivery as d')->join('do_line as l', 'l.delivery_id', '=', 'd.id_delivery')
                ->leftJoin('co_order_delivery as p', 'p.id_delivery', '=', 'l.co_delivery_id')
                ->where('d.do_type', 'CUSTOMER')->whereBetween('d.actual_ship_date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('d.id_delivery', 'd.actual_ship_date')
                ->selectRaw('d.id_delivery, d.actual_ship_date, MIN(COALESCE(p.original_date, p.ship_date)) AS promise')->get();

            return ['shipped' => $rows->count(), 'on_time' => $rows->filter(fn ($r) => $r->promise === null || substr((string) $r->actual_ship_date, 0, 10) <= substr((string) $r->promise, 0, 10))->count()];
        });
    }

    /** @return Collection<int, object> baris v1_fin_ar_aging yang masih punya sisa */
    public function receivables(): Collection
    {
        return $this->once('ar', fn () => Schema::connection('tm2acc')->hasView('v1_fin_ar_aging')
            ? DB::connection('tm2acc')->table('v1_fin_ar_aging')->where('outstanding', '>', 0)->get(['invoice_number', 'partner_id', 'due_date', 'outstanding', 'days_overdue'])
                ->map(fn ($r) => (object) ['invoice_number' => $r->invoice_number, 'partner_id' => $r->partner_id, 'due_date' => $r->due_date,
                    'outstanding' => (float) $r->outstanding, 'days_overdue' => (int) $r->days_overdue])
            : collect());
    }

    public function overdue(): float
    {
        return (float) $this->receivables()->where('days_overdue', '>', 0)->sum('outstanding');
    }

    // Target bulan itu; null = belum diisi di Setup > KPI Targets.
    public function target(string $key, CarbonImmutable $month, int $salesPersonId = 0): ?float
    {
        $all = $this->once('targets', fn () => SysKpiTarget::query()->where('activate', true)->get(['target_key', 'period_month', 'sales_person_id', 'target_value'])
            ->keyBy(fn (SysKpiTarget $t) => $t->target_key.'|'.$t->period_month->format('Y-m').'|'.(int) $t->sales_person_id));

        return $all->get($key.'|'.$month->format('Y-m').'|'.$salesPersonId)?->target_value;
    }

    /** @return array<int, string> id => nama */
    public function salesPeople(): array
    {
        return $this->once('people', fn () => SalesPerson::query()->orderBy('sales_name')->pluck('sales_name', 'id_sales_person')->all());
    }

    /** @return array<int, string> id => nama customer */
    public function customerNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids ? CustCustomer::query()->whereIn('id_customer', $ids)->pluck('customer_name', 'id_customer')->all() : [];
    }

    /** @return array{inquiries: int, priced: int, quoted: int, ordered: int} jumlah dokumen menurut tanggal dokumennya */
    public function pipeline(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('pipeline:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            $sdi = DB::connection('tm2sdi');
            $range = [$from->toDateString(), $to->toDateString()];

            return [
                'inquiries' => $sdi->table('iqp_inquiry')->whereBetween('inquiry_date', $range)->where('stat_cancel', 0)->count(),
                'priced' => $sdi->table('iqp_costing')->whereBetween('doc_date', $range)->where('published_flag', 1)->distinct()->count('inquiry_id'),
                'quoted' => $sdi->table('quo_quotation')->whereBetween('issued_date', $range)->where('stat_cancel', 0)->where('doc_status', 'PUBLISHED')->count(),
                'ordered' => $this->orders($from, $to)->count(),
            ];
        });
    }

    // Hari kerja tersisa bulan ini sesudah hari ini: bukan hari libur mingguan (jam kantor) dan bukan hari libur kalender.
    public function workDaysLeft(): int
    {
        return $this->once('workdays', function () {
            $off = WorkCalendar::offDays();
            $holidays = SysHoliday::query()->whereBetween('holiday_date', [$this->now->toDateString(), $this->now->endOfMonth()->toDateString()])
                ->pluck('holiday_date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
            $n = 0;
            for ($d = $this->now->addDay(); $d->month === $this->now->month; $d = $d->addDay()) {
                $n += in_array($d->dayOfWeek, $off, true) || in_array($d->toDateString(), $holidays, true) ? 0 : 1;
            }

            return $n;
        });
    }
}
