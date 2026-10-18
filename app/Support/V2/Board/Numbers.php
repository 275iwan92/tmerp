<?php

/*
 * REMARK
 * Tujuan       : Angka dan daftar untuk widget Opening Board (satu kali hitung per permintaan).
 * Sumber hulu  : Banyak tabel tm2*; dokumen SOP / WI = tm2mnt.mnt_document (+ mnt_document_line), register SOP / WI tunggal sejak 2026-10-18 (sop_document dilebur).
 * Input        : Tidak ada.
 * Output       : Koleksi dan angka per widget.
 * Dipakai oleh : Widget di App\Support\V2\Board\Widgets.
 * Status       : Aktif. documents() membaca MntDocument company in force (2026-10-18).
 */

namespace App\Support\V2\Board;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\VacVacancy;
use App\Models\Inventory\StockOnhand;
use App\Models\Master\AttDailyStatus;
use App\Models\Master\CustCustomer;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Master\OrgPosition;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Master\SalesPerson;
use App\Models\Mnt\MntDocument;
use App\Models\Master\SupSupplier;
use App\Models\Master\SysHoliday;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Prd\PrdWoOperationOutput;
use App\Models\Prd\QcComplaintReview;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrRequisition;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CrmComplaint;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
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

    /** @return array<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable, back: int}> periode-periode grafik tren, lama -> baru */
    public function buckets(?int $points = null): array
    {
        $points ??= self::TREND[$this->period];
        $out = [];
        for ($i = $points - 1; $i >= 0; $i--) {
            [$from, $to] = $this->range($i);
            $out[] = ['label' => $this->label($from), 'from' => $from, 'to' => $to, 'back' => $i];
        }

        return $out;
    }

    /** @return array<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable, value: float, target: ?float}> order value per periode, lama -> baru */
    public function trend(?int $points = null): array
    {
        $buckets = $this->buckets($points);
        $orders = $this->confirmed($buckets[0]['from'], $this->to());

        return array_map(fn (array $b) => ['label' => $b['label'], 'from' => $b['from'], 'to' => $b['to'], 'target' => $this->periodTarget('REVENUE', 0, $b['back']),
            'value' => (float) $orders->filter(fn ($o) => $o->co_date >= $b['from']->toDateString() && $o->co_date <= $b['to']->toDateString())->sum('value')], $buckets);
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

    // ---- Sales: pengiriman, antrian harga, menang / kalah, komplain ----------------------------------------------------

    /** @return Collection<int, object{co_no: ?string, customer_id: ?int, ship_date: string, qty: float, days: int}> baris CPDEL yang belum berangkat, days = hari sampai janji (minus = lewat) */
    public function deliveriesOpen(): Collection
    {
        return $this->once('deliveries', function () {
            $today = $this->now->startOfDay();

            return CoOrderDelivery::query()->where('status', 'PLANNED')->whereNotNull('ship_date')->with('order:id_order,co_no,customer_id,doc_status,stat_cancel')
                ->orderBy('ship_date')->limit(500)->get()
                ->filter(fn (CoOrderDelivery $d) => $d->order && ! $d->order->stat_cancel && in_array($d->order->doc_status, self::CONFIRMED, true))
                ->map(fn (CoOrderDelivery $d) => (object) ['co_no' => $d->order->co_no, 'customer_id' => $d->order->customer_id, 'ship_date' => substr((string) $d->ship_date, 0, 10),
                    'qty' => (float) $d->ship_qty, 'days' => (int) $today->diffInDays(CarbonImmutable::parse(substr((string) $d->ship_date, 0, 10)), false)])->values();
        });
    }

    /** @return array{prodev: Collection, procost: Collection} inquiry yang sudah di-Confirm Sales dan menunggu Pro-Dev / Pro-Cost */
    public function waitingPrice(): array
    {
        return $this->once('waiting_price', function () {
            $open = IqpInquiry::query()->where('stat_cancel', false)->where('stat_confirm_sales', true)->where('stat_confirm_procost', false)
                ->whereNotIn('sub_state', ['CONVERTED', 'LOST', 'CANCELLED'])
                ->get(['id_inquiry', 'inquiry_no', 'inquiry_type', 'customer_id', 'stat_confirm_prodev', 'confirm_sales_at', 'confirm_prodev_at']);
            $age = fn ($at) => $at ? (int) CarbonImmutable::parse($at)->startOfDay()->diffInDays($this->now->startOfDay()) : 0;
            $row = fn (IqpInquiry $i, $since) => (object) ['inquiry_no' => $i->inquiry_no, 'customer_id' => $i->customer_id, 'days' => $age($since)];

            return ['prodev' => $open->where('stat_confirm_prodev', false)->map(fn ($i) => $row($i, $i->confirm_sales_at))->sortByDesc('days')->values(),
                'procost' => $open->where('stat_confirm_prodev', true)->map(fn ($i) => $row($i, $i->confirm_prodev_at ?? $i->confirm_sales_at))->sortByDesc('days')->values()];
        });
    }

    /** @return array{won: int, lost: int} inquiry bertanggal di rentang itu yang sudah selesai (definisi laporan Win-Rate Sales) */
    public function winLoss(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('win:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            $by = IqpInquiry::query()->whereBetween('inquiry_date', [$from->toDateString(), $to->toDateString()])->whereIn('sub_state', ['CONVERTED', 'LOST'])
                ->selectRaw('sub_state, COUNT(*) AS n')->groupBy('sub_state')->pluck('n', 'sub_state');

            return ['won' => (int) ($by['CONVERTED'] ?? 0), 'lost' => (int) ($by['LOST'] ?? 0)];
        });
    }

    /** @return Collection<int, CrmComplaint> komplain / retur yang belum ditutup dan tidak batal */
    public function openComplaints(): Collection
    {
        return $this->once('complaints', fn () => Schema::connection('tm2sdi')->hasTable('crm_complaint')
            ? CrmComplaint::query()->where('stat_close', false)->where('stat_cancel', false)->orderBy('complaint_date')->get() : collect());
    }

    /** @return Collection<int, object{quotation_no: string, customer_id: ?int, issued: string, valid_until: ?string, value: float}> Quotation terbit yang belum menjadi Customer Order */
    public function openQuotations(): Collection
    {
        return $this->once('open_quo', fn () => QuoQuotation::query()->where('doc_status', 'PUBLISHED')->where('stat_cancel', false)
            ->whereNotIn('id_quotation', CoOrder::query()->whereNotNull('quotation_id')->select('quotation_id'))
            ->withSum('items as value', 'subtotal')->orderBy('issued_date')->get(['id_quotation', 'quotation_no', 'customer_id', 'issued_date', 'valid_until'])
            ->map(fn (QuoQuotation $q) => (object) ['quotation_no' => $q->quotation_no, 'customer_id' => $q->customer_id, 'issued' => substr((string) $q->issued_date, 0, 10),
                'valid_until' => $q->valid_until ? substr((string) $q->valid_until, 0, 10) : null, 'value' => (float) $q->value]));
    }

    /** @return Collection<int, PrdWo> Work Order yang lewat due date dan belum selesai */
    public function lateWorkOrders(): Collection
    {
        return $this->once('wo_late', fn () => PrdWo::query()->whereNotIn('wo_status', ['COMPLETED', 'CANCELLED'])->whereNotNull('due_date')
            ->where('due_date', '<', $this->now->startOfDay()->toDateTimeString())->orderBy('due_date')->get(['wo_id', 'wo_no', 'wo_status', 'due_date', 'qty']));
    }

    /** @return array<string, int> jenis kelamin (L / P / kosong) => pegawai aktif */
    public function headcountByGender(): array
    {
        return $this->once('gender', fn () => EmpEmployee::query()->where('activate', true)->selectRaw('gender, COUNT(*) AS n')->groupBy('gender')->pluck('n', 'gender')
            ->mapWithKeys(fn ($n, $g) => [(string) $g => (int) $n])->all());
    }

    // ---- Laba rugi dari buku besar (voucher POSTED): pendapatan = REVENUE + OTHER_INCOME, biaya = COGS + EXPENSE + OTHER_EXPENSE ----

    private const INCOME_TYPES = ['REVENUE', 'OTHER_INCOME'];

    private const EXPENSE_TYPES = ['COGS', 'EXPENSE', 'OTHER_EXPENSE'];

    /** @return Collection<int, object{date: string, account: string, type: string, amount: float}> gerak akun laba rugi sejak tanggal itu; amount positif = pendapatan atau biaya */
    private function ledgerLines(CarbonImmutable $since): Collection
    {
        return $this->once('ledger:'.$since->toDateString(), function () use ($since) {
            $q = DB::connection('tm2acc')->table('fin_voucher_line as l')->join('fin_voucher as v', 'v.voucher_id', '=', 'l.voucher_id')
                ->join('fin_account as a', 'a.account_id', '=', 'l.account_id')->whereIn('a.account_type', [...self::INCOME_TYPES, ...self::EXPENSE_TYPES])
                ->where('v.status', 'POSTED')->where('v.stat_cancel', 0)->where('v.entry_date', '>=', $since->toDateString())
                ->groupBy('v.entry_date', 'a.account_name', 'a.account_type')
                ->selectRaw('v.entry_date, a.account_name, a.account_type, SUM(l.debit) AS d, SUM(l.credit) AS c');

            return Visible::apply($q, 'tm2acc', 'fin_voucher', 'v')->get()->map(fn ($r) => (object) ['date' => substr((string) $r->entry_date, 0, 10), 'account' => (string) $r->account_name,
                'type' => (string) $r->account_type, 'amount' => in_array($r->account_type, self::INCOME_TYPES, true) ? (float) $r->c - (float) $r->d : (float) $r->d - (float) $r->c]);
        });
    }

    /** @return array{income: float, expense: float, expenses: array<string, float>} laba rugi satu rentang; expenses = nama akun => biaya, terbesar dulu */
    public function profitLoss(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Satu kali baca untuk semua rentang yang mungkin diminta papan: mulai dari periode grafik paling lama.
        $since = min($this->buckets()[0]['from'], $this->range(1)[0], $from);
        $rows = $this->ledgerLines($since)->filter(fn ($l) => $l->date >= $from->toDateString() && $l->date <= $to->toDateString());
        $expenses = $rows->filter(fn ($l) => in_array($l->type, self::EXPENSE_TYPES, true));

        return ['income' => (float) $rows->filter(fn ($l) => in_array($l->type, self::INCOME_TYPES, true))->sum('amount'), 'expense' => (float) $expenses->sum('amount'),
            'expenses' => $expenses->groupBy('account')->map(fn ($set) => (float) $set->sum('amount'))->filter(fn ($v) => $v > 0)->sortDesc()->all()];
    }

    // ---- Purchasing --------------------------------------------------------------------------------------------------

    public const PO_APPROVED = ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED'];

    public const PO_OPEN = ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'];

    /** @return Collection<int, object{po_id: int, date: string, status: string, supplier_id: ?int, value: float}> PO tidak batal di rentang tanggal */
    public function purchaseOrders(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->once('po:'.$from->toDateString().':'.$to->toDateString(), fn () => PoOrder::query()
            ->whereBetween('i_doc_date', [$from->toDateString(), $to->toDateString()])->where('stat_cancel', false)
            ->get(['po_id', 'i_doc_date', 'status', 'i_supplier_id', 'c_grand_total'])
            ->map(fn (PoOrder $o) => (object) ['po_id' => (int) $o->po_id, 'date' => substr((string) $o->i_doc_date, 0, 10), 'status' => (string) $o->status,
                'supplier_id' => $o->i_supplier_id, 'value' => (float) $o->c_grand_total]));
    }

    /** @return Collection<int, PoOrder> PO yang sudah disetujui / dikirim ke supplier dan belum diterima penuh, per hari ini */
    public function purchaseOrdersOpen(): Collection
    {
        return $this->once('po_open', fn () => PoOrder::query()->where('stat_cancel', false)->whereIn('status', self::PO_OPEN)
            ->orderBy('i_delivery_date')->get(['po_id', 'doc_no', 'i_doc_date', 'i_delivery_date', 'status', 'i_supplier_id', 'c_grand_total']));
    }

    /** @return array<string, int> jumlah Purchase Requisition per status, tidak batal */
    public function requisitions(): array
    {
        return $this->once('pr', fn () => PrRequisition::query()->where('stat_cancel', false)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')
            ->map(fn ($n) => (int) $n)->all());
    }

    /** @return array<int, string> id => nama supplier */
    public function supplierNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids ? SupSupplier::query()->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all() : [];
    }

    // ---- Hutang, pembayaran, kas -------------------------------------------------------------------------------------

    /** @return Collection<int, object> baris v1_fin_ap_aging yang masih punya sisa */
    public function payables(): Collection
    {
        return $this->once('ap', function () {
            if (! Schema::connection('tm2acc')->hasView('v1_fin_ap_aging')) {
                return collect();
            }
            $acc = DB::connection('tm2acc');
            $rows = $acc->table('v1_fin_ap_aging')->where('outstanding', '>', 0)->get(['invoice_id', 'invoice_number', 'partner_id', 'due_date', 'outstanding', 'days_overdue']);
            $shown = Visible::apply($acc->table('fin_invoice'), 'tm2acc', 'fin_invoice')->whereIn('invoice_id', $rows->pluck('invoice_id'))->pluck('invoice_id')->flip();

            return $rows->filter(fn ($r) => $shown->has($r->invoice_id))->values()
                ->map(fn ($r) => (object) ['invoice_number' => $r->invoice_number, 'partner_id' => $r->partner_id, 'due_date' => $r->due_date,
                    'outstanding' => (float) $r->outstanding, 'days_overdue' => (int) $r->days_overdue]);
        });
    }

    // Uang yang dialokasikan ke invoice di rentang itu: CUSTOMER = diterima dari customer, SUPPLIER = dibayar ke supplier.
    public function paid(string $partnerType, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return $this->once('paid:'.$partnerType.':'.$from->toDateString().':'.$to->toDateString(), function () use ($partnerType, $from, $to) {
            $q = DB::connection('tm2acc')->table('fin_payment_alloc as p')->join('fin_invoice as i', 'i.invoice_id', '=', 'p.invoice_id')
                ->where('i.partner_type', $partnerType)->whereBetween('p.alloc_date', [$from->toDateString(), $to->toDateString()]);

            return (float) Visible::apply($q, 'tm2acc', 'fin_invoice', 'i')->sum('p.amount');
        });
    }

    /** @return Collection<int, object{voucher_id: int, date: string, account_id: int, net: float}> gerak akun kas / bank (fin_account.cf_line = CASH) dari voucher POSTED */
    private function cashLines(): Collection
    {
        return $this->once('cash_lines', function () {
            $q = DB::connection('tm2acc')->table('fin_voucher_line as l')->join('fin_voucher as v', 'v.voucher_id', '=', 'l.voucher_id')
                ->join('fin_account as a', 'a.account_id', '=', 'l.account_id')->where('a.cf_line', 'CASH')->where('v.status', 'POSTED')->where('v.stat_cancel', 0)
                ->groupBy('l.voucher_id', 'v.entry_date', 'l.account_id')->selectRaw('l.voucher_id, v.entry_date, l.account_id, SUM(l.debit - l.credit) AS net');

            return Visible::apply($q, 'tm2acc', 'fin_voucher', 'v')->get()
                ->map(fn ($r) => (object) ['voucher_id' => (int) $r->voucher_id, 'date' => substr((string) $r->entry_date, 0, 10), 'account_id' => (int) $r->account_id, 'net' => (float) $r->net]);
        });
    }

    /** @return array<int, array{code: string, name: string, balance: float}> saldo tiap akun kas / bank per hari ini */
    public function cashBalances(): array
    {
        return $this->once('cash_balance', function () {
            $by = $this->cashLines()->filter(fn ($l) => $l->date <= $this->now->toDateString())->groupBy('account_id')->map(fn ($set) => (float) $set->sum('net'));
            $names = DB::connection('tm2acc')->table('fin_account')->whereIn('account_id', $by->keys())->orderBy('account_code')->get(['account_id', 'account_code', 'account_name']);

            return $names->map(fn ($a) => ['code' => $a->account_code, 'name' => $a->account_name, 'balance' => (float) $by[$a->account_id]])->all();
        });
    }

    /** @return array{in: float, out: float} kas masuk dan keluar di rentang itu; pindah buku antar akun kas saling menghapus (dihitung bersih per voucher) */
    public function cashFlow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $net = $this->cashLines()->filter(fn ($l) => $l->date >= $from->toDateString() && $l->date <= $to->toDateString())
            ->groupBy('voucher_id')->map(fn ($set) => (float) $set->sum('net'));

        return ['in' => (float) $net->filter(fn ($v) => $v > 0)->sum(), 'out' => (float) abs($net->filter(fn ($v) => $v < 0)->sum())];
    }

    // ---- Payroll (nilai terenkripsi: dijumlah lewat model, bukan SQL) -------------------------------------------------

    /** @return array<int, array{id: int, label: string, start: string, end: string, status: string, slips: int, net: float, basic: float, allowance: float, bonus: float, overtime: float, deduction: float, anomaly: int}> $n run terakhir, lama -> baru */
    public function payrollRuns(int $n = 8): array
    {
        return $this->once('payroll:'.$n, function () use ($n) {
            $runs = PyrPayrollRun::query()->orderByDesc('period_end')->limit($n)->get()->reverse()->values();
            $slips = PyrPayslip::query()->whereIn('id_run', $runs->pluck('id_run'))->get()->groupBy('id_run');

            return $runs->map(function (PyrPayrollRun $r) use ($slips) {
                $set = $slips->get($r->id_run) ?? collect();
                $sum = fn (string ...$cols) => (float) $set->sum(fn (PyrPayslip $p) => array_sum(array_map(fn ($c) => (float) $p->{$c}, $cols)));

                return ['id' => (int) $r->id_run, 'label' => (string) Locale::d($r->period_end), 'start' => substr((string) $r->period_start, 0, 10), 'end' => substr((string) $r->period_end, 0, 10),
                    'status' => (string) $r->run_status, 'slips' => $set->count(), 'net' => $sum('total_gaji_bersih'), 'basic' => $sum('total_pokok'),
                    'allowance' => $sum('total_tj_keahlian', 'total_tj_loyalitas', 'total_tj_prestasi'), 'bonus' => $sum('total_bonus_pagi', 'total_bonus_hadir', 'total_uang_roti'),
                    'overtime' => $sum('total_overtime_pay'), 'deduction' => $sum('total_denda', 'total_bpjs_employee', 'total_pph21'), 'anomaly' => $set->where('has_anomaly', true)->count()];
            })->all();
        });
    }

    // ---- Human Resource -----------------------------------------------------------------------------------------------

    public const ATT_LATE = ['T-5', 'T+5', 'T+15', 'PAT-5', 'PAT+5'];

    public const ATT_LEAVE = ['C', 'CL', 'I', 'IS'];

    /** @return array{active: int, joined: int, resigned: int} pegawai aktif per hari ini; masuk dan keluar di rentang itu */
    public function headcount(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('headcount:'.$from->toDateString().':'.$to->toDateString(), fn () => [
            'active' => EmpEmployee::query()->where('activate', true)->count(),
            'joined' => EmpEmployee::query()->whereBetween('join_date', [$from->toDateString(), $to->toDateString()])->count(),
            'resigned' => EmpEmployee::query()->whereBetween('resign_date', [$from->toDateString(), $to->toDateString()])->count(),
        ]);
    }

    /** @return array<string, int> nama departemen => pegawai aktif, terbanyak dulu */
    public function headcountByDepartment(): array
    {
        return $this->once('headcount_dept', function () {
            // emp_employee -> org_position -> org_department: satu database (tm2mst).
            $q = DB::connection('tm2mst')->table('emp_employee as e')->leftJoin('org_position as p', 'p.position_id', '=', 'e.position_id')
                ->leftJoin('org_department as d', 'd.department_id', '=', 'p.department_id')->where('e.activate', 1)
                ->groupBy('d.department_name')->selectRaw('d.department_name AS name, COUNT(*) AS n')->orderByDesc('n');

            return Visible::apply($q, 'tm2mst', 'emp_employee', 'e')->get()->mapWithKeys(fn ($r) => [(string) ($r->name ?? '—') => (int) $r->n])->all();
        });
    }

    /** @return array{present: int, late: int, leave: int, sick: int, absent: int} hari-orang per status kehadiran di rentang itu */
    public function attendance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('att:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            $by = AttDailyStatus::query()->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('status_code, COUNT(*) AS n')->groupBy('status_code')->pluck('n', 'status_code');
            $sum = fn (array $codes) => (int) $by->only($codes)->sum();

            return ['present' => $sum(['H']), 'late' => $sum(self::ATT_LATE), 'leave' => $sum(self::ATT_LEAVE), 'sick' => $sum(['S']), 'absent' => $sum(['A'])];
        });
    }

    /** @return Collection<int, EmpLeaveRequest> permohonan cuti / izin / sakit yang menunggu keputusan */
    public function leaveWaiting(): Collection
    {
        return $this->once('leave', fn () => EmpLeaveRequest::query()->where('status', 'PENDING')->orderBy('date_from')->get());
    }

    /** @return array<int, string> id => nama pegawai */
    public function employeeNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids ? EmpEmployee::query()->withoutGlobalScopes()->whereIn('employee_id', $ids)->pluck('full_name', 'employee_id')->all() : [];
    }

    /** @return array{vacancies: int, applications: array<string, int>} lowongan yang sedang dibuka dan lamaran per status */
    public function recruitment(): array
    {
        return $this->once('recruitment', fn () => [
            'vacancies' => VacVacancy::query()->where('is_published', true)->whereNull('closed_at')->count(),
            'applications' => AppApplication::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
        ]);
    }

    // ---- Produksi dan stok --------------------------------------------------------------------------------------------

    /** @return array<string, int> jumlah Work Order per status */
    public function workOrders(): array
    {
        return $this->once('wo', fn () => PrdWo::query()->selectRaw('wo_status, COUNT(*) AS n')->groupBy('wo_status')->pluck('n', 'wo_status')->map(fn ($n) => (int) $n)->all());
    }

    /** @return array<string, float> kelas stok (RM / SFG / FG) => nilai stok on hand yang bisa dipakai */
    public function stockValue(): array
    {
        return $this->once('stock', function () {
            $out = [];
            foreach (array_keys(StockOnhand::ITEM_KEYS) as $class) {
                $out[$class] = (float) StockOnhand::of($class)->where('is_usable', true)->sum('value_onhand');
            }

            return $out;
        });
    }

    // ---- Produksi: target vs hasil, kapasitas mesin, downtime -----------------------------------------------------------

    /** @return Collection<int, object{operation_id: int, machine_id: ?int, qty: float, start: ?string, finish: ?string, status: string, open: bool}> operasi Work Order yang tidak batal, sejak tanggal itu (atau belum berjadwal) */
    private function operations(CarbonImmutable $since): Collection
    {
        return $this->once('ops:'.$since->toDateString(), function () use ($since) {
            $done = PrdWo::query()->whereIn('wo_status', ['COMPLETED', 'CANCELLED'])->pluck('wo_id')->flip();

            return PrdWoOperation::query()->whereIn('wo_id', PrdWo::query()->where('wo_status', '<>', 'CANCELLED')->select('wo_id'))
                ->where(fn ($q) => $q->whereNull('target_finish')->orWhere('target_finish', '>=', $since->toDateTimeString()))
                ->get(['operation_id', 'wo_id', 'machine_id', 'qty', 'target_start', 'target_finish', 'task_status'])
                ->map(fn (PrdWoOperation $o) => (object) ['operation_id' => (int) $o->operation_id, 'machine_id' => $o->machine_id, 'qty' => (float) $o->qty,
                    'start' => $o->target_start ? (string) $o->target_start : null, 'finish' => $o->target_finish ? (string) $o->target_finish : null,
                    'status' => (string) $o->task_status, 'open' => ! $done->has($o->wo_id)]);
        });
    }

    // Tanggal paling awal yang mungkin diminta papan untuk periode ini (periode grafik paling lama atau periode lalu).
    private function earliest(): CarbonImmutable
    {
        return min($this->buckets()[0]['from'], $this->range(1)[0]);
    }

    /** @return array{target: float, result: float, operations: int, outputs: int} target = qty operasi yang dijadwalkan selesai di rentang itu; result = qty baik yang dicatat di rentang itu */
    public function production(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $since = min($this->earliest(), $from);
        $ops = $this->operations($since)->filter(fn ($o) => $o->finish && substr($o->finish, 0, 10) >= $from->toDateString() && substr($o->finish, 0, 10) <= $to->toDateString());
        $outputs = $this->once('outputs:'.$since->toDateString(), fn () => PrdWoOperationOutput::query()->where('recorded_at', '>=', $since->toDateTimeString())
            ->get(['output_id', 'qty_good', 'recorded_at'])->map(fn ($o) => (object) ['date' => substr((string) $o->recorded_at, 0, 10), 'qty' => (float) $o->qty_good]))
            ->filter(fn ($o) => $o->date >= $from->toDateString() && $o->date <= $to->toDateString());

        return ['target' => (float) $ops->sum('qty'), 'result' => (float) $outputs->sum('qty'), 'operations' => $ops->count(), 'outputs' => $outputs->count()];
    }

    // Hari kerja di rentang itu: bukan hari libur mingguan (jam kantor) dan bukan hari libur kalender.
    public function workDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->once('workdays:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            $off = WorkCalendar::offDays();
            $holidays = SysHoliday::query()->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])->pluck('holiday_date')
                ->map(fn ($d) => substr((string) $d, 0, 10))->flip();
            $n = 0;
            for ($d = $from->startOfDay(); $d->lessThanOrEqualTo($to); $d = $d->addDay()) {
                $n += in_array($d->dayOfWeek, $off, true) || $holidays->has($d->toDateString()) ? 0 : 1;
            }

            return $n;
        });
    }

    /**
     * Kapasitas vs beban mesin proses di rentang itu. available = jam per shift x shift per hari x hari kerja (master mesin);
     * null bila jam / shift mesinnya belum diisi. planned = jam operasi Work Order terjadwal di mesin itu yang jatuh di rentang itu.
     *
     * @return array<int, array{code: string, name: string, available: ?float, planned: float}> terurut dari beban tertinggi
     */
    public function capacity(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('capacity:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            $days = $this->workDays($from, $to);
            $start = $from->startOfDay();
            $end = $to->startOfDay()->addDay();
            $planned = [];
            foreach ($this->operations(min($this->earliest(), $from)) as $o) {
                if (! $o->machine_id || ! $o->start || ! $o->finish) {
                    continue;
                }
                $a = max(CarbonImmutable::parse($o->start), $start);
                $b = min(CarbonImmutable::parse($o->finish), $end);
                if ($b->greaterThan($a)) {
                    $planned[$o->machine_id] = ($planned[$o->machine_id] ?? 0) + $a->diffInMinutes($b) / 60;
                }
            }

            return ProdMachine::query()->where('activate', true)->where('is_process_machine', true)->orderBy('machine_code')
                ->get(['machine_id', 'machine_code', 'machine_name', 'hours_per_shift', 'shifts_per_day'])
                ->map(fn (ProdMachine $m) => ['code' => (string) $m->machine_code, 'name' => (string) $m->machine_name,
                    'available' => $m->hours_per_shift && $m->shifts_per_day ? (float) $m->hours_per_shift * (int) $m->shifts_per_day * $days : null,
                    'planned' => round((float) ($planned[$m->machine_id] ?? 0), 2)])
                ->sortByDesc(fn (array $m) => [$m['available'] ? $m['planned'] / $m['available'] : ($m['planned'] > 0 ? INF : -1), $m['planned']])->values()->all();
        });
    }

    /** @return array{planned: float, unplanned: float, events: int, open: int} jam mesin berhenti yang mulai di rentang itu; yang belum selesai dihitung sampai sekarang (open = jumlahnya) */
    public function downtime(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('downtime:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            $rows = ProdMachineDowntime::query()->whereBetween('start_at', [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()])
                ->get(['is_planned', 'start_at', 'end_at', 'duration_minutes']);
            $until = min($this->now, $to->endOfDay());
            $minutes = fn (ProdMachineDowntime $r) => $r->duration_minutes !== null ? (float) $r->duration_minutes
                : max(0, CarbonImmutable::parse($r->start_at)->diffInMinutes($r->end_at ? CarbonImmutable::parse($r->end_at) : $until, false));

            return ['planned' => (float) $rows->where('is_planned', 1)->sum($minutes) / 60, 'unplanned' => (float) $rows->where('is_planned', 0)->sum($minutes) / 60,
                'events' => $rows->count(), 'open' => $rows->whereNull('end_at')->count()];
        });
    }

    /** @return array<string, int> jumlah operasi per status, hanya Work Order yang belum selesai */
    public function operationStatus(): array
    {
        return $this->operations(CarbonImmutable::parse('1990-01-01'))->where('open', true)->countBy('status')->all();
    }

    // ---- Mutu: QC penerimaan, QC produksi, komplain ------------------------------------------------------------------

    /** @return array{pass: int, fail: int, waiting: int} baris penerimaan barang: diperiksa di rentang itu (lulus / tidak) dan yang masih menunggu QC */
    public function incomingQc(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('iqc:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            $done = PvLine::query()->whereBetween('qc_at', [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()])
                ->whereNotNull('qc_quality_status')->whereNotIn('qc_quality_status', ['', 'PENDING'])->selectRaw('qc_quality_status AS st, COUNT(*) AS n')->groupBy('qc_quality_status')->pluck('n', 'st');
            // Belum dinilai = bawaan kolom 'PENDING' (bukan NULL); penerimaan yang belum diposting berstatus DRAFT atau QC_PENDING.
            $waiting = PvLine::query()->whereIn('pv_id', PvReceipt::query()->whereIn('status', ['DRAFT', 'QC_PENDING'])->select('pv_id'))
                ->where(fn ($q) => $q->whereNull('qc_quality_status')->orWhereIn('qc_quality_status', ['', 'PENDING']))->count();

            return ['pass' => (int) ($done['PASS'] ?? 0), 'fail' => (int) $done->except('PASS')->sum(), 'waiting' => $waiting];
        });
    }

    /** @return Collection<int, CrmComplaint> komplain yang perlu jawaban QA dan belum punya review yang di-Confirm */
    public function qaWaiting(): Collection
    {
        return $this->once('qa_waiting', function () {
            if (! Schema::connection('tm2sdi')->hasTable('crm_complaint')) {
                return collect();
            }
            // Review ada di tm2prd, komplain di tm2sdi: dua query, dicocokkan di PHP.
            $reviewed = Schema::connection('tm2prd')->hasTable('qc_complaint_review')
                ? QcComplaintReview::query()->where('stat_confirm', true)->pluck('complaint_id')->flip() : collect();

            return CrmComplaint::query()->where('needs_qa', true)->where('stat_cancel', false)->where('stat_close', false)->orderBy('complaint_date')->get()
                ->reject(fn (CrmComplaint $c) => $reviewed->has($c->id_complaint))->values();
        });
    }

    /** @return array<string, int> kategori => jumlah komplain bertanggal di rentang itu (tidak batal) */
    public function complaintsByCategory(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('complaint_cat:'.$from->toDateString().':'.$to->toDateString(), fn () => Schema::connection('tm2sdi')->hasTable('crm_complaint')
            ? CrmComplaint::query()->whereBetween('complaint_date', [$from->toDateString(), $to->toDateString()])->where('stat_cancel', false)
                ->selectRaw('category, COUNT(*) AS n')->groupBy('category')->pluck('n', 'category')->map(fn ($n) => (int) $n)->all() : []);
    }

    // ---- Gudang -------------------------------------------------------------------------------------------------------

    /** @return array<string, float> nama gudang => nilai stok on hand yang bisa dipakai (semua kelas), terbesar dulu */
    public function stockByWarehouse(): array
    {
        return $this->once('stock_wh', function () {
            $out = [];
            foreach (array_keys(StockOnhand::ITEM_KEYS) as $class) {
                foreach (StockOnhand::of($class)->where('is_usable', true)->groupBy('warehouse_name')->selectRaw('warehouse_name AS name, SUM(value_onhand) AS value')->get() as $r) {
                    $out[(string) ($r->name ?? '—')] = ($out[(string) ($r->name ?? '—')] ?? 0) + (float) $r->value;
                }
            }
            arsort($out);

            return $out;
        });
    }

    /** @return array{in: int, out: int, adjust: int, return: int} jumlah dokumen stok bertanggal di rentang itu, menurut arah (semua kelas stok) */
    public function stockDocuments(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->once('stock_docs:'.$from->toDateString().':'.$to->toDateString(), function () use ($from, $to) {
            // doc_type di view ini = doc_type KARTU stok (IN / OUT / AD / BB / TF, data lama juga ADJ), bukan angka 1 / -1 di kepala
            // dokumen; doc_id = id baris kartu. Satu dokumen = satu nomor per kelas stok, berapa pun barisnya. Tidak dihitung:
            // saldo awal (BB; data lama menulis kartunya sebagai IN dengan doc_cat BB), Stock Transfer (TF: bukan penerimaan
            // dan bukan pengeluaran), baris kartu tanpa dokumen.
            $rows = DB::connection('tm2inv')->table('v_inv_movement')->whereBetween('doc_date', [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()])
                ->whereNotNull('doc_no')->distinct()->get(['stock_class', 'doc_no', 'doc_type', 'doc_cat']);
            $docs = ['in' => [], 'out' => [], 'adjust' => [], 'return' => []];
            foreach ($rows as $r) {
                $kind = match (true) {
                    $r->doc_type === 'TF', $r->doc_type === 'BB', $r->doc_cat === 'BB' => null,
                    $r->doc_cat === 'RETURN' => 'return',
                    in_array($r->doc_type, ['AD', 'ADJ'], true) || in_array($r->doc_cat, ['AD', 'OP'], true) => 'adjust',
                    $r->doc_type === 'IN' => 'in',
                    $r->doc_type === 'OUT' => 'out',
                    default => null,
                };
                if ($kind) {
                    $docs[$kind][$r->stock_class.'|'.$r->doc_no] = true;
                }
            }

            return array_map('count', $docs);
        });
    }

    /** @return Collection<int, PvReceipt> penerimaan barang yang menunggu QC, atau sudah diposting tetapi belum diserahkan */
    public function receiptsWaiting(): Collection
    {
        return $this->once('receipts_waiting', fn () => PvReceipt::query()->where(fn ($q) => $q->where('status', 'QC_PENDING')
            ->orWhere(fn ($w) => $w->where('status', 'POSTED')->where('is_handed_over', false)))->orderBy('i_doc_date')->get(['pv_id', 'doc_no', 'i_doc_date', 'i_supplier_id', 'status', 'is_handed_over']));
    }

    // ---- Dokumen kerja: Job Description, SOP, Work Instruction ----------------------------------------------------------

    /** @return Collection<int, MntDocument> dokumen aktif satu jenis (SOP | WI), yang paling baru berlaku di atas */
    public function documents(string $type): Collection
    {
        return $this->once('docs:'.$type, fn () => MntDocument::company()->inForce()->with('department')->where('kind', $type)
            ->orderByDesc('effective_date')->orderBy('title')->get());
    }

    /** @return array{positions: int, filled: int} jabatan aktif dan yang Job Description-nya sudah diisi */
    public function jobDescriptionCoverage(): array
    {
        return $this->once('jd', fn () => ['positions' => OrgPosition::query()->where('activate', true)->count(),
            'filled' => OrgPosition::query()->where('activate', true)->whereNotNull('job_description')->whereRaw("TRIM(job_description) <> ''")->count()]);
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
