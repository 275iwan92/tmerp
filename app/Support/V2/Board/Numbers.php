<?php

namespace App\Support\V2\Board;

use App\Models\Master\CustCustomer;
use App\Models\Master\SalesPerson;
use App\Models\Master\SysHoliday;
use App\Models\Sdi\CoOrder;
use App\Models\Sys\SysKpiTarget;
use App\Support\DataOrigin\Visible;
use App\Support\V2\Data\WorkCalendar;
use Carbon\CarbonImmutable;
use Devlib\Locale;
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
// Periode papan (user 2026-10-02: "boleh dari daily sampai yearly") = periode KALENDER yang sedang berjalan: hari ini, minggu
// ini (Senin - Minggu), bulan ini, kuartal ini, semester ini, tahun ini; pembandingnya periode yang sama sebelumnya.
// Target diisi per bulan: periode bulanan ke atas menjumlah target bulan-bulannya (persen dan batas piutang = target bulan
// terakhir periode itu); harian dan mingguan tanpa target.
// Relasi ke tm2mst (nama customer / sales person) = query terpisah, dicocokkan di PHP (aturan lintas database).
// Data DUMMY / LEGACY ikut setelan admin (Setup > Dummy & Legacy Data): model Eloquent lewat DataOriginScope, query builder
// lewat Visible::apply -- angka papan tidak boleh memuat data contoh yang disembunyikan dari layar lain.
final class Numbers
{
    public const CONFIRMED = ['CONFIRMED', 'CLOSED'];

    public const WAITING = ['SUBMITTED'];

