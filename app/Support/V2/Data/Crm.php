<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CrmActivity;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SysConstant;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// CRM > Customer 360 (user 2026-10-02: "CRM adalah bangun hubungan dengan customer"): satu layar per customer yang
// MERANGKUM data yang sudah ada -- inquiry / quotation, order (baru + arsip sistem lama), kiriman, aktivitas, komplain.
// Hanya membaca; data master customer tetap diubah di Master Customer. Lintas-DB (tm2sdi <-> tm2mst) = query terpisah.
// Nilai rupiah hanya untuk yang berhak melihat harga jual (SecUser::canViewSalesPrice, rulebook A.9) -- disaring di server.
final class Crm
{
    public const SEGMENT_TONES = ['Active' => 'success', 'Sleeping' => 'warn', 'Prospect' => 'info', 'No order' => 'neutral'];

    private const OPEN_ORDER = ['DRAFT', 'SUBMITTED', 'CONFIRMED'];

    public static function canSeePrice(): bool
    {
        return (bool) Auth::user()?->canViewSalesPrice();
    }

    public static function sleepingDays(): int
    {
        return max(1, (int) SysConstant::number('CRM_SLEEPING_DAYS', 180));
    }

    // Daftar customer aktif + ringkasan hubungan (satu query agregat per tabel, dicocokkan di PHP).
    public static function rows(): array
    {
        $sdi = DB::connection('tm2sdi');
        $yearAgo = now()->subYear()->toDateString();
        $legacy = $sdi->table('co_order_legacy')->where('doc_status_normalized', '<>', 'CANCELLED')->groupBy('customer_id')
            ->selectRaw('customer_id, MAX(co_date) last_date, SUM(co_date >= ?) n12', [$yearAgo])->get()->keyBy('customer_id');
        $orders = $sdi->table('co_order')->where('stat_cancel', 0)->groupBy('customer_id')
            ->selectRaw('customer_id, MAX(co_date) last_date, SUM(co_date >= ?) n12, SUM(doc_status IN ("'.implode('","', self::OPEN_ORDER).'")) open_n', [$yearAgo])->get()->keyBy('customer_id');
        $openQuo = self::openQuotations()->countBy('customer_id');
        $lastAct = $sdi->table('crm_activity')->groupBy('customer_id')->selectRaw('customer_id, MAX(activity_date) last_date')->pluck('last_date', 'customer_id');
        $nextFu = CrmActivity::query()->whereNotNull('next_action_date')->where('next_done', 0)->orderBy('next_action_date')->get()->unique('customer_id')->keyBy('customer_id');
        $complaints = $sdi->table('crm_complaint')->where('stat_confirm', 1)->where('stat_close', 0)->where('stat_cancel', 0)->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) n')->pluck('n', 'customer_id');
        $due = CrmActivities::dueResolver();
        $sleepFrom = now()->subDays(self::sleepingDays())->toDateString();

