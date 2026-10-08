<?php

namespace App\Services\Planning;

use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingAlloc;
use App\Models\Inventory\RmBookingD;
use App\Models\Master\MatUsageStandard;
use App\Models\Sys\SysConstant;
use App\Services\Inventory\FgAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Material Planning = MPS + MRP + Booking dalam SATU engine (docs/materialbooking_tier1.md §6-§8, keputusan user 2026-10-01).
// 1. Demand : CO aktif (CONFIRMED = firm, DRAFT/SUBMITTED = tampil saja) + sisa forecast MPS (forecast dikurangi CO bulan yang sama).
//    Tanggal butuh = yang lebih awal dari (tanggal kirim CPDEL / due date) dan (tanggal CO + BOOKING_NEED_WORKDAYS hari kerja).
// 2. Stok FG produk yang sama dibooking dulu (B7), sisa qty dipecah ke SEMUA bahan (B3): kertas dari layout ProDev
//    -> costing inquiry -> BOM; tinta / varnish / coating / film / lem dari mat_usage_standard.
// 3. Netting per bahan: prioritas CONFIRMED -> DRAFT -> FORECAST, lalu tanggal butuh; sumber stok Available -> PO tepat
//    waktu -> PR -> PO/PR telat -> SHORT. Booking yang sudah di-Confirm PPIC beku (dibaca dari DB, tidak dihitung ulang).
// Engine hanya MEMBACA master (prod_*, mat_*); yang ditulis hanya transaksi booking & PR (aturan user: master berubah hanya oleh user).
final class MaterialPlanningEngine
{
    public const TIER_CONFIRMED = 1;

    public const TIER_DRAFT = 2;

    public const TIER_FORECAST = 3;

    // Booking sejak costing (docs/md_for_systems/inventorywarehouse_v201_scope.md bagian 6): rebutan stok CO Confirmed, CO Draft,
    // (forecast), Quotation, lalu pre-booking costing. Keduanya lunak: tidak mengurangi Available sampai di-Hold atau naik jadi CO Confirmed.
    public const TIER_QUOTATION = 4;

    public const TIER_PRE = 5;

    public const STAGES = ['B0' => 'Pre-booking (ProCost published)', 'B1' => 'Quotation', 'B2' => 'Customer Order (draft)', 'B3' => 'Customer Order (confirmed)', 'B4' => 'Pick list'];

    public const STATUS_TONES = ['BOOKED' => 'success', 'STOCK' => 'success', 'FG_STOCK' => 'success', 'PEGGED_PO' => 'info', 'PEGGED_PR' => 'info',
        'LATE_PO' => 'warn', 'LATE_PR' => 'warn', 'SHORT' => 'danger', 'NEED_ITEM' => 'danger', 'NEED_LAYOUT' => 'danger'];

    // Urutan keparahan untuk ringkasan per CO / bahan.
    public const SEVERITY = ['NEED_LAYOUT' => 7, 'NEED_ITEM' => 6, 'SHORT' => 5, 'LATE_PR' => 4, 'LATE_PO' => 4, 'PEGGED_PR' => 3, 'PEGGED_PO' => 2,
        'STOCK' => 1, 'FG_STOCK' => 1, 'BOOKED' => 0];

    private static ?array $cache = null;

    private static ?Collection $frozen = null;

    private array $holidays = [];

    private array $offDays = [];

    private ?Collection $standards = null;

    private array $materials = [];

    public function __construct(private ?string $login = null)
    {
        $this->holidays = DB::connection('tm2mst')->table('sys_holiday')->where('activate', true)->pluck('holiday_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->flip()->all();
        $this->offDays = DB::connection('tm2mst')->table('sys_office_hour')->where('is_workday', false)->where('activate', true)
            ->pluck('day_of_week')->map(fn ($d) => (int) $d)->all() ?: [7];
    }

    public static function forget(): void
    {
        self::$cache = null;
        self::$frozen = null;
    }

    // ------------------------------------------------------------------ hasil lengkap (dipakai semua layar)

    /** @return array{lines: array, materials: array} */
    public function plan(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $lines = [];
        $frozenCos = RmBooking::where('stat_confirm', 1)->where('stat_cancel', 0)->where('stat_closed', 0)->whereNotNull('co_id')->pluck('co_id')->flip();
        $demands = $this->demands();
        $picks = $this->picks();
        foreach ($this->netFinishedGoods($demands->reject(fn ($d) => isset($frozenCos[$d['co_id'] ?? 0]))) as $d) {
            foreach ($this->explode($d) as $l) {
                $lines[] = $this->applyPick($l, $picks);
            }
        }
        // Pre-booking sejak ProCost terbit (B0 / B1): tanpa CO, hanya kertas dari data costing; tier paling akhir dalam rebutan stok.
        foreach ($this->preDemands() as $d) {
            foreach ($this->explodePre($d) as $l) {
                $lines[] = $this->applyPick($l, $picks);
            }
        }
        $lines = $this->net($lines);
        array_push($lines, ...$this->frozenLines());

        return self::$cache = ['lines' => $lines, 'materials' => $this->summarize($lines)];
    }

    // Pick Item PPIC (disimpan di booking, BUKAN di Master Product) menggantikan baris NEED_ITEM.
    private function applyPick(array $l, array $picks): array
    {
        $pick = $l['status'] === 'NEED_ITEM' ? ($picks[self::pickKey($l)] ?? null) : null;
        if ($pick && ($m = $this->material((int) $pick))) {
            $l = ['material_id' => (int) $pick, 'material' => $m->material_name, 'status' => null, 'picked' => true] + $l;
        }

        return $l;
    }

    // Kunci kebutuhan: baris CO per co_item_id; pre-booking (tanpa CO) per inquiry ('Q<inquiry_id>').
    public static function pickKey(array $l): string
    {
        return implode('|', [$l['co_item_id'] ?? (isset($l['inquiry_id']) && ($l['type'] ?? '') === 'PRE' ? 'Q'.$l['inquiry_id'] : 0), $l['part_id'] ?? 0, $l['role'], mb_substr((string) ($l['spec'] ?? ''), 0, 150)]);
    }

    // Item pilihan PPIC per kebutuhan (rm_booking_d.note_detail = 'PICK'), bertahan saat booking dihitung ulang.
    private function picks(): array
    {
        return DB::connection('tm2inv')->table('rm_booking_d as d')->join('rm_booking as h', 'h.booking_id', '=', 'd.booking_id')->where('h.stat_cancel', 0)
            ->where('d.note_detail', 'PICK')->whereNotNull('d.material_id')->get(['d.co_detail_id', 'h.inquiry_id', 'h.co_id as head_co', 'd.part_id', 'd.material_role', 'd.spec_text', 'd.material_id'])
            ->mapWithKeys(fn ($r) => [implode('|', [$r->co_detail_id ?? ($r->head_co === null && $r->inquiry_id ? 'Q'.$r->inquiry_id : 0), $r->part_id ?? 0, $r->material_role, $r->spec_text ?? '']) => (int) $r->material_id])->all();
    }

    public function pick(RmBookingD $d, int $materialId): void
    {
        abort_unless($this->material($materialId), 422, t('Item not found.'));
        $d->update(['material_id' => $materialId, 'note_detail' => 'PICK', 'update_by' => $this->login]);
        self::forget();
        if ($d->co_id) {
            $this->persist((int) $d->co_id);
        } elseif ($inquiryId = RmBooking::query()->whereKey($d->booking_id)->value('inquiry_id')) {
            $this->persistPre((int) $inquiryId);
        }
    }

    public function linesForCo(int $coId): array
    {
        return array_values(array_filter($this->plan()['lines'], fn ($l) => ($l['co_id'] ?? null) === $coId));
    }

    // ------------------------------------------------------------------ 1. Demand

    public function demands(): Collection
    {
        $n = (int) SysConstant::number('BOOKING_NEED_WORKDAYS', 3);
        $cos = DB::connection('tm2sdi')->table('co_order')->where('stat_cancel', 0)->whereIn('doc_status', ['DRAFT', 'SUBMITTED', 'CONFIRMED'])->get()->keyBy('id_order');
        $items = DB::connection('tm2sdi')->table('co_order_item')->whereIn('order_id', $cos->keys())->where('line_kind', 'PRODUCT')->whereNotNull('product_id')->get();
        $dels = DB::connection('tm2sdi')->table('co_order_delivery')->whereIn('item_id', $items->pluck('id_item'))->where('status', 'PLANNED')->get()->groupBy('item_id');
        $products = $this->productNames($items->pluck('product_id'));
        $customers = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $cos->pluck('customer_id'))->pluck('customer_name', 'id_customer');

        $out = collect();
        foreach ($items as $it) {
            $co = $cos[$it->order_id];
            $limit = $this->addWorkdays(CarbonImmutable::parse($co->co_date), $n);
            $rows = ($dels[$it->id_item] ?? collect())->map(fn ($d) => [$d->id_delivery, (float) $d->ship_qty, $d->ship_date]);
            if ($rows->isEmpty()) {
                $rows = collect([[null, (float) $it->qty, $it->due_date]]);
            }
            foreach ($rows as [$delId, $qty, $date]) {
                $due = $date ? CarbonImmutable::parse($date) : null;
                $need = $due && $due->lt($limit) ? $due : $limit;
                $out->push(['type' => 'CO', 'tier' => $co->doc_status === 'CONFIRMED' ? self::TIER_CONFIRMED : self::TIER_DRAFT,
                    'co_id' => (int) $co->id_order, 'co_no' => $co->co_no, 'co_status' => $co->doc_status, 'customer_id' => (int) $co->customer_id,
                    'customer' => $customers[$co->customer_id] ?? '—', 'co_item_id' => (int) $it->id_item, 'co_delivery_id' => $delId,
                    'inquiry_id' => $it->inquiry_id, 'product_id' => (int) $it->product_id, 'product' => $products[$it->product_id] ?? $it->description,
                    'qty' => $qty, 'due_date' => $due?->toDateString(), 'need_date' => $need->toDateString(),
                    'need_rule' => $due && $due->lt($limit) ? 'due date' : 'CO date + '.$n.' working days']);
            }
        }

        return $out->merge($this->forecastDemands($cos, $items, $products));
    }