    public const PERIODS = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'semester' => 'Half-yearly', 'yearly' => 'Yearly'];

    public const DEFAULT_PERIOD = 'monthly';

    // Jumlah titik grafik tren per periode.
    private const TREND = ['daily' => 14, 'weekly' => 12, 'monthly' => 12, 'quarterly' => 8, 'semester' => 6, 'yearly' => 5];

    // Target yang boleh dijumlah lintas bulan; selain ini (persen, batas) dipakai target bulan terakhir periode.
    private const ADDITIVE = ['REVENUE', 'NEW_ORDERS', 'NEW_CUSTOMERS'];

    /** @var array<string, mixed> */
    private array $memo = [];

    public readonly string $period;

    public function __construct(public readonly CarbonImmutable $now, string $period = self::DEFAULT_PERIOD)
    {
        $this->period = isset(self::PERIODS[$period]) ? $period : self::DEFAULT_PERIOD;
    }

    public static function make(string $period = self::DEFAULT_PERIOD): self
    {
        return new self(CarbonImmutable::now(config('app.timezone')), $period);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} hari pertama dan terakhir periode ke-$back sebelum periode berjalan */
    public function range(int $back = 0): array
    {
        $day = $this->now->startOfDay();
        switch ($this->period) {
            case 'daily':
                $from = $day->subDays($back);

                return [$from, $from];
            case 'weekly':
                $from = $day->startOfWeek()->subWeeks($back);

                return [$from, $from->addDays(6)];
            case 'quarterly':
                $from = $day->startOfMonth()->setMonth(intdiv($day->month - 1, 3) * 3 + 1)->subMonthsNoOverflow(3 * $back);

                return [$from, $from->addMonthsNoOverflow(2)->endOfMonth()->startOfDay()];
            case 'semester':
                $from = $day->startOfMonth()->setMonth($day->month <= 6 ? 1 : 7)->subMonthsNoOverflow(6 * $back);

                return [$from, $from->addMonthsNoOverflow(5)->endOfMonth()->startOfDay()];
            case 'yearly':
                $from = $day->startOfYear()->subYears($back);

                return [$from, $from->endOfYear()->startOfDay()];
            default:
                $from = $this->month($back);

                return [$from, $from->endOfMonth()->startOfDay()];
        }
    }

    public function from(): CarbonImmutable
    {
        return $this->range()[0];
    }

    public function to(): CarbonImmutable
    {
        return $this->range()[1];
    }

    // Label pendek satu periode (sumbu grafik): 2/10, W40, Okt, Q4 26, S2 26, 2026.
    public function label(CarbonImmutable $from): string
    {
        return match ($this->period) {
            'daily' => $from->format('j/n'),
            'weekly' => 'W'.$from->isoWeek(),
            'quarterly' => 'Q'.$from->quarter.' '.$from->format('y'),
            'semester' => ($from->month <= 6 ? 'S1 ' : 'S2 ').$from->format('y'),
            'yearly' => (string) $from->year,
            default => explode('-', (string) Locale::d($from))[1] ?? $from->format('M'),
        };
    }

    // Rentang periode berjalan untuk keterangan widget: "02-Okt-2026" atau "01-Okt-2026 – 31-Okt-2026".
    public function rangeText(): string
    {
        [$from, $to] = $this->range();

        return $from->equalTo($to) ? (string) Locale::d($from) : Locale::d($from).' – '.Locale::d($to);
    }

    // Target periode berjalan (atau periode ke-$back); null = belum diisi, atau periodenya harian / mingguan.
    public function periodTarget(string $key, int $salesPersonId = 0, int $back = 0): ?float
    {
        if (in_array($this->period, ['daily', 'weekly'], true)) {
            return null;
        }
        [$from, $to] = $this->range($back);
        if (! in_array($key, self::ADDITIVE, true)) {
            return $this->target($key, $to->greaterThan($this->now) ? $this->month() : $to->startOfMonth(), $salesPersonId);
        }
        $sum = null;
        for ($m = $from->startOfMonth(); $m->lessThanOrEqualTo($to); $m = $m->addMonthNoOverflow()) {
            $t = $this->target($key, $m, $salesPersonId);
            $sum = $t === null ? $sum : ($sum ?? 0) + $t;
        }

        return $sum;
    }

    /** @return array<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable, value: float, target: ?float}> order value per periode, lama -> baru */
    public function trend(?int $points = null): array
    {
        $points ??= self::TREND[$this->period];
        [$first] = $this->range($points - 1);
        $orders = $this->confirmed($first, $this->to());
        $out = [];
        for ($i = $points - 1; $i >= 0; $i--) {
            [$from, $to] = $this->range($i);
            $out[] = ['label' => $this->label($from), 'from' => $from, 'to' => $to, 'target' => $this->periodTarget('REVENUE', 0, $i),
                'value' => (float) $orders->filter(fn ($o) => $o->co_date >= $from->toDateString() && $o->co_date <= $to->toDateString())->sum('value')];
        }

        return $out;
    }

    /** @return array{confirmed: float, draft: float, waiting: float, n_confirmed: int, n_draft: int, n_waiting: int} nilai dan jumlah dokumen per tahap */
    public static function split(Collection $orders): array
    {
        $confirmed = $orders->whereIn('doc_status', self::CONFIRMED);
        $waiting = $orders->whereIn('doc_status', self::WAITING);
        $draft = $orders->whereNotIn('doc_status', [...self::CONFIRMED, ...self::WAITING]);

        return ['confirmed' => (float) $confirmed->sum('value'), 'draft' => (float) $draft->sum('value'), 'waiting' => (float) $waiting->sum('value'),
            'n_confirmed' => $confirmed->count(), 'n_draft' => $draft->count(), 'n_waiting' => $waiting->count()];
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
            $rows = Visible::apply(DB::connection('tm2sdi')->table('do_delivery as d'), 'tm2sdi', 'do_delivery', 'd')
                ->join('do_line as l', 'l.delivery_id', '=', 'd.id_delivery')
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
        return $this->once('ar', function () {
            if (! Schema::connection('tm2acc')->hasView('v1_fin_ar_aging')) {
                return collect();
            }
            $acc = DB::connection('tm2acc');
            $rows = $acc->table('v1_fin_ar_aging')->where('outstanding', '>', 0)->get(['invoice_id', 'invoice_number', 'partner_id', 'due_date', 'outstanding', 'days_overdue']);
            // View-nya tidak membawa asal data: invoice yang disembunyikan setelan admin disaring lewat tabel induknya.
            $shown = Visible::apply($acc->table('fin_invoice'), 'tm2acc', 'fin_invoice')->whereIn('invoice_id', $rows->pluck('invoice_id'))->pluck('invoice_id')->flip();

            return $rows->filter(fn ($r) => $shown->has($r->invoice_id))->values()
                ->map(fn ($r) => (object) ['invoice_number' => $r->invoice_number, 'partner_id' => $r->partner_id, 'due_date' => $r->due_date,
                    'outstanding' => (float) $r->outstanding, 'days_overdue' => (int) $r->days_overdue]);
        });
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
                'inquiries' => Visible::apply($sdi->table('iqp_inquiry'), 'tm2sdi', 'iqp_inquiry')->whereBetween('inquiry_date', $range)->where('stat_cancel', 0)->count(),
                'priced' => Visible::apply($sdi->table('iqp_costing'), 'tm2sdi', 'iqp_costing')->whereBetween('doc_date', $range)->where('published_flag', 1)->distinct()->count('inquiry_id'),
                'quoted' => Visible::apply($sdi->table('quo_quotation'), 'tm2sdi', 'quo_quotation')->whereBetween('issued_date', $range)->where('stat_cancel', 0)
                    ->where('doc_status', 'PUBLISHED')->count(),
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