        return CustCustomer::query()->where('activate', true)->orderBy('customer_name')
            ->get(['id_customer', 'customer_code', 'customer_name', 'parent_group', 'industry_category', 'is_prospect'])
            ->map(function (CustCustomer $c) use ($legacy, $orders, $openQuo, $lastAct, $nextFu, $complaints, $due, $sleepFrom) {
                $id = $c->id_customer;
                $last = max((string) ($legacy[$id]->last_date ?? ''), (string) ($orders[$id]->last_date ?? '')) ?: null;
                $fu = $nextFu->get($id);
                $d = $fu ? $due($fu) : ['bucket' => 'NONE', 'due' => '—', 'due_tone' => 'neutral', 'row_tone' => null];

                return [
                    'id' => $id, 'name' => $c->customer_name, 'code' => $c->customer_code, 'group' => $c->parent_group, 'industry' => $c->industry_category,
                    'segment' => t(self::segment((bool) $c->is_prospect, $last, $sleepFrom)), 'last_order' => $last ? substr($last, 0, 10) : null,
                    'orders_12m' => (int) ($legacy[$id]->n12 ?? 0) + (int) ($orders[$id]->n12 ?? 0), 'open_quo' => (int) ($openQuo[$id] ?? 0),
                    'open_co' => (int) ($orders[$id]->open_n ?? 0), 'last_contact' => isset($lastAct[$id]) ? substr((string) $lastAct[$id], 0, 10) : null,
                    'next_date' => $fu?->next_action_date?->toDateString(), 'complaints' => (int) ($complaints[$id] ?? 0), 'has_complaint' => isset($complaints[$id]),
                ] + $d;
            })
            // Yang menunggu tindakan di atas (follow-up lewat / hari ini / segera, lalu komplain terbuka), sisanya menurut order 12 bulan.
            ->sortBy(fn (array $r) => [array_search($r['bucket'], ['OVERDUE', 'TODAY', 'SOON'], true) === false ? 9 : array_search($r['bucket'], ['OVERDUE', 'TODAY', 'SOON'], true),
                $r['has_complaint'] ? 0 : 1, -$r['orders_12m'], $r['name']])->values()->all();
    }

    private static function segment(bool $prospect, ?string $lastOrder, string $sleepFrom): string
    {
        return match (true) {
            $lastOrder === null => $prospect ? 'Prospect' : 'No order',
            substr($lastOrder, 0, 10) < $sleepFrom => 'Sleeping',
            default => 'Active',
        };
    }

    // Quotation terbit yang belum WIN / LOST / batal.
    private static function openQuotations(?int $customerId = null)
    {
        return QuoQuotation::query()->with('items.inquiry:id_inquiry,sub_state')->where('doc_status', 'PUBLISHED')->where('stat_cancel', 0)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))->get()->filter(fn (QuoQuotation $q) => $q->outcome() === null)->values();
    }

    public static function find(int $id): ?CustCustomer
    {
        return CustCustomer::query()->with(['paymentTerm', 'contacts', 'branches'])->find($id);
    }

    // Seluruh isi halaman satu customer.
    public static function profile(CustCustomer $c): array
    {
        $id = $c->id_customer;
        $price = self::canSeePrice();
        $sdi = DB::connection('tm2sdi');

        $quotes = QuoQuotation::query()->with('items.inquiry:id_inquiry,sub_state')->where('customer_id', $id)->orderByDesc('id_quotation')->limit(100)->get();
        $orders = CoOrder::query()->with(['items', 'externalCosts'])->where('customer_id', $id)->orderByDesc('id_order')->limit(100)->get();
        $legacy = $sdi->table('co_order_legacy')->where('customer_id', $id)->orderByDesc('co_date')->orderByDesc('id_co_legacy')->limit(200)->get();
        $deliveries = DoDelivery::query()->where('customer_id', $id)->orderByDesc('id_delivery')->limit(100)->get();
        $legacyDn = $sdi->table('co_delivery_legacy')->where('customer_id', $id)->orderByDesc('dnote_date')->limit(100)->get();
        $activities = CrmActivities::rows($id);
        $complaints = CrmComplaints::rows($id);
        $legacyAgg = $sdi->table('co_order_legacy')->where('customer_id', $id)->where('doc_status_normalized', '<>', 'CANCELLED')
            ->selectRaw('COUNT(*) n, MIN(co_date) first_date, MAX(co_date) last_date, SUM(grand_total) total')->first();
        $newAgg = $sdi->table('co_order')->where('customer_id', $id)->where('stat_cancel', 0)->selectRaw('COUNT(*) n, MIN(co_date) first_date, MAX(co_date) last_date')->first();
        $openFu = collect($activities)->whereIn('bucket', ['OVERDUE', 'TODAY', 'SOON', 'LATER'])->sortBy('next_date')->first();
        $lastOrder = max((string) $legacyAgg->last_date, (string) $newAgg->last_date) ?: null;
        $firstOrder = collect([$legacyAgg->first_date, $newAgg->first_date])->filter()->min();

        $orderRows = $orders->map(fn (CoOrder $o) => ['no' => $o->co_no ?: 'Draft #'.$o->id_order, 'date' => $o->co_date?->toDateString(), 'po' => $o->customer_po_no,
            'status' => $o->stat_cancel ? 'CANCELLED' : $o->doc_status, 'source' => t('New system'), 'total' => $price ? (float) ($o->totals()['total_billing'] ?? 0) : null, 'sort' => (string) $o->co_date?->toDateString()])
            ->concat($legacy->map(fn ($l) => ['no' => $l->legacy_co_no, 'date' => $l->co_date ? substr((string) $l->co_date, 0, 10) : null, 'po' => $l->customer_po_no, 'status' => $l->doc_status_normalized,
                'source' => t('Archive'), 'total' => $price ? (float) $l->grand_total : null, 'sort' => (string) $l->co_date]))
            ->sortByDesc('sort')->values()->all();

        return [
            'price' => $price,
            'summary' => [
                'segment' => self::segment((bool) $c->is_prospect, $lastOrder, now()->subDays(self::sleepingDays())->toDateString()),
                'first_order' => $firstOrder, 'last_order' => $lastOrder, 'orders_total' => (int) $legacyAgg->n + (int) $newAgg->n,
                'lifetime' => $price ? (float) $legacyAgg->total + (float) $orders->where('stat_cancel', false)->sum(fn (CoOrder $o) => (float) ($o->totals()['total_billing'] ?? 0)) : null,
                'open_quo' => self::openQuotations($id)->count(), 'open_co' => $orders->where('stat_cancel', false)->whereIn('doc_status', self::OPEN_ORDER)->count(),
                'open_complaints' => collect($complaints)->where('status', 'CONFIRMED')->count(),
                'last_contact' => $activities[0]['date'] ?? null, 'next_followup' => $openFu, 'open_followups' => collect($activities)->whereIn('bucket', ['OVERDUE', 'TODAY', 'SOON', 'LATER'])->count(),
            ],
            'contacts' => $c->contacts->where('activate', true)->sortByDesc('is_default')->map(fn ($k) => ['name' => $k->contact_name.($k->is_default ? ' ★' : ''), 'position' => $k->position,
                'phone' => $k->phone, 'email' => $k->email, 'note' => $k->note])->values()->all(),
            'branches' => $c->branches->where('activate', true)->map(fn ($b) => ['name' => $b->branch_name.($b->is_default ? ' ★' : ''), 'address' => $b->address, 'phone' => $b->phone])->values()->all(),
            'activities' => $activities,
            'quotations' => $quotes->map(fn (QuoQuotation $q) => ['id' => $q->id_quotation, 'no' => $q->quotation_no ?: 'Draft #'.$q->id_quotation, 'date' => $q->issued_date?->toDateString(),
                'valid' => $q->valid_until?->toDateString(), 'status' => $q->stat_cancel ? 'CANCELLED' : Quotation::status($q), 'lines' => $q->items->count(),
                'total' => $price ? (float) $q->totals()['grand'] : null])->all(),
            'orders' => $orderRows,
            'deliveries' => $deliveries->map(fn (DoDelivery $d) => ['no' => $d->do_no ?: 'Draft #'.$d->id_delivery, 'date' => $d->do_date?->toDateString(), 'ship' => ($d->actual_ship_date ?? $d->scheduled_ship_date)?->toDateString(),
                'status' => $d->status, 'source' => t('New system'), 'sort' => (string) $d->do_date])
                ->concat($legacyDn->map(fn ($l) => ['no' => $l->legacy_dnote_no, 'date' => substr((string) $l->dnote_date, 0, 10) ?: null, 'ship' => substr((string) $l->dnote_date, 0, 10) ?: null, 'status' => $l->doc_status_normalized,
                    'source' => t('Archive'), 'sort' => (string) $l->dnote_date]))->sortByDesc('sort')->values()->all(),
            'complaints' => $complaints,
            'chart' => self::monthly($id, $price),
            'products' => self::topProducts($id),
        ];
    }

    // Order per bulan, 24 bulan terakhir (arsip + sistem baru): jumlah order, dan nilainya bila berhak melihat harga.
    private static function monthly(int $customerId, bool $price): array
    {
        $from = CarbonImmutable::today()->startOfMonth()->subMonths(23);
        $sdi = DB::connection('tm2sdi');
        $legacy = $sdi->table('co_order_legacy')->where('customer_id', $customerId)->where('doc_status_normalized', '<>', 'CANCELLED')->where('co_date', '>=', $from->toDateString())
            ->selectRaw("DATE_FORMAT(co_date, '%Y-%m') ym, COUNT(*) n, SUM(grand_total) total")->groupBy('ym')->get()->keyBy('ym');
        $new = $sdi->table('co_order')->where('customer_id', $customerId)->where('stat_cancel', 0)->where('co_date', '>=', $from->toDateString())
            ->selectRaw("DATE_FORMAT(co_date, '%Y-%m') ym, COUNT(*) n")->groupBy('ym')->pluck('n', 'ym');
        $labels = $count = $value = [];
        for ($m = $from; $m->lte(CarbonImmutable::today()); $m = $m->addMonth()) {
            $ym = $m->format('Y-m');
            // Label pendek supaya 24 bulan muat: nama bulan saja, tahun hanya di bulan pertama dan tiap Januari.
            $labels[] = substr((string) Locale::d($m), 3, 3).($m->month === 1 || ! $labels ? " '".$m->format('y') : '');
            $count[] = (int) ($legacy[$ym]->n ?? 0) + (int) ($new[$ym] ?? 0);
            $value[] = round((float) ($legacy[$ym]->total ?? 0));
        }

        return ['labels' => $labels, 'count' => $count, 'value' => $price ? $value : null];
    }

    // Product yang paling sering dipesan (arsip + sistem baru): bahan pembicaraan repeat order.
    private static function topProducts(int $customerId): array
    {
        $sdi = DB::connection('tm2sdi');
        $legacy = $sdi->table('co_order_legacy_detail as d')->join('co_order_legacy as h', 'h.id_co_legacy', '=', 'd.co_legacy_id')
            ->where('h.customer_id', $customerId)->where('h.doc_status_normalized', '<>', 'CANCELLED')->whereNotNull('d.product_id')->groupBy('d.product_id')
            ->selectRaw('d.product_id, COUNT(DISTINCT h.id_co_legacy) n, SUM(d.qty) qty, MAX(h.co_date) last_date, MIN(d.uom_code) unit')->get();
        $new = $sdi->table('co_order_item as i')->join('co_order as h', 'h.id_order', '=', 'i.order_id')
            ->where('h.customer_id', $customerId)->where('h.stat_cancel', 0)->whereNotNull('i.product_id')->groupBy('i.product_id')
            ->selectRaw('i.product_id, COUNT(DISTINCT h.id_order) n, SUM(i.qty) qty, MAX(h.co_date) last_date, MIN(i.qty_unit) unit')->get();
        $rows = $legacy->concat($new)->groupBy('product_id')->map(fn ($g, $pid) => ['product_id' => (int) $pid, 'n' => (int) $g->sum('n'), 'qty' => (float) $g->sum('qty'),
            'last' => (string) $g->max('last_date'), 'unit' => $g->first()->unit])->sortByDesc('n')->take(15);
        $names = ProdProduct::query()->whereKey($rows->pluck('product_id')->all())->get(['id_product', 'product_name', 'product_code'])->keyBy('id_product');

        return $rows->map(fn ($r) => ['product' => $names[$r['product_id']]->product_name ?? '#'.$r['product_id'], 'code' => $names[$r['product_id']]->product_code ?? '',
            'n' => $r['n'], 'qty' => $r['qty'], 'unit' => $r['unit'], 'last' => Locale::d(substr($r['last'], 0, 10))])->values()->all();
    }

    // Isi objek form khusus x-mp::sales.customer-card (halaman satu customer di Customer 360): kepala + angka kunci, lalu tab
    // Overview / Activities / Quotations / Orders / Deliveries / Complaints / Contacts. Hanya membaca. 'price' = boleh melihat
    // nilai rupiah (disaring di server oleh profile()).
    public static function card(CustCustomer $c, string $home): array
    {
        $p = self::profile($c);
        $s = $p['summary'];
        $fu = $s['next_followup'];
        $actUrl = route('v2.screen', 'crm-activity');
        $cmpUrl = route('v2.screen', 'crm-complaint');
        $top = [
            ['First Order', Locale::d($s['first_order']) ?? '—', ['tm2sdi.co_order_legacy.co_date', 'tm2sdi.co_order.co_date']],
            ['Last Order', Locale::d($s['last_order']) ?? '—', ['tm2sdi.co_order_legacy.co_date', 'tm2sdi.co_order.co_date']],
            ['Orders (all time)', Locale::number($s['orders_total']), ['tm2sdi.co_order_legacy', 'tm2sdi.co_order']],
        ];
        if ($p['price']) {
            $top[] = ['Order Value (all time)', Locale::money($s['lifetime']), ['tm2sdi.co_order_legacy.grand_total', 'tm2sdi.co_order']];
        }
        $top[] = ['Last Contact', Locale::d($s['last_contact']) ?? '—', ['tm2sdi.crm_activity.activity_date']];

        return [
            'home' => $home, 'price' => (bool) $p['price'], 'title' => (string) $c->customer_name,
            'description' => implode(' · ', array_filter([$c->customer_code, $c->parent_group, $c->industry_category, t($s['segment'])])),
            'buttons' => [
                ['fa-phone-volume', '+ Activity', $actUrl.'?new=1&customer_id='.$c->id_customer],
                ['fa-triangle-exclamation', '+ Complaint', $cmpUrl.'?new=1&customer_id='.$c->id_customer],
                ['fa-building', 'Master Customer', route('v2.screen', 'md-customer-form').'?id='.$c->id_customer],
                ['back', 'Back', $home],
            ],
            'facts' => [$top, [
                ['Open Quotations', Locale::number($s['open_quo']), ['tm2sdi.quo_quotation (PUBLISHED, not WIN / LOST)']],
                ['Open Orders', Locale::number($s['open_co']), ['tm2sdi.co_order (Draft / Submitted / Confirmed)']],
                ['Open Complaints', Locale::number($s['open_complaints']), ['tm2sdi.crm_complaint (Confirmed, not Closed)']],
                ['Open Follow-ups', Locale::number($s['open_followups']), ['tm2sdi.crm_activity (next_done = 0)']],
                ['Next Follow-up', $fu ? Locale::d($fu['next_date']).' · '.$fu['due'].($fu['next'] ? ' · '.$fu['next'] : '') : '—', ['tm2sdi.crm_activity.next_action_date']],
            ]],
            'chart' => $p['chart'], 'products' => $p['products'],
            'profile' => [
                [4, [['Contact', trim(($c->mr_ms ? $c->mr_ms.' ' : '').$c->contact_name) ?: '—', ['tm2mst.cust_customer.contact_name']],
                    ['Phone', $c->phone ?: '—', ['tm2mst.cust_customer.phone']],
                    ['E-mail', $c->email ?: '—', ['tm2mst.cust_customer.email']],
                    ['Payment Term', $c->paymentTerm?->term_label ?? $c->payment_term ?? '—', ['tm2mst.cust_customer.payment_term']]]],
                [2, [['Address', $c->default_address ?: '—', ['tm2mst.cust_customer.default_address']],
                    ['Main Product', implode(' · ', array_filter([$c->primary_product, $c->secondary_product])) ?: '—', ['tm2mst.cust_customer.primary_product']]]],
                [2, [['Packing Instruction', $c->packing_instruction ?: '—', ['tm2mst.cust_customer.packing_instruction']],
                    ['Quality Notes', $c->quality_notes ?: '—', ['tm2mst.cust_customer.quality_notes']]]],
            ],
            'activities' => $p['activities'], 'activityUrl' => $actUrl.'?id={id}',
            'quotations' => $p['quotations'], 'quotationUrl' => route('v2.screen', 'quotation').'?id={id}',
            'orders' => $p['orders'], 'deliveries' => $p['deliveries'],
            'complaints' => $p['complaints'], 'complaintUrl' => $cmpUrl.'?id={id}', 'qaTones' => CrmComplaints::QA_TONES, 'complaintTones' => CrmComplaints::STATUS_TONES,
            'contacts' => $p['contacts'], 'branches' => $p['branches'],
        ];
    }
}