    // Sisa forecast MPS per customer x product x bulan = forecast - qty CO (due date di bulan itu). Tanggal butuh = hari kerja pertama bulan itu.
    private function forecastDemands(Collection $cos, Collection $items, array $products): Collection
    {
        $horizon = CarbonImmutable::today()->addDays((int) SysConstant::number('MRP_HORIZON_DAYS', 90));
        $fc = DB::connection('tm2prd')->table('prd_demand_forecast')->where('activate', true)
            ->whereBetween('period_start', [CarbonImmutable::today()->startOfMonth()->toDateString(), $horizon->toDateString()])->get();
        if ($fc->isEmpty()) {
            return collect();
        }
        $products += $this->productNames($fc->pluck('product_id'));
        $customers = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $fc->pluck('customer_id'))->pluck('customer_name', 'id_customer');
        $used = [];
        foreach ($items as $it) {
            $co = $cos[$it->order_id];
            $month = substr((string) ($it->due_date ?? $co->co_date), 0, 7);
            $k = $co->customer_id.'|'.$it->product_id.'|'.$month;
            $used[$k] = ($used[$k] ?? 0) + (float) $it->qty;
        }

        return $fc->map(function ($f) use ($used, $products, $customers) {
            $month = substr((string) $f->period_start, 0, 7);
            $left = (float) $f->qty_forecast - ($used[$f->customer_id.'|'.$f->product_id.'|'.$month] ?? 0);
            $start = CarbonImmutable::parse($f->period_start);
            $need = $this->addWorkdays($start->lt(CarbonImmutable::today()) ? CarbonImmutable::today() : $start->subDay(), 1);

            return $left <= 0 ? null : ['type' => 'FORECAST', 'tier' => self::TIER_FORECAST, 'co_id' => null, 'co_no' => 'FC '.$month,
                'co_status' => 'FORECAST', 'customer_id' => (int) $f->customer_id, 'customer' => $customers[$f->customer_id] ?? '—', 'co_item_id' => null,
                'co_delivery_id' => null, 'inquiry_id' => null, 'forecast_id' => (int) $f->id_forecast, 'product_id' => (int) $f->product_id,
                'product' => $products[$f->product_id] ?? '#'.$f->product_id, 'qty' => $left, 'due_date' => null, 'need_date' => $need->toDateString(),
                'need_rule' => 'forecast '.$month.' (after CO consumption)'];
        })->filter()->values();
    }

    // ------------------------------------------------------------------ 1b. Pre-booking sejak ProCost terbit (B0 / B1)

    // ProCost terbit (iqp_costing.published_flag = 1) untuk inquiry yang belum punya CO aktif = kebutuhan kertas yang sudah diketahui
    // sebelum CO ada (B-01). Satu kebutuhan per inquiry (rantai chain_key = inquiry_id): B0 tanpa quotation, B1 bila quotation terbit
    // dan belum kedaluwarsa; quotation yang semuanya kedaluwarsa / batal = rantai berakhir. Inquiry yang sudah masuk CO ditangani jalur CO
    // (tidak dihitung dua kali), dan inquiry yang pre-booking-nya di-Hold dibaca dari booking beku. Hanya kolom qty / ukuran costing yang dibaca.
    public function preDemands(): Collection
    {
        $sdi = DB::connection('tm2sdi');
        $live = array_keys($this->liveCoStatus());
        $held = RmBooking::where('stat_confirm', 1)->where('stat_cancel', 0)->where('stat_closed', 0)->whereNull('co_id')->whereNotNull('chain_key')->pluck('chain_key')->map(fn ($k) => (int) $k)->all();
        $costings = $sdi->table('iqp_costing')->where('published_flag', 1)->whereNull('reversed_at')->whereNotNull('inquiry_id')->whereNotIn('inquiry_id', array_merge($live, $held) ?: [0])
            ->orderBy('id_costing')->get(['id_costing', 'inquiry_id', 'doc_date'])->keyBy('inquiry_id');
        if ($costings->isEmpty()) {
            return collect();
        }
        $inquiries = $sdi->table('iqp_inquiry')->whereIn('id_inquiry', $costings->keys())->where('stat_cancel', 0)->get()->keyBy('id_inquiry');
        $tiers = $sdi->table('iqp_tier')->whereIn('inquiry_id', $inquiries->keys())->orderByDesc('is_primary')->orderBy('tier_seq')->get()->groupBy('inquiry_id');
        $quotes = $this->quotationStates($inquiries->keys()->all());
        $products = $this->productNames($inquiries->pluck('product_id'));
        $customers = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $inquiries->pluck('customer_id')->filter())->pluck('customer_name', 'id_customer');
        $days = (int) SysConstant::number('INV_PRE_BOOKING_NEED_WORKDAYS', 14);
        $out = collect();
        foreach ($inquiries as $id => $inq) {
            $state = $quotes[$id] ?? 'NONE';
            $qty = (float) ($tiers[$id][0]->qty ?? 0);
            if ($state === 'DEAD' || $qty <= 0) {
                continue;
            }
            $due = $inq->due_date ? CarbonImmutable::parse($inq->due_date) : null;
            $need = $due ?? $this->addWorkdays(CarbonImmutable::today(), $days);
            $out->push(['type' => 'PRE', 'tier' => $state === 'ACTIVE' ? self::TIER_QUOTATION : self::TIER_PRE, 'stage' => $state === 'ACTIVE' ? 'B1' : 'B0',
                'co_id' => null, 'co_no' => $inq->inquiry_no, 'co_status' => $state === 'ACTIVE' ? 'Quotation' : 'Not CO yet',
                'customer_id' => (int) $inq->customer_id, 'customer' => $customers[$inq->customer_id] ?? ($inq->prospect_name ?: '—'), 'co_item_id' => null, 'co_delivery_id' => null,
                'inquiry_id' => (int) $id, 'costing_id' => (int) $costings[$id]->id_costing, 'product_id' => $inq->product_id ? (int) $inq->product_id : null,
                'product' => $products[$inq->product_id] ?? ($inq->product_draft_name ?: $inq->inquiry_no), 'qty' => $qty, 'due_date' => $due?->toDateString(), 'need_date' => $need->toDateString(),
                'need_rule' => $due ? 'inquiry due date' : 'ProCost published + '.$days.' working days']);
        }

        return $out;
    }

    // Status CO aktif per inquiry (CONFIRMED > SUBMITTED > DRAFT): inquiry itu sudah punya jalur CO.
    /** @return array<int, string> */
    private function liveCoStatus(): array
    {
        $rank = ['DRAFT' => 1, 'SUBMITTED' => 2, 'CONFIRMED' => 3];
        $out = [];
        foreach (DB::connection('tm2sdi')->table('co_order_item as i')->join('co_order as o', 'o.id_order', '=', 'i.order_id')->where('o.stat_cancel', 0)
            ->whereIn('o.doc_status', array_keys($rank))->whereNotNull('i.inquiry_id')->get(['i.inquiry_id', 'o.doc_status']) as $r) {
            if (($rank[$r->doc_status] ?? 0) > ($rank[$out[$r->inquiry_id] ?? ''] ?? 0)) {
                $out[(int) $r->inquiry_id] = $r->doc_status;
            }
        }

        return $out;
    }

    // ACTIVE = ada quotation terbit yang belum kedaluwarsa; DEAD = ada quotation tetapi semuanya batal / kedaluwarsa; NONE = tanpa quotation (atau masih draft).
    /** @return array<int, string> */
    private function quotationStates(array $inquiryIds): array
    {
        $today = CarbonImmutable::today()->toDateString();
        $out = [];
        foreach (DB::connection('tm2sdi')->table('quo_quotation_item as i')->join('quo_quotation as q', 'q.id_quotation', '=', 'i.quotation_id')->whereIn('i.inquiry_id', $inquiryIds)
            ->get(['i.inquiry_id', 'q.doc_status', 'q.stat_cancel', 'q.valid_until']) as $r) {
            $kind = match (true) {
                (int) $r->stat_cancel === 1 => 'DEAD',
                $r->doc_status === 'DRAFT' => 'NONE',
                $r->valid_until !== null && substr((string) $r->valid_until, 0, 10) < $today => 'DEAD',
                default => 'ACTIVE',
            };
            $out[(int) $r->inquiry_id] = match (true) {
                ($out[$r->inquiry_id] ?? null) === 'ACTIVE' || $kind === 'ACTIVE' => 'ACTIVE',
                ($out[$r->inquiry_id] ?? null) === 'NONE' || $kind === 'NONE' => 'NONE',
                default => 'DEAD',
            };
        }

        return $out;
    }

    // Kertas dari data costing (plano, cut per plano, isi per lembar kerja, gramatur, kode sheet pemasok); kolom harga tidak dibaca.
    public function explodePre(array $d): array
    {
        $sdi = DB::connection('tm2sdi');
        $base = ['type' => 'PRE', 'tier' => $d['tier'], 'stage' => $d['stage'], 'co_id' => null, 'co_no' => $d['co_no'], 'co_status' => $d['co_status'], 'customer' => $d['customer'],
            'co_item_id' => null, 'co_delivery_id' => null, 'inquiry_id' => $d['inquiry_id'], 'costing_id' => $d['costing_id'], 'product_id' => $d['product_id'], 'product' => $d['product'],
            'need_date' => $d['need_date'], 'need_rule' => $d['need_rule']];
        $parts = $sdi->table('iqp_costing_part')->where('costing_id', $d['costing_id'])->orderBy('no_urut')
            ->get(['part_id', 'part_name', 'i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'grammage_id', 'supplier_sheet_code', 'flute_type', 'qty_per_set']);
        $iqpParts = $sdi->table('iqp_part')->where('inquiry_id', $d['inquiry_id'])->get(['id_part', 'prod_part_id', 'part_name', 'qty_per_sku'])->keyBy('id_part');
        $waste = (float) SysConstant::number('BOOKING_DEFAULT_WASTE_PCT', 5);
        if ($parts->isEmpty()) {
            return [$base + $this->gap('NEED_LAYOUT', null, '—', 'The published costing has no parts: the sheets cannot be calculated')];
        }
        $out = [];
        foreach ($parts as $cp) {
            $ip = $iqpParts[$cp->part_id] ?? null;
            $partBase = $base + ['part_id' => $ip->prod_part_id ?? null, 'part' => ($ip->part_name ?? '') ?: ($cp->part_name ?: '—')];
            $up = (int) round((float) $cp->i_cut_per_plano * max(1, (float) $cp->i_isi_per_lb));
            [$w, $h] = [(float) $cp->i_panjang_plano_cm * 10, (float) $cp->i_lebar_plano_cm * 10];
            if ($up < 1 || $w <= 0 || $h <= 0) {
                $out[] = $partBase + $this->gap('NEED_LAYOUT', $partBase['part_id'], $partBase['part'], 'The published costing has no plano size or ups for this part: the sheets cannot be calculated');

                continue;
            }
            $qtyPart = $d['qty'] * ((float) $cp->qty_per_set ?: (float) ($ip->qty_per_sku ?? 1) ?: 1);
            $sheets = (int) ceil(ceil($qtyPart / $up) * (1 + $waste / 100));
            $item = $this->preSubstrateItem($cp, $ip, $w, $h);
            $out[] = $partBase + ['role' => 'SUBSTRATE', 'material_id' => $item['id'], 'material' => $item['name'], 'uom' => $item['uom'], 'qty' => $sheets, 'source' => 'COSTING',
                'note' => number_format($qtyPart, 0, '.', '').' pcs ÷ '.$up.' up = '.number_format(ceil($qtyPart / $up), 0, '.', '').' sheets × (1 + '.rtrim(rtrim(number_format($waste, 2, '.', ''), '0'), '.').'% waste) = '
                    .$sheets.' sheets · '.rtrim(rtrim(number_format($w, 1, '.', ''), '0'), '.').' × '.rtrim(rtrim(number_format($h, 1, '.', ''), '0'), '.').' mm (published costing)',
                'spec' => $item['spec'], 'status' => $item['id'] ? null : 'NEED_ITEM', 'alloc' => []];
        }

        return $out;
    }

    // Item kertas pre-booking: item yang sudah tertaut ke part inquiry, lalu BOM Master Product; selain itu spesifikasi saja (NEED_ITEM, PPIC memilih).
    private function preSubstrateItem(object $cp, ?object $ip, float $w, float $h): array
    {
        $id = $ip ? DB::connection('tm2sdi')->table('iqp_part_material')->where('part_id', $ip->id_part)->whereNotNull('rawmat_id')->orderByDesc('is_primary')->orderBy('layer_seq')->value('rawmat_id') : null;
        if (! $id && $ip?->prod_part_id) {
            $id = DB::connection('tm2mst')->table('prod_part_material')->where('part_id', $ip->prod_part_id)->where(fn ($q) => $q->where('role', 'SUBSTRATE')->orWhereNull('role'))
                ->whereNotNull('material_id')->orderByDesc('is_primary')->orderBy('layer_seq')->value('material_id');
        }
        $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
        $gram = $cp->grammage_id ? DB::connection('tm2mst')->table('mat_grammage')->where('id_grammage', $cp->grammage_id)->value('grammage_name') : null;
        $spec = trim(implode(' · ', array_filter([$gram, $cp->supplier_sheet_code ? 'sheet '.$cp->supplier_sheet_code : null, $cp->flute_type ? 'flute '.$cp->flute_type : null, $fmt($w).' × '.$fmt($h).' mm'])));
        $m = $id ? $this->material((int) $id) : null;

        return ['id' => $m ? (int) $id : null, 'name' => $m->material_name ?? 'Paper/board: '.$spec, 'uom' => $m->uom_stock ?? 'SHEET', 'spec' => $spec];
    }

    // Tulis (ulang) booking pre-booking satu inquiry: header lunak (DRAFT), baris kertas, dan sumber penutupnya. Booking yang di-Hold tidak disentuh.
    public function persistPre(int $inquiryId): ?RmBooking
    {
        self::forget();
        $demand = $this->preDemands()->firstWhere('inquiry_id', $inquiryId);
        $b = RmBooking::where('chain_key', (string) $inquiryId)->where('stat_cancel', 0)->orderByDesc('booking_id')->first();
        if (! $demand || $b?->stat_confirm || ($b && $b->co_id)) {
            return $b;
        }
        $lines = array_values(array_filter($this->plan()['lines'], fn ($l) => ($l['type'] ?? '') === 'PRE' && ($l['inquiry_id'] ?? null) === $inquiryId));

        return $this->writePre($demand, $lines, $b);
    }

    private function writePre(array $d, array $lines, ?RmBooking $b): RmBooking
    {
        return DB::connection('tm2inv')->transaction(function () use ($d, $lines, $b) {
            $head = ['booking_type' => 'PRE', 'co_id' => null, 'doc_status' => 'DRAFT', 'stat_confirm' => 0, 'stat_cancel' => 0, 'stat_closed' => 0, 'stage' => $d['stage'],
                'chain_key' => (string) $d['inquiry_id'], 'inquiry_id' => $d['inquiry_id'], 'costing_id' => $d['costing_id'], 'notes' => mb_substr('Pre-booking '.$d['co_no'].' · '.$d['customer'], 0, 255)];
            $b = $b ? tap($b)->update($head + ['update_by' => $this->login]) : RmBooking::create($head + ['booking_no' => $this->nextNo(), 'booking_date' => now(), 'create_by' => $this->login]);
            RmBookingD::where('booking_id', $b->booking_id)->delete();
            foreach ($lines as $i => $l) {
                $row = RmBookingD::create(['booking_id' => $b->booking_id, 'seq_no' => $i + 1, 'co_id' => null, 'co_detail_id' => null, 'co_delivery_id' => null, 'product_id' => $l['product_id'],
                    'part_id' => $l['part_id'] ?? null, 'material_id' => $l['material_id'], 'material_role' => $l['role'], 'uom_code' => $l['uom'], 'qty_need' => $l['qty'], 'need_date' => $l['need_date'],
                    'line_status' => $l['status'], 'calc_source' => $l['source'], 'calc_note' => mb_substr((string) $l['note'], 0, 255), 'spec_text' => $l['spec'] ? mb_substr($l['spec'], 0, 150) : null,
                    'qty_book' => 0, 'qty_purchase' => collect($l['alloc'])->whereIn('type', ['PO', 'PR'])->sum('qty'), 'qty_short' => $l['short'] ?? 0, 'qty_replacement' => 0, 'qty_issued' => 0,
                    'stat_cancel' => 0, 'note_detail' => empty($l['picked']) ? null : 'PICK', 'create_by' => $this->login]);
                foreach ($l['alloc'] as $a) {
                    RmBookingAlloc::create(['booking_detail_id' => $row->booking_detail_id, 'source_type' => $a['type'], 'source_id' => $a['id'], 'source_no' => $a['no'], 'source_date' => $a['date'],
                        'qty' => $a['qty'], 'is_late' => $a['late'], 'created_by' => $this->login]);
                }
            }
            $b->update(['last_run_at' => now(), 'update_by' => $this->login]);

            return $b->fresh();
        });
    }

    // Hitung ulang semua pre-booking: tulis yang masih berlaku (B0 / B1), tandai B2 bila inquiry sudah punya CO draft, dan
    // lepas yang rantainya berakhir (ProCost dibalik, quotation kedaluwarsa, inquiry batal). Dipanggil runAll() (tombol Recalculate All
    // dan perintah malam). CO Confirmed mengambil alih header-nya lewat persist() (adoptPre).
    public function runPre(): int
    {
        self::forget();
        $demands = $this->preDemands();
        $byInquiry = collect($this->plan()['lines'])->where('type', 'PRE')->groupBy('inquiry_id');
        $headers = RmBooking::where('stat_cancel', 0)->whereNull('co_id')->whereNotNull('chain_key')->get()->keyBy('chain_key');
        $n = 0;
        foreach ($demands as $d) {
            $b = $headers->get((string) $d['inquiry_id']);
            if ($b?->stat_confirm) {
                continue;
            }
            $this->writePre($d, $byInquiry->get($d['inquiry_id'], collect())->all(), $b);
            $n++;
        }
        $active = $demands->pluck('inquiry_id')->map(fn ($i) => (string) $i)->flip();
        $live = $this->liveCoStatus();
        foreach ($headers as $key => $b) {
            if ($b->stat_confirm || isset($active[$key])) {
                continue;
            }
            match ($live[(int) $key] ?? null) {
                'DRAFT', 'SUBMITTED' => $b->update(['stage' => 'B2', 'update_by' => $this->login]),
                'CONFIRMED' => null,
                default => $b->update(['stat_cancel' => 1, 'doc_status' => 'CANCELLED', 'notes' => 'Pre-booking ended: costing reversed, quotation expired or inquiry cancelled', 'update_by' => $this->login]),
            };
        }
        self::forget();

        return $n;
    }

    // CO Confirmed mengambil alih header pre-booking rantai yang sama (satu header B0 -> B3, B-02); header rantai lain di CO yang sama digabung.
    private function adoptPre(object $co): ?RmBooking
    {
        $inquiries = DB::connection('tm2sdi')->table('co_order_item')->where('order_id', $co->id_order)->whereNotNull('inquiry_id')->pluck('inquiry_id')->map(fn ($i) => (string) $i)->all();
        $pre = $inquiries ? RmBooking::where('stat_cancel', 0)->whereNull('co_id')->whereIn('chain_key', $inquiries)->orderByDesc('stat_confirm')->orderBy('booking_id')->get() : collect();
        $keep = $pre->first();
        if (! $keep) {
            return null;
        }
        foreach ($pre->slice(1) as $other) {
            $other->update(['stat_cancel' => 1, 'doc_status' => 'CANCELLED', 'notes' => 'Merged into booking '.$keep->booking_no, 'update_by' => $this->login]);
        }
        $keep->update(['co_id' => $co->id_order, 'booking_type' => 'CO', 'stage' => 'B2', 'notes' => 'Customer Order '.$co->co_no, 'update_by' => $this->login]);
        RmBookingD::where('booking_id', $keep->booking_id)->update(['co_id' => $co->id_order]);
        if ($keep->stat_confirm) {
            $keep->update(['co_fingerprint' => $this->fingerprint((int) $co->id_order), 'stage' => 'B3']);
        }

        return $keep->fresh();
    }

    // ------------------------------------------------------------------ Hold (penahanan stok) dan pelepasan

    // Hold = PPIC menahan stok pre-booking untuk bahan kritis: mengunci seperti booking keras (Available berkurang) tetapi habis sendiri pada
    // hold_until (INV_BOOKING_HOLD_WORKDAYS hari kerja) lewat perintah inventory:booking-maintain. Hanya untuk booking tanpa CO.
    public function hold(RmBooking $b, ?string $reason = null): void
    {
        abort_if($b->stat_confirm, 422, t('Booking is already locked.'));
        abort_if($b->co_id, 422, t('Use Confirm for the booking of a Customer Order.'));
        DB::connection('tm2inv')->transaction(function () use ($b, $reason) {
            $this->lockStock($b);
            $b->update(['stat_confirm' => 1, 'doc_status' => 'HELD', 'hold_until' => $this->addWorkdays(CarbonImmutable::today(), (int) SysConstant::number('INV_BOOKING_HOLD_WORKDAYS', 3))->toDateString(),
                'hold_by' => $this->login, 'hold_reason' => $reason ? mb_substr($reason, 0, 255) : null, 'confirm_by' => $this->login, 'confirm_date' => now(), 'update_by' => $this->login]);
        });
        self::forget();
    }

    public function releaseHold(RmBooking $b): void
    {
        abort_unless($b->doc_status === 'HELD', 422, t('This booking is not on hold.'));
        DB::connection('tm2inv')->transaction(function () use ($b) {
            RmBookingD::where('booking_id', $b->booking_id)->where('qty_need', 0)->delete();
            RmBookingD::where('booking_id', $b->booking_id)->update(['qty_book' => 0, 'location_id' => null]);
            $b->update(['stat_confirm' => 0, 'doc_status' => 'DRAFT', 'hold_until' => null, 'hold_by' => null, 'hold_reason' => null, 'confirm_by' => null, 'confirm_date' => null, 'update_by' => $this->login]);
        });
        self::forget();
        if ($b->inquiry_id) {
            $this->persistPre((int) $b->inquiry_id);
        }
    }

    // ------------------------------------------------------------------ 2. Stok FG lalu pecah ke bahan

    // Stok barang jadi produk yang sama dipakai dulu (B7: langsung ikut booking), urut prioritas; sisa qty dipecah ke bahan.
    private function netFinishedGoods(Collection $demands): Collection
    {
        // Satu sumber Available FG (App\Services\Inventory\FgAvailability): stok bisa dipakai dikurangi yang dipegang booking Confirmed / Held.
        $fg = array_map(fn (array $r) => $r['available'], FgAvailability::byProduct($demands->pluck('product_id')->filter()->unique()->values()->all()));

        return $this->sorted($demands)->map(function ($d) use (&$fg) {
            $take = min($d['qty'], max(0, $fg[$d['product_id']] ?? 0));
            if ($take > 0 && $d['tier'] !== self::TIER_FORECAST) {
                $fg[$d['product_id']] -= $take;
                $d['fg_qty'] = $take;
            }
            $d['make_qty'] = $d['qty'] - ($d['fg_qty'] ?? 0);

            return $d;
        });
    }

    /** Kebutuhan bahan satu demand. */
    public function explode(array $d): array
    {
        $base = array_intersect_key($d, array_flip(['type', 'tier', 'co_id', 'co_no', 'co_status', 'customer', 'co_item_id', 'co_delivery_id',
            'forecast_id', 'product_id', 'product', 'need_date', 'need_rule']));
        $out = [];
        if (($d['fg_qty'] ?? 0) > 0) {
            $out[] = $base + ['role' => 'FG', 'part_id' => null, 'part' => '—', 'material_id' => null, 'material' => $d['product'].' (finished goods)',
                'uom' => 'PCS', 'qty' => $d['fg_qty'], 'source' => 'FG_STOCK', 'note' => 'Finished goods stock of the same product, booked first',
                'spec' => null, 'status' => 'FG_STOCK', 'alloc' => [['type' => 'FG', 'id' => $d['product_id'], 'no' => 'FG stock', 'date' => null, 'qty' => $d['fg_qty'], 'late' => false]]];
        }
        $make = $d['make_qty'] ?? $d['qty'];
        if ($make <= 0) {
            return $out;
        }
        $parts = DB::connection('tm2mst')->table('prod_part')->where('product_id', $d['product_id'])->where('activate', 1)->get();
        $parents = $parts->pluck('parent_part_id')->filter()->flip();
        $leaves = $parts->reject(fn ($p) => isset($parents[$p->id_part]));
        if ($leaves->isEmpty()) {
            return [...$out, $base + $this->gap('NEED_LAYOUT', null, '—', 'Master Product has no parts: complete it in Pro-Dev / Master Product')];
        }
        foreach ($leaves as $p) {
            $qtyPart = $make * ((float) $p->qty_per_sku ?: 1);
            $sheet = $this->sheetBasis($p, $d);
            $partBase = $base + ['part_id' => (int) $p->id_part, 'part' => $p->part_code.($p->part_name ? ' · '.$p->part_name : '')];
            if (! $sheet) {
                $out[] = $partBase + $this->gap('NEED_LAYOUT', null, null,
                    'No confirmed Pro-Dev layout, costing or BOM size for this part: sheets cannot be calculated');

                continue;
            }
            $sheets = (int) ceil(ceil($qtyPart / $sheet['up']) * (1 + $sheet['waste'] / 100));
            $m2 = $sheets * $sheet['w'] * $sheet['h'] / 1_000_000;
            $note = number_format($qtyPart, 0, '.', '').' pcs ÷ '.$sheet['up'].' up = '.number_format(ceil($qtyPart / $sheet['up']), 0, '.', '')
                .' sheets × (1 + '.rtrim(rtrim(number_format($sheet['waste'], 2, '.', ''), '0'), '.').'% waste) = '.$sheets.' sheets · '
                .$sheet['w'].' × '.$sheet['h'].' mm';
            $item = $this->substrateItem($p, $d);
            $out[] = $partBase + ['role' => 'SUBSTRATE', 'material_id' => $item['id'], 'material' => $item['name'], 'uom' => $item['uom'] ?? 'SHEET',
                'qty' => $sheets, 'source' => $sheet['source'], 'note' => $note, 'spec' => $item['spec'], 'status' => $item['id'] ? null : 'NEED_ITEM', 'alloc' => []];
            foreach ($this->inkLines($p, $d, $sheets, $m2) as $l) {
                $out[] = $partBase + $l;
            }
            foreach ($this->standardLines($p, $qtyPart, $sheets, $m2) as $l) {
                $out[] = $partBase + $l;
            }
        }

        return $out;
    }

    private function gap(string $status, ?int $partId, ?string $part, string $note): array
    {
        return ['role' => 'SUBSTRATE', 'material_id' => null, 'material' => '—', 'uom' => '', 'qty' => 0, 'source' => null, 'note' => $note,
            'spec' => null, 'status' => $status, 'alloc' => []] + ($part !== null ? ['part_id' => $partId, 'part' => $part] : []);
    }

    // Dasar lembar: layout ProDev CONFIRMED -> costing inquiry CO -> ukuran bahan BOM vs ukuran bentangan part.
    private function sheetBasis(object $p, array $d): ?array
    {
        $groups = DB::connection('tm2mst')->table('prod_layout_group')->where('product_id', $d['product_id'])->where('status', 'CONFIRMED')->get();
        foreach ($groups as $g) {
            $gp = DB::connection('tm2mst')->table('prod_layout_group_part')->where('id_layout_group', $g->id_layout_group)->get();
            $mine = $gp->firstWhere('part_id', $p->id_part);
            if ($gp->isEmpty() || $mine) {
                // Layout satu part: up_count = UP per plano. Layout gabungan: UP part ini = qty_up_in_group.
                $up = $gp->count() > 1 ? (int) ($mine->qty_up_in_group ?? 0) : (int) $g->up_count;
                if ($up > 0 && $g->plano_w_mm > 0) {
                    return ['up' => $up, 'waste' => (float) ($g->waste_pct ?? SysConstant::number('BOOKING_DEFAULT_WASTE_PCT', 5)),
                        'w' => (float) $g->plano_w_mm, 'h' => (float) $g->plano_h_mm, 'source' => 'LAYOUT'];
                }
            }
        }
        $waste = SysConstant::number('BOOKING_DEFAULT_WASTE_PCT', 5);
        if ($d['inquiry_id'] ?? null) {
            $costing = DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $d['inquiry_id'])->orderByDesc('published_flag')->orderByDesc('id_costing')->first();
            if ($costing) {
                $iqpPart = DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $d['inquiry_id'])->where('prod_part_id', $p->id_part)->value('id_part');
                $cp = DB::connection('tm2sdi')->table('iqp_costing_part')->where('costing_id', $costing->id_costing)
                    ->when($iqpPart, fn ($q) => $q->where('part_id', $iqpPart))->orderBy('no_urut')->first();
                $up = (int) round((float) ($cp->i_cut_per_plano ?? 0) * max(1, (float) ($cp->i_isi_per_lb ?? 1)));
                if ($cp && $up > 0 && (float) $cp->i_panjang_plano_cm > 0) {
                    return ['up' => $up, 'waste' => $waste, 'w' => (float) $cp->i_panjang_plano_cm * 10, 'h' => (float) $cp->i_lebar_plano_cm * 10, 'source' => 'COSTING'];
                }
            }
        }
        $bom = DB::connection('tm2mst')->table('prod_part_material')->where('part_id', $p->id_part)->where('role', 'SUBSTRATE')->whereNotNull('size_p')->first();
        if ($bom && $p->flat_w > 0 && $p->flat_h > 0) {
            [$W, $H, $w, $h] = [(float) $bom->size_p, (float) $bom->size_l, (float) $p->flat_w, (float) $p->flat_h];
            $up = max(floor($W / $w) * floor($H / $h), floor($W / $h) * floor($H / $w));
            if ($up >= 1) {
                return ['up' => (int) $up, 'waste' => $waste, 'w' => $W, 'h' => $H, 'source' => 'BOM'];
            }
        }

        return null;
    }

    // Item kertas: BOM Master Product -> snapshot inquiry -> kosong (NEED_ITEM, dengan spesifikasi untuk dipilih PPIC).
    private function substrateItem(object $p, array $d): array
    {
        $bom = DB::connection('tm2mst')->table('prod_part_material')->where('part_id', $p->id_part)->where(fn ($q) => $q->where('role', 'SUBSTRATE')->orWhereNull('role'))
            ->orderByDesc('is_primary')->orderBy('layer_seq')->first();
        $id = $bom->material_id ?? null;
        if (! $id && ($d['inquiry_id'] ?? null)) {
            $iqpPart = DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $d['inquiry_id'])->where('prod_part_id', $p->id_part)->value('id_part');
            $id = $iqpPart ? DB::connection('tm2sdi')->table('iqp_part_material')->where('part_id', $iqpPart)->whereNotNull('rawmat_id')->orderBy('layer_seq')->value('rawmat_id') : null;
        }
        $spec = $bom ? trim(implode(' · ', array_filter([
            $bom->category_id ? DB::connection('tm2mst')->table('mat_category')->where('id_category', $bom->category_id)->value('category_name') : null,
            $bom->gram_weight ? rtrim(rtrim((string) $bom->gram_weight, '0'), '.').' gsm' : null,
            $bom->size_p ? rtrim(rtrim((string) $bom->size_p, '0'), '.').' × '.rtrim(rtrim((string) $bom->size_l, '0'), '.').' mm' : null,
        ]))) : null;
        $m = $id ? $this->material((int) $id) : null;

        return ['id' => $m ? (int) $id : null, 'name' => $m->material_name ?? ($spec ? 'Paper/board: '.$spec : 'Paper/board (item not chosen)'),
            'uom' => $m->uom_stock ?? 'SHEET', 'spec' => $spec];
    }

    private function inkLines(object $p, array $d, int $sheets, float $m2): array
    {
        $std = $this->standards()->firstWhere('material_role', 'INK');
        if (! $std) {
            return [];
        }
        $inks = DB::connection('tm2mst')->table('prod_part_ink')->where('part_id', $p->id_part)->get(['ink_name', 'coverage_pct']);
        if ($inks->isEmpty()) {
            $n = (int) collect(explode('/', (string) $p->cfg_color))->map(fn ($x) => (int) $x)->sum();
            $names = match (true) {
                $n <= 0 => [],
                $n === 4 => ['Cyan', 'Magenta', 'Yellow', 'Black'],
                default => array_map(fn ($i) => 'Color '.$i, range(1, $n)),
            };
            $inks = collect($names)->map(fn ($x) => (object) ['ink_name' => $x, 'coverage_pct' => null]);
        }
        $out = [];
        foreach ($inks->groupBy(fn ($i) => trim((string) $i->ink_name)) as $name => $rows) {
            $cov = (float) ($rows->max('coverage_pct') ?: SysConstant::number('BOOKING_INK_COVERAGE_PCT', 25));
            $qty = round($m2 * $cov / 100 * $std->qty_per_basis * $rows->count(), 3);
            $item = $this->inkItem($name) ?? ($std->default_material_id ? $this->material((int) $std->default_material_id) : null);
            $out[] = ['role' => 'INK', 'material_id' => $item ? (int) $item->material_id : null, 'material' => $item->material_name ?? 'Ink '.$name,
                'uom' => $std->uom, 'qty' => max($qty, 0.001), 'source' => 'USAGE_STD', 'spec' => 'Ink '.$name,
                'note' => round($m2, 1).' m² × '.$cov.'% coverage × '.($std->qty_per_basis * 1000).' g/m² ('.$std->usage_code.')',
                'status' => $item ? null : 'NEED_ITEM', 'alloc' => []];
        }

        return $out;
    }

    // Tinta per warna dicocokkan ke nama item (mis. "SURPRIZE TK CYAN"); yang ada stoknya didahulukan.
    private function inkItem(string $name): ?object
    {
        if (strlen($name) < 3) {
            return null;
        }
        $id = DB::connection('tm2mst')->table('mat_raw')->where('activate', 1)->where('material_name', 'like', '%'.$name.'%')
            ->where(fn ($q) => $q->where('material_name', 'like', '%INK%')->orWhere('material_name', 'like', '% TK %'))
            ->orderByDesc('cur_stock')->value('material_id');

        return $id ? $this->material((int) $id) : null;
    }

    // Varnish / coating / film / lem: standar aktif yang kata kuncinya ada di nama proses part atau kolom finishing.
    private function standardLines(object $p, float $qtyPart, int $sheets, float $m2): array
    {
        $text = strtolower(implode(' ', [$p->finishing, $p->cfg_ink,
            DB::connection('tm2mst')->table('prod_part_process')->where('part_id', $p->id_part)->pluck('process_name')->implode(' ')]));
        $out = [];
        foreach ($this->standards()->where('material_role', '!=', 'INK') as $s) {
            $kw = collect(explode(',', (string) $s->trigger_keyword))->map(fn ($k) => trim(strtolower($k)))->filter();
            $hit = $kw->first(fn ($k) => str_contains($text, $k));
            if (! $hit) {
                continue;
            }
            [$qty, $basis] = match ($s->basis) {
                'PER_1000_PCS' => [$qtyPart / 1000 * $s->qty_per_basis, number_format($qtyPart, 0, '.', '').' pcs ÷ 1000 × '.$s->qty_per_basis],
                'PER_SHEET' => [$sheets * $s->qty_per_basis, $sheets.' sheets × '.$s->qty_per_basis],
                default => [$m2 * $s->qty_per_basis, round($m2, 1).' m² × '.$s->qty_per_basis],
            };
            $m = $s->default_material_id ? $this->material((int) $s->default_material_id) : null;
            $out[] = ['role' => $s->material_role, 'material_id' => $m ? (int) $s->default_material_id : null, 'material' => $m->material_name ?? $s->usage_name,
                'uom' => $s->uom, 'qty' => round($qty, 3), 'source' => 'USAGE_STD', 'spec' => $s->usage_name,
                'note' => $basis.' '.$s->uom.' ('.$s->usage_code.', process "'.$hit.'")', 'status' => $m ? null : 'NEED_ITEM', 'alloc' => []];
        }

        return $out;
    }

    // ------------------------------------------------------------------ 3. Netting

    private function net(array $lines): array
    {
        $ids = collect($lines)->pluck('material_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return $lines;
        }
        $stock = $this->minusFrozen(DB::connection('tm2inv')->table('v_rm_available')->where('is_usable', 1)->whereIn('material_id', $ids)
            ->where('qty_available', '>', 0)->selectRaw('material_id, SUM(qty_available) qty')->groupBy('material_id')->pluck('qty', 'material_id')
            ->map(fn ($q) => (float) $q)->all(), 'STOCK');
        $po = DB::connection('tm2pur')->table('po_line as l')->join('po_order as o', 'o.po_id', '=', 'l.po_id')->whereIn('l.item_id', $ids)
            ->where('o.stat_cancel', 0)->whereNotIn('o.status', ['DRAFT', 'CANCELLED', 'CLOSED', 'RECEIVED', 'REJECTED'])->where('l.c_qty_outstanding', '>', 0)
            ->orderBy('o.i_delivery_date')->get(['l.po_line_id as id', 'l.item_id', 'o.doc_no as no', 'o.i_delivery_date as date', DB::raw('l.c_qty_outstanding * IF(IFNULL(l.c_conv_to_stock, 0) > 0, l.c_conv_to_stock, 1) as qty')])
            ->map(fn ($r) => (array) $r + ['type' => 'PO'])->all();
        $pr = DB::connection('tm2pur')->table('pr_line as l')->join('pr_requisition as r', 'r.pr_id', '=', 'l.pr_id')->whereIn('l.item_id', $ids)
            ->where('r.stat_cancel', 0)->whereNotIn('r.status', ['REJECTED', 'CANCELLED', 'CLOSED', 'FULLY_ORDERED'])
            ->whereRaw('l.i_qty > IFNULL(l.c_qty_ordered, 0)')->orderBy('l.i_need_date')
            ->get(['l.pr_line_id as id', 'l.item_id', 'r.doc_no as no', DB::raw('COALESCE(l.i_need_date, r.i_need_date) as date'), DB::raw('l.i_qty - IFNULL(l.c_qty_ordered, 0) as qty')])
            ->map(fn ($r) => (array) $r + ['type' => 'PR'])->all();
        // PO/PR ditulis dalam satuan beli -> dikali konversi ke satuan stok (PO: c_conv_to_stock baris; PR: konversi master item).
        $conv = fn (array $s) => $s['type'] === 'PR' ? ((float) ($this->material((int) $s['item_id'])->conversion_to_stock ?? 0) ?: 1) : 1;
        $incoming = collect([...$po, ...$pr])->map(fn ($s) => $s + ['left' => (float) $s['qty'] * $conv($s) - $this->frozenPeg($s['type'], (int) $s['id'])])
            ->groupBy('item_id')->map->all()->all();

        $order = collect($lines)->map(fn ($l, $i) => $l + ['_i' => $i])->filter(fn ($l) => $l['material_id'] && $l['status'] === null);
        foreach ($this->sorted($order) as $l) {
            $m = $l['material_id'];
            $need = (float) $l['qty'];
            $alloc = [];
            $take = min($need, max(0, $stock[$m] ?? 0));
            if ($take > 0) {
                $stock[$m] -= $take;
                $need -= $take;
                $alloc[] = ['type' => 'STOCK', 'id' => null, 'no' => 'Available stock', 'date' => null, 'qty' => $take, 'late' => false];
            }
            foreach ([false, true] as $late) {
                foreach ($incoming[$m] ?? [] as $k => $s) {
                    if ($need <= 0.00001 || $s['left'] <= 0) {
                        continue;
                    }
                    $isLate = $s['date'] && $s['date'] > $l['need_date'];
                    if ($isLate !== $late) {
                        continue;
                    }
                    $q = min($need, $s['left']);
                    $incoming[$m][$k]['left'] -= $q;
                    $need -= $q;
                    $alloc[] = ['type' => $s['type'], 'id' => (int) $s['id'], 'no' => $s['no'] ?: $s['type'].' #'.$s['id'], 'date' => $s['date'], 'qty' => $q, 'late' => $isLate];
                }
            }
            $types = collect($alloc);
            $status = match (true) {
                $need > 0.00001 => 'SHORT',
                $types->contains(fn ($a) => $a['late'] && $a['type'] === 'PO') => 'LATE_PO',
                $types->contains(fn ($a) => $a['late']) => 'LATE_PR',
                $types->contains('type', 'PR') => 'PEGGED_PR',
                $types->contains('type', 'PO') => 'PEGGED_PO',
                default => 'STOCK',
            };
            $lines[$l['_i']]['alloc'] = $alloc;
            $lines[$l['_i']]['short'] = max(0, round($need, 4));
            $lines[$l['_i']]['status'] = $status;
        }
        foreach ($lines as &$l) {
            $l['short'] ??= in_array($l['status'], ['NEED_ITEM', 'NEED_LAYOUT'], true) ? (float) $l['qty'] : 0;
        }

        return $lines;
    }

    private function sorted(Collection $rows): Collection
    {
        return $rows->sortBy([['tier', 'asc'], ['need_date', 'asc'], ['co_id', 'asc']])->values();
    }

    // Sumber yang sudah dipegang booking CONFIRMED (hard) tidak boleh dipakai lagi oleh usulan lain.
    private function frozenAllocs(): Collection
    {
        return self::$frozen ??= DB::connection('tm2inv')->table('rm_booking_alloc as a')->join('rm_booking_d as d', 'd.booking_detail_id', '=', 'a.booking_detail_id')
            ->join('rm_booking as h', 'h.booking_id', '=', 'd.booking_id')->where('h.stat_confirm', 1)->where('h.stat_cancel', 0)->where('h.stat_closed', 0)
            ->get(['a.source_type', 'a.source_id', 'a.qty', 'a.qty as q', 'd.material_id', 'd.product_id', 'd.qty_issued']);
    }

    private function frozenPeg(string $type, int $id): float
    {
        return (float) $this->frozenAllocs()->where('source_type', $type)->where('source_id', $id)->sum('qty');
    }

    // STOCK: v_rm_available SUDAH mengurangi qty_book booking confirmed -> tidak dikurangi lagi. FG belum punya view booking -> dikurangi di sini.
    private function minusFrozen(array $qty, string $type): array
    {
        if ($type === 'FG') {
            foreach ($this->frozenAllocs()->where('source_type', 'FG') as $a) {
                $qty[$a->product_id] = ($qty[$a->product_id] ?? 0) - (float) $a->qty;
            }
        }

        return $qty;
    }

    // Baris booking CONFIRMED: dibaca apa adanya dari DB (beku), status BOOKED untuk bagian stok.
    private function frozenLines(): array
    {
        $heads = RmBooking::where('stat_confirm', 1)->where('stat_cancel', 0)->where('stat_closed', 0)
            ->where(fn ($q) => $q->whereNotNull('co_id')->orWhere(fn ($p) => $p->whereNull('co_id')->whereNotNull('chain_key')))->get()->keyBy('booking_id');
        if ($heads->isEmpty()) {
            return [];
        }
        $cos = DB::connection('tm2sdi')->table('co_order')->whereIn('id_order', $heads->pluck('co_id'))->get()->keyBy('id_order');
        $customers = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $cos->pluck('customer_id'))->pluck('customer_name', 'id_customer');
        // Pre-booking yang di-Hold (tanpa CO) tampil sebagai kebutuhan beku dengan nomor inquiry-nya.
        $inquiries = DB::connection('tm2sdi')->table('iqp_inquiry')->whereIn('id_inquiry', $heads->pluck('inquiry_id')->filter())->get(['id_inquiry', 'inquiry_no', 'customer_id', 'prospect_name'])->keyBy('id_inquiry');
        $customers = $customers->union(DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $inquiries->pluck('customer_id')->filter())->pluck('customer_name', 'id_customer'));
        $products = [];

        return RmBookingD::with('allocs')->whereIn('booking_id', $heads->keys())->where('stat_cancel', 0)->orderBy('seq_no')->get()
            ->map(function ($r) use ($heads, $cos, $customers, $inquiries, &$products) {
                $head = $heads[$r->booking_id];
                $co = $head->co_id ? ($cos[$head->co_id] ?? null) : null;
                $inq = $head->co_id ? null : ($inquiries[$head->inquiry_id] ?? null);
                $products += $this->productNames([$r->product_id]);

                return ['type' => $head->co_id ? 'CO' : 'PRE', 'tier' => self::TIER_CONFIRMED, 'co_id' => $head->co_id ? (int) $head->co_id : null, 'inquiry_id' => $head->inquiry_id ? (int) $head->inquiry_id : null,
                    'stage' => $head->stage, 'co_no' => $co->co_no ?? ($inq->inquiry_no ?? '—'),
                    'co_status' => $co->doc_status ?? ($inq ? 'Held' : '—'), 'customer' => $customers[$co->customer_id ?? ($inq->customer_id ?? 0)] ?? ($inq->prospect_name ?? '—'), 'co_item_id' => $r->co_detail_id,
                    'co_delivery_id' => $r->co_delivery_id, 'product_id' => $r->product_id, 'product' => $products[$r->product_id] ?? '—',
                    'need_date' => $r->need_date?->toDateString(), 'need_rule' => 'booked', 'part_id' => $r->part_id,
                    'part' => $r->part_id ? DB::connection('tm2mst')->table('prod_part')->where('id_part', $r->part_id)->value('part_code') : '—',
                    'role' => $r->material_role, 'material_id' => $r->material_id, 'material' => $r->material_id ? ($this->material((int) $r->material_id)->material_name ?? '—') : ($r->spec_text ?: '—'),
                    'uom' => $r->uom_code, 'qty' => (float) $r->qty_need, 'source' => $r->calc_source, 'note' => $r->calc_note, 'spec' => $r->spec_text,
                    'status' => $r->line_status === 'STOCK' || $r->line_status === 'FG_STOCK' ? 'BOOKED' : $r->line_status, 'short' => (float) $r->qty_short,
                    'frozen' => true, 'alloc' => $r->allocs->map(fn ($a) => ['type' => $a->source_type, 'id' => $a->source_id, 'no' => $a->source_no,
                        'date' => $a->source_date?->toDateString(), 'qty' => (float) $a->qty, 'late' => $a->is_late])->all()];
            })->all();
    }

    // ------------------------------------------------------------------ ringkasan per bahan (Shortage Planner)

    private function summarize(array $lines): array
    {
        $out = [];
        foreach (collect($lines)->filter(fn ($l) => $l['role'] !== 'FG')->groupBy(fn ($l) => $l['material_id'] ?: 'SPEC:'.($l['spec'] ?: $l['material'])) as $key => $rows) {
            $first = $rows->sortBy('need_date')->first();
            $m = $first['material_id'] ? $this->material((int) $first['material_id']) : null;
            $short = (float) $rows->sum('short');
            $lead = $m ? $this->leadDays($m) : null;
            $orderBy = $short > 0 && $lead !== null ? $this->subWorkdays(CarbonImmutable::parse($rows->where('short', '>', 0)->min('need_date')), $lead)->toDateString() : null;
            $worst = $rows->pluck('status')->sortByDesc(fn ($s) => self::SEVERITY[$s] ?? 0)->first();
            $out[] = ['key' => (string) $key, 'material_id' => $first['material_id'], 'material' => $first['material'], 'code' => $m->material_code ?? null,
                'role' => $first['role'], 'uom' => $first['uom'], 'need' => (float) $rows->sum('qty'), 'short' => $short,
                'stock' => (float) $rows->sum(fn ($l) => collect($l['alloc'])->whereIn('type', ['STOCK'])->sum('qty')),
                'incoming' => (float) $rows->sum(fn ($l) => collect($l['alloc'])->whereIn('type', ['PO', 'PR'])->sum('qty')),
                'first_need' => $rows->min('need_date'), 'order_by' => $orderBy, 'lead_days' => $lead, 'status' => $worst,
                'orders' => $rows->pluck('co_no')->unique()->values()->all(), 'supplier_id' => $m->suggest_supplier_id ?? null,
                'firm_short' => (float) $rows->where('tier', self::TIER_CONFIRMED)->sum('short'),
                // Kekurangan yang hanya berasal dari pre-booking (belum ada CO): sinyal pembelian dini (B-04), label "Not CO yet".
                'pre_short' => (float) $rows->where('type', 'PRE')->where('tier', '>=', self::TIER_QUOTATION)->sum('short')];
        }

        return collect($out)->sortBy([['short', 'desc'], ['first_need', 'asc']])->values()->all();
    }

    public function leadDays(object $m): int
    {
        if ((int) $m->lead_time_days > 0) {
            return (int) $m->lead_time_days;
        }
        $cat = DB::connection('tm2mst')->table('mat_category')->where('category_name', $m->category1)->where('level', 1)->first();
        for ($i = 0; $cat && $i < 6; $i++) {
            if ((int) $cat->lead_time_days > 0) {
                return (int) $cat->lead_time_days;
            }
            $cat = $cat->parent_id ? DB::connection('tm2mst')->table('mat_category')->where('id_category', $cat->parent_id)->first() : null;
        }

        return (int) SysConstant::number('PURCHASE_LEAD_DAYS_DEFAULT', 7);
    }

    // ------------------------------------------------------------------ tulis booking (transaksi)

    // Simpan usulan booking CO CONFIRMED (DRAFT, soft). Booking yang sudah CONFIRMED tidak disentuh.
    public function persist(int $coId): ?RmBooking
    {
        $co = DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->first();
        if (! $co || $co->doc_status !== 'CONFIRMED' || $co->stat_cancel) {
            return null;
        }
        $b = RmBooking::where('co_id', $coId)->where('stat_cancel', 0)->first();
        if ($b?->stat_confirm) {
            return $b;
        }
        // Satu rantai, satu header (B-02): booking pre-booking inquiry yang sama diambil alih, bukan dibuat ganda.
        $b ??= $this->adoptPre($co);
        if ($b?->stat_confirm) {
            self::forget();

            return $b;
        }
        self::forget();
        $lines = $this->linesForCo($coId);
        $chain = $this->chainOf($coId);

        return DB::connection('tm2inv')->transaction(function () use ($b, $co, $lines, $coId, $chain) {
            $b ??= RmBooking::create(['booking_no' => $this->nextNo(), 'booking_date' => now(), 'booking_type' => 'CO', 'co_id' => $coId,
                'doc_status' => 'DRAFT', 'stat_confirm' => 0, 'stat_cancel' => 0, 'stat_closed' => 0, 'notes' => 'Customer Order '.$co->co_no, 'create_by' => $this->login]);
            $b->update(['stage' => 'B2'] + $chain);
            RmBookingD::where('booking_id', $b->booking_id)->delete();
            foreach (array_values($lines) as $i => $l) {
                $d = RmBookingD::create(['booking_id' => $b->booking_id, 'seq_no' => $i + 1, 'co_id' => $coId, 'co_detail_id' => $l['co_item_id'],
                    'co_delivery_id' => $l['co_delivery_id'], 'product_id' => $l['product_id'], 'part_id' => $l['part_id'] ?? null, 'material_id' => $l['material_id'],
                    'material_role' => $l['role'], 'uom_code' => $l['uom'], 'qty_need' => $l['qty'], 'need_date' => $l['need_date'], 'line_status' => $l['status'],
                    'calc_source' => $l['source'], 'calc_note' => mb_substr((string) $l['note'], 0, 255), 'spec_text' => $l['spec'] ? mb_substr($l['spec'], 0, 150) : null,
                    'qty_book' => 0, 'qty_purchase' => collect($l['alloc'])->whereIn('type', ['PO', 'PR'])->sum('qty'), 'qty_short' => $l['short'] ?? 0,
                    'qty_replacement' => 0, 'qty_issued' => 0, 'stat_cancel' => 0, 'note_detail' => empty($l['picked']) ? null : 'PICK', 'create_by' => $this->login]);
                foreach ($l['alloc'] as $a) {
                    RmBookingAlloc::create(['booking_detail_id' => $d->booking_detail_id, 'source_type' => $a['type'], 'source_id' => $a['id'], 'source_no' => $a['no'],
                        'source_date' => $a['date'], 'qty' => $a['qty'], 'is_late' => $a['late'], 'created_by' => $this->login]);
                }
            }
            $b->update(['last_run_at' => now(), 'update_by' => $this->login]);

            return $b->fresh();
        });
    }

    // Confirm = kunci (hard). Stok dibooking per lokasi (v_rm_available mengurangi per material + lokasi).
    public function confirm(RmBooking $b): void
    {
        abort_if($b->stat_confirm, 422, t('Booking is already confirmed.'));
        DB::connection('tm2inv')->transaction(function () use ($b) {
            $this->lockStock($b);
            $b->update(['stat_confirm' => 1, 'doc_status' => 'CONFIRMED', 'stage' => 'B3', 'confirm_by' => $this->login, 'confirm_date' => now(),
                'co_fingerprint' => $this->fingerprint((int) $b->co_id), 'update_by' => $this->login]);
        });
        self::forget();
    }

    // Pegang stok per lokasi untuk bagian STOCK tiap baris (qty_book + location_id); dipakai Confirm dan Hold. Dipanggil di dalam transaksi.
    private function lockStock(RmBooking $b): void
    {
        foreach (RmBookingD::with('allocs')->where('booking_id', $b->booking_id)->get() as $d) {
            $stock = (float) $d->allocs->where('source_type', 'STOCK')->sum('qty');
            if ($stock <= 0 || ! $d->material_id) {
                continue;
            }
            $locs = DB::connection('tm2inv')->table('v_rm_available')->where('material_id', $d->material_id)->where('is_usable', 1)
                ->where('qty_available', '>', 0)->orderByDesc('qty_available')->get(['location_id', 'qty_available']);
            $first = true;
            foreach ($locs as $loc) {
                if ($stock <= 0) {
                    break;
                }
                $q = min($stock, (float) $loc->qty_available);
                $stock -= $q;
                if ($first) {
                    $d->update(['qty_book' => $q, 'location_id' => $loc->location_id]);
                    $first = false;
                } else {
                    // Stok di lokasi lain: baris tambahan qty_need 0 (bukan kebutuhan baru), hanya memegang stok lokasi itu.
                    RmBookingD::create(array_merge($d->only(['booking_id', 'co_id', 'co_detail_id', 'co_delivery_id', 'product_id', 'part_id', 'material_id',
                        'material_role', 'uom_code', 'need_date', 'calc_source']), ['seq_no' => $d->seq_no, 'qty_need' => 0, 'qty_book' => $q, 'qty_purchase' => 0,
                            'qty_short' => 0, 'qty_replacement' => 0, 'qty_issued' => 0, 'location_id' => $loc->location_id, 'line_status' => 'STOCK',
                            'calc_note' => 'Stock held at another location for line '.$d->seq_no, 'stat_cancel' => 0, 'create_by' => $this->login]));
                }
            }
        }
    }

    // Rantai kebutuhan satu CO: inquiry pertama pada barisnya dan ProCost terbitnya (header booking menyimpannya untuk B-02).
    /** @return array{chain_key: ?string, inquiry_id: ?int, costing_id: ?int} */
    private function chainOf(int $coId): array
    {
        $inquiryId = DB::connection('tm2sdi')->table('co_order_item')->where('order_id', $coId)->whereNotNull('inquiry_id')->orderBy('id_item')->value('inquiry_id');
        $costingId = $inquiryId ? DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $inquiryId)->orderByDesc('published_flag')->orderByDesc('id_costing')->value('id_costing') : null;

        return ['chain_key' => $inquiryId ? (string) $inquiryId : null, 'inquiry_id' => $inquiryId ? (int) $inquiryId : null, 'costing_id' => $costingId ? (int) $costingId : null];
    }

    // Back to Draft: lepas kunci (stok kembali Available), lalu dihitung ulang.
    public function unlock(RmBooking $b): void
    {
        DB::connection('tm2inv')->transaction(function () use ($b) {
            RmBookingD::where('booking_id', $b->booking_id)->where('qty_need', 0)->delete();
            RmBookingD::where('booking_id', $b->booking_id)->update(['qty_book' => 0, 'location_id' => null]);
            $b->update(['stat_confirm' => 0, 'doc_status' => 'DRAFT', 'stage' => 'B2', 'confirm_by' => null, 'confirm_date' => null, 'update_by' => $this->login]);
        });
        self::forget();
        $this->persist((int) $b->co_id);
    }

    // Hitung ulang semua: lepas booking yang CO-nya dibatalkan / sudah tidak ada, lalu simpan usulan tiap CO CONFIRMED
    // (booking yang sudah dikunci tidak disentuh). Dipanggil tombol Recalculate All, perintah malam, dan pemicu CO.
    public function runAll(): int
    {
        $alive = DB::connection('tm2sdi')->table('co_order')->where('doc_status', 'CONFIRMED')->where('stat_cancel', 0)->pluck('id_order');
        foreach (RmBooking::where('stat_cancel', 0)->whereNotNull('co_id')->whereNotIn('co_id', $alive)->pluck('co_id') as $gone) {
            $this->release((int) $gone, 'Customer Order cancelled, reopened or removed');
        }
        self::forget();
        $n = 0;
        foreach ($alive as $id) {
            $n += $this->persist((int) $id) ? 1 : 0;
        }
        $this->runPre();

        return $n;
    }

    public function release(int $coId, string $why): void
    {
        RmBooking::where('co_id', $coId)->where('stat_cancel', 0)->update(['stat_cancel' => 1, 'doc_status' => 'CANCELLED', 'notes' => mb_substr($why, 0, 255),
            'update_by' => $this->login]);
        self::forget();
    }

    // CO berubah sesudah booking di-Confirm (qty / tanggal / item) -> tampil peringatan "CO changed", PPIC Back to Draft.
    public function fingerprint(int $coId): string
    {
        $items = DB::connection('tm2sdi')->table('co_order_item')->where('order_id', $coId)->orderBy('id_item')->get(['id_item', 'product_id', 'qty', 'due_date']);
        $dels = DB::connection('tm2sdi')->table('co_order_delivery')->where('order_id', $coId)->where('status', 'PLANNED')->orderBy('id_delivery')->get(['id_delivery', 'ship_qty', 'ship_date']);

        return hash('sha256', json_encode([$items, $dels]));
    }

    private function nextNo(): string
    {
        $prefix = 'BK'.now()->format('y');
        $last = RmBooking::where('booking_no', 'like', $prefix.'%')->orderByDesc('booking_no')->value('booking_no');

        return $prefix.str_pad((string) ((int) substr((string) $last, 4) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ bantu

    public function standards(): Collection
    {
        return $this->standards ??= MatUsageStandard::where('activate', true)->orderBy('usage_code')->get();
    }

    public function material(int $id): ?object
    {
        return $this->materials[$id] ??= DB::connection('tm2mst')->table('mat_raw')->where('material_id', $id)
            ->first(['material_id', 'material_code', 'material_name', 'category1', 'uom_stock', 'uom_purchase', 'conversion_to_stock', 'suggest_supplier_id', 'lead_time_days', 'last_unitprice']);
    }

    private function productNames(iterable $ids): array
    {
        return DB::connection('tm2mst')->table('prod_product')->whereIn('id_product', collect($ids)->filter()->unique())->pluck('product_name', 'id_product')->all();
    }

    public function isWorkday(CarbonImmutable $d): bool
    {
        return ! in_array($d->dayOfWeekIso, $this->offDays, true) && ! isset($this->holidays[$d->toDateString()]);
    }

    public function addWorkdays(CarbonImmutable $d, int $n): CarbonImmutable
    {
        while ($n > 0) {
            $d = $d->addDay();
            if ($this->isWorkday($d)) {
                $n--;
            }
        }

        return $d;
    }

    public function subWorkdays(CarbonImmutable $d, int $n): CarbonImmutable
    {
        while ($n > 0) {
            $d = $d->subDay();
            if ($this->isWorkday($d)) {
                $n--;
            }
        }

        return $d;
    }
}
