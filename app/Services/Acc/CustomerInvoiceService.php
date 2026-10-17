<?php

/*
 * REMARK
 * Tujuan       : Membuat dan memposting tagihan penjualan (invoice, uang muka, pelunasan) dari Customer Order / Delivery Order; tarif PPN dibekukan di faktur menurut tanggal faktur.
 * Sumber hulu  : CoOrder, DoDelivery + POD, TaxRate::ppn(tanggal faktur) (layar Tax Rate), konstanta DP_VAT_ON_RECEIPT.
 * Input        : CO, tanggal faktur, user.
 * Output       : fin_invoice + baris + jurnal (tax_rate disimpan di faktur sebagai snapshot).
 * Dipakai oleh : To Invoice, Customer Invoice v2, Down Payment.
 * Status       : Aktif.
 */

namespace App\Services\Acc;

use App\Models\Acc\FinCompanyBank;
use App\Models\Acc\FinDpAllocation;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinInvoiceLine;
use App\Models\Acc\FinPaymentAlloc;
use App\Models\Acc\FinVoucher;
use App\Models\Master\CustCustomer;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\ProdPart;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDeliveryPod;
use App\Models\Sdi\DoLine;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SysConstant;
use App\Support\DbName;
use App\Support\Docs\DocSeries;
use App\Support\Finance\TaxRate;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Customer Invoice + uang muka (ketentuan: docs/md_for_systems/customerinvoice_accounting.md).
//   Invoice       : baris = baris Surat Jalan yang sudah diterima customer dan belum ditagih; harga dari Customer Order.
//                   Confirm = nomor dari seri + jurnal (Piutang / Penjualan / PPN) + potongan uang muka + qty_invoiced.
//   Down Payment  : tagihan uang muka TIDAK menjurnal; jurnal lahir saat uangnya diterima (receiveDownPayment).
// Sesudah Confirm dokumen tidak berubah: koreksi lewat cancel() (jurnal balik) selama belum ada pembayaran.
// Semua pesan salah dilempar sebagai DomainException (teks English lewat t() / tf(): sudah dalam bahasa tampilan).
class CustomerInvoiceService
{
    public const INVOICE = 'CUSTOMER_INVOICE';

    public const DOWN_PAYMENT = 'CUSTOMER_DP';

    // jenis inquiry => kode jenis order di cetakan (sama dengan laporan CO)
    private const ORDER_TYPES = ['NEW' => 'NEW', 'RECALC' => 'RTP', 'REVISE' => 'RDP'];

    public function __construct(private JournalPostingService $journal, private FinPeriodGuard $guard, private ArApService $arap) {}

    // ---------------------------------------------------------------- yang bisa ditagih

    /** Baris Surat Jalan satu order yang sudah diterima customer dan masih punya sisa belum ditagih. */
    public function billable(int $coId): Collection
    {
        return DoLine::query()->with(['delivery', 'orderItem'])->where('co_order_id', $coId)->whereNotNull('qty_received')
            ->whereHas('delivery', fn ($q) => $q->where('do_type', '<>', 'SUBCON')->whereNotIn('status', ['DRAFT', 'CANCELLED']))
            ->orderBy('delivery_id')->orderBy('line_no')->get()
            ->filter(fn (DoLine $l) => $this->remaining($l) > 0)->values();
    }

    public function remaining(DoLine $l): float
    {
        return round((float) $l->qty_received - (float) $l->qty_invoiced, 2);
    }

    /** Uang muka order ini yang uangnya sudah diterima dan belum habis dipotong: [id tagihan DP => sisa]. */
    public function downPaymentsAvailable(?int $coId, ?int $exceptInvoiceId = null): Collection
    {
        if (! $coId) {
            return collect();
        }
        $dps = FinInvoice::withoutGlobalScopes()->where('invoice_type', self::DOWN_PAYMENT)->where('co_id', $coId)->where('doc_status', 'POSTED')
            ->where('paid_amount', '>', 0)->orderBy('invoice_date')->orderBy('invoice_id')->get();
        $used = FinDpAllocation::query()->whereIn('dp_invoice_id', $dps->modelKeys())
            ->when($exceptInvoiceId, fn ($q) => $q->where('final_invoice_id', '<>', $exceptInvoiceId))
            ->selectRaw('dp_invoice_id, SUM(allocated_base + allocated_tax) used')->groupBy('dp_invoice_id')->pluck('used', 'dp_invoice_id');

        return $dps->map(fn (FinInvoice $dp) => ['dp' => $dp, 'used' => round((float) ($used[$dp->invoice_id] ?? 0), 2),
            'remaining' => round(min((float) $dp->paid_amount, (float) $dp->total_amount) - (float) ($used[$dp->invoice_id] ?? 0), 2)])
            ->filter(fn (array $r) => $r['remaining'] > 0)->keyBy(fn (array $r) => $r['dp']->invoice_id);
    }

    // ---------------------------------------------------------------- membuat Draft

    /** Draft invoice dari baris Surat Jalan terpilih (kosong = semua yang bisa ditagih). */
    public function createInvoice(CoOrder $co, array $doLineIds, string $user): FinInvoice
    {
        $lines = $this->billable($co->id_order)->when($doLineIds, fn ($c) => $c->whereIn('id_line', array_map('intval', $doLineIds)))->values();
        $this->fail($lines->isEmpty(), t('Nothing to invoice: no received delivery note line is left for this order.'));
        $customer = CustCustomer::withoutGlobalScopes()->findOrFail($co->customer_id);
        $date = $lines->max(fn (DoLine $l) => $l->delivery->do_date?->toDateString()) ?: now()->toDateString();
        $spec = $this->spec($lines->pluck('orderItem')->filter()->unique('id_item'));

        return DB::connection('tm2acc')->transaction(function () use ($co, $customer, $lines, $date, $spec, $user) {
            $inv = FinInvoice::create($this->header($co, $customer, self::INVOICE, $date, $user) + [
                'source_type' => 'DELIVERY', 'description' => 'Invoice '.$co->co_no,
                'discount_pct' => round((float) $co->discount_doc_pct + (float) $co->discount_qty_pct, 2),
            ]);
            foreach ($lines as $i => $l) {
                FinInvoiceLine::create([
                    'invoice_id' => $inv->invoice_id, 'line_no' => $i + 1, 'do_line_id' => $l->id_line, 'delivery_id' => $l->delivery_id, 'delivery_no' => $l->delivery->do_no,
                    'delivery_date' => $l->delivery->do_date?->toDateString(), 'co_order_item_id' => $l->co_order_item_id, 'product_id' => $l->orderItem?->product_id,
                    'description' => $l->item_description ?: (string) $l->orderItem?->description, 'color_config' => $spec[$l->co_order_item_id]['cfg'] ?? null,
                    'order_type' => $spec[$l->co_order_item_id]['type'] ?? null, 'is_lump_sum' => (bool) $l->orderItem?->qty_minim,
                    'qty_delivered' => $l->qty_do, 'qty_received' => $l->qty_received, 'qty_invoiced' => $this->remaining($l), 'qty_unit' => $l->qty_unit,
                    'unit_price' => $l->unit_price, 'created_by' => $user, 'updated_by' => $user,
                ]);
            }
            $this->recalc($inv->load('lines'));
            $inv->dp_plan = $this->proposeDownPayments($inv);
            $inv->save();

            return $inv;
        });
    }

    /** Draft tagihan uang muka atas satu Customer Order: persen bawaan dari konstanta DP_DEFAULT_PERCENT. */
    public function createDownPayment(CoOrder $co, string $user): FinInvoice
    {
        $customer = CustCustomer::withoutGlobalScopes()->findOrFail($co->customer_id);
        $co->loadMissing(['items', 'externalCosts']);
        $order = round((float) $co->totals()['total_billing'], 2);
        $this->fail($order <= 0, t('This order has no value yet.'));
        $inv = new FinInvoice($this->header($co, $customer, self::DOWN_PAYMENT, now()->toDateString(), $user) + [
            'source_type' => 'ORDER', 'order_amount' => $order, 'dp_percent' => SysConstant::number('DP_DEFAULT_PERCENT', 50),
        ]);
        $inv->description = 'Down payment '.$this->pct($inv->dp_percent).'% '.$co->co_no;
        $this->setDownPaymentAmount($inv, round($order * (float) $inv->dp_percent / 100, 2));
        $inv->save();

        return $inv;
    }

    private function header(CoOrder $co, CustCustomer $customer, string $type, string $date, string $user): array
    {
        $ppn = (bool) $co->is_ppn;
        $term = $co->payment_term ?: $customer->payment_term;
        $bank = FinCompanyBank::query()->where('activate', 1)->orderByDesc('is_default')->orderBy('id_company_bank')->get()->first(fn (FinCompanyBank $b) => $b->usableFor($ppn));

        return [
            'invoice_number' => null, 'invoice_type' => $type, 'partner_type' => 'CUSTOMER', 'partner_id' => $customer->id_customer, 'co_id' => $co->id_order,
            'is_ppn' => $ppn, 'tax_rate' => $ppn ? TaxRate::ppn($date) : 0, 'payment_term_code' => $term, 'customer_po_no' => $co->customer_po_no,
            'bill_to_name' => $customer->customer_name, 'bill_to_address' => $customer->default_address, 'company_bank_id' => $bank?->id_company_bank,
            'invoice_date' => $date, 'due_date' => $this->dueDate($date, $term), 'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'paid_amount' => 0,
            'payment_status' => 'UNPAID', 'doc_status' => 'DRAFT', 'revenue_account_id' => $this->arap->accountId('411000') ?: null,
            'created_by' => $user, 'updated_by' => $user,
        ];
    }

    // CFG warna + jenis order per baris CO (cara yang sama dengan laporan CO): dari inquiry / master product-nya.
    private function spec(Collection $items): array
    {
        $inq = $items->mapWithKeys(fn (CoOrderItem $i) => [$i->id_item => $i->inquiry_id ?: $i->product?->source_inquiry_id]);
        $ids = $inq->filter()->unique();
        $types = IqpInquiry::query()->whereIn('id_inquiry', $ids)->pluck('inquiry_type', 'id_inquiry');
        $byInq = IqpPart::query()->whereIn('inquiry_id', $ids)->whereNotNull('cfg_color')->where('cfg_color', '<>', '')->orderBy('part_seq')
            ->get(['inquiry_id', 'cfg_color'])->groupBy('inquiry_id')->map(fn ($p) => $p->pluck('cfg_color')->unique()->first());
        $byProduct = ProdPart::query()->whereIn('product_id', $items->pluck('product_id')->filter()->unique())->whereNotNull('cfg_color')->where('cfg_color', '<>', '')
            ->get(['product_id', 'cfg_color'])->groupBy('product_id')->map(fn ($p) => $p->pluck('cfg_color')->unique()->first());

        return $items->mapWithKeys(fn (CoOrderItem $i) => [$i->id_item => [
            'type' => self::ORDER_TYPES[$types[$inq[$i->id_item]] ?? ''] ?? null,
            'cfg' => mb_substr((string) (($inq[$i->id_item] ? ($byInq[$inq[$i->id_item]] ?? null) : null) ?? ($byProduct[$i->product_id] ?? '')), 0, 10) ?: null,
        ]])->all();
    }

    // ---------------------------------------------------------------- hitung

    // Syarat bayar: kode master (hari dari master) atau teks lama berangka ("30 hari"); selain itu jatuh tempo hari itu juga.
    public function dueDate(string $date, ?string $term): string
    {
        $days = FinPaymentTerm::query()->where('payment_term_code', (string) $term)->value('days');
        if ($days === null && preg_match('/(\d+)/', (string) $term, $m)) {
            $days = (int) $m[1];
        }

        return CarbonImmutable::parse($date)->addDays((int) $days)->toDateString();
    }

    /** Hitung ulang nilai baris + total Draft invoice dari barisnya (belum menyimpan nomor / jurnal). */
    public function recalc(FinInvoice $inv): FinInvoice
    {
        $inv->loadMissing('lines');
        foreach ($inv->lines as $l) {
            $l->line_total = $l->is_lump_sum ? ($this->lumpSumBilledElsewhere($l) ? 0 : round((float) $l->unit_price, 2)) : round((float) $l->qty_invoiced * (float) $l->unit_price, 2);
            $l->save();
        }
        $t = $this->totals($inv);
        $inv->fill(['subtotal' => $t['subtotal'], 'discount_amount' => $t['discount'], 'tax_amount' => $t['tax'], 'total_amount' => $t['total'],
            'due_date' => $this->dueDate($inv->invoice_date->toDateString(), $inv->payment_term_code)])->save();

        return $inv;
    }

    // Baris borongan (Qty Minim) ditagih satu kali: sudah ada di invoice lain yang masih berlaku -> nilainya 0 di sini.
    private function lumpSumBilledElsewhere(FinInvoiceLine $l): bool
    {
        return $l->co_order_item_id && FinInvoiceLine::query()->where('co_order_item_id', $l->co_order_item_id)->where('is_lump_sum', 1)->where('line_total', '>', 0)
            ->where('invoice_id', '<>', $l->invoice_id)->whereHas('invoice', fn ($q) => $q->withoutGlobalScopes()->where('doc_status', '<>', 'CANCELLED'))->exists();
    }

    /** Angka invoice: subtotal, diskon, biaya lain, dasar pajak, PPN, total, uang muka dipotong, sisa. */
    public function totals(FinInvoice $inv): array
    {
        if ($inv->invoice_type === self::DOWN_PAYMENT) {
            $total = round((float) $inv->total_amount, 2);

            return ['subtotal' => round((float) $inv->subtotal, 2), 'discount' => 0.0, 'charge' => 0.0, 'dpp' => round((float) $inv->subtotal, 2), 'tax' => round((float) $inv->tax_amount, 2),
                'total' => $total, 'dp' => 0.0, 'balance' => round($total - (float) $inv->paid_amount, 2)];
        }
        $charge = round((float) $inv->charge_amount, 2);
        if ($inv->doc_status === 'DRAFT') {
            $sub = round((float) $inv->lines->sum('line_total'), 2);
            $discount = round($sub * (float) $inv->discount_pct / 100, 2);
            $dpp = round($sub - $discount + $charge, 2);
            $tax = $inv->is_ppn ? round($dpp * (float) $inv->tax_rate / 100, 2) : 0.0;
            $total = round($dpp + $tax, 2);
        } else {
            // Sesudah Confirm angka tidak dihitung ulang: yang berlaku = yang tersimpan (juga untuk invoice lama tanpa baris).
            $sub = round((float) $inv->subtotal, 2);
            $discount = round((float) $inv->discount_amount, 2);
            $dpp = round($sub - $discount + $charge, 2);
            $tax = round((float) $inv->tax_amount, 2);
            $total = round((float) $inv->total_amount, 2);
        }
        $dp = $inv->doc_status === 'DRAFT' ? round(array_sum(array_map('floatval', (array) $inv->dp_plan)), 2) : round((float) $inv->dp_applied_amount, 2);
        $cash = $inv->doc_status === 'DRAFT' ? 0.0 : round((float) $inv->paid_amount - (float) $inv->dp_applied_amount, 2);

        return ['subtotal' => $sub, 'discount' => $discount, 'charge' => $charge, 'dpp' => $dpp, 'tax' => $tax, 'total' => $total, 'dp' => $dp,
            'balance' => round($total - $dp - $cash - (float) $inv->pph_amount, 2)];
    }

    // Usulan potongan: uang muka order ini, paling lama dulu, paling banyak sebesar total invoice.
    public function proposeDownPayments(FinInvoice $inv): ?array
    {
        $left = $this->totals($inv)['total'];
        $plan = [];
        foreach ($this->downPaymentsAvailable($inv->co_id, $inv->invoice_id) as $id => $r) {
            $take = round(min($r['remaining'], $left), 2);
            if ($take > 0) {
                $plan[$id] = $take;
                $left = round($left - $take, 2);
            }
        }

        return $plan ?: null;
    }

    // Nilai tagihan uang muka (termasuk PPN). Order PPN + DP_VAT_ON_RECEIPT = 1: dipisah harga + PPN (PPN diakui saat uang
    // diterima). Selain itu seluruhnya uang muka tanpa PPN (cara lama: PPN seluruhnya di invoice akhir).
    public function setDownPaymentAmount(FinInvoice $inv, float $total): void
    {
        $total = round($total, 2);
        $rate = $inv->is_ppn && SysConstant::number('DP_VAT_ON_RECEIPT', 1) >= 1 ? (float) $inv->tax_rate : 0.0;
        $base = $rate > 0 ? round($total / (1 + $rate / 100), 2) : $total;
        $inv->fill(['subtotal' => $base, 'tax_amount' => round($total - $base, 2), 'total_amount' => $total]);
    }

    // ---------------------------------------------------------------- simpan Draft

    /**
     * $in: invoice_date, payment_term_code, company_bank_id, customer_po_no, bill_to_name, bill_to_address, notes, tax_invoice_no,
     *      charge_amount, charge_note, lines [id => [qty_invoiced]], remove [id => 1], dp [id tagihan DP => [apply]]   (invoice)
     *      description, dp_percent ATAU bill_amount                                                                      (uang muka)
     * Angka sudah berupa float.
     */
    public function saveDraft(FinInvoice $inv, array $in, string $user): FinInvoice
    {
        $this->fail($inv->doc_status !== 'DRAFT', t('This document is already Confirmed and cannot be changed.'));
        $text = fn (string $k, int $max) => array_key_exists($k, $in) ? (mb_substr(trim((string) $in[$k]), 0, $max) ?: null) : $inv->{$k};

        return DB::connection('tm2acc')->transaction(function () use ($inv, $in, $user, $text) {
            $inv->fill([
                'invoice_date' => trim((string) ($in['invoice_date'] ?? '')) ?: $inv->invoice_date, 'payment_term_code' => $text('payment_term_code', 50),
                'customer_po_no' => $text('customer_po_no', 100), 'bill_to_name' => $text('bill_to_name', 200), 'bill_to_address' => $text('bill_to_address', 2000),
                'notes' => $text('notes', 255), 'tax_invoice_no' => $text('tax_invoice_no', 50), 'updated_by' => $user,
            ]);
            if (! empty($in['company_bank_id'])) {
                $bank = FinCompanyBank::query()->where('activate', 1)->find((int) $in['company_bank_id']);
                $this->fail(! $bank || ! $bank->usableFor((bool) $inv->is_ppn), t('This bank account is not for this tax identity.'));
                $inv->company_bank_id = $bank->id_company_bank;
            }

            if ($inv->invoice_type === self::DOWN_PAYMENT) {
                $inv->description = $text('description', 255);
                $percent = array_key_exists('dp_percent', $in) && $in['dp_percent'] !== null && (float) $in['dp_percent'] > 0 ? (float) $in['dp_percent'] : null;
                $this->fail($percent !== null && $percent > 100, t('The down payment cannot be more than 100% of the order.'));
                $amount = $percent !== null ? round((float) $inv->order_amount * $percent / 100, 2) : round((float) ($in['bill_amount'] ?? $inv->total_amount), 2);
                $inv->dp_percent = $percent;
                $this->setDownPaymentAmount($inv, $amount);
                $inv->due_date = $this->dueDate($inv->invoice_date->toDateString(), $inv->payment_term_code);
                $inv->save();

                return $inv;
            }

            $inv->fill(['charge_amount' => max(0, round((float) ($in['charge_amount'] ?? $inv->charge_amount), 2)), 'charge_note' => $text('charge_note', 100)]);
            $lines = $inv->lines()->get()->keyBy('id_invoice_line');
            foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
                $lines->pull((int) $id)?->delete();
            }
            foreach ((array) ($in['lines'] ?? []) as $id => $row) {
                if (($line = $lines->get((int) $id)) && array_key_exists('qty_invoiced', (array) $row)) {
                    $line->update(['qty_invoiced' => max(0, round((float) $row['qty_invoiced'], 2)), 'updated_by' => $user]);
                }
            }
            $inv->setRelation('lines', $inv->lines()->get());
            $this->recalc($inv);

            // Potongan uang muka: yang diketik user, dibatasi sisa uang mukanya; tanpa kiriman = usulan otomatis dihitung ulang.
            if (array_key_exists('dp', $in)) {
                $available = $this->downPaymentsAvailable($inv->co_id, $inv->invoice_id);
                $plan = [];
                foreach ((array) $in['dp'] as $id => $row) {
                    $apply = round((float) (is_array($row) ? ($row['apply'] ?? 0) : $row), 2);
                    if ($apply > 0 && $available->has((int) $id)) {
                        $plan[(int) $id] = $apply;
                    }
                }
                $inv->dp_plan = $plan ?: null;
            } else {
                $inv->dp_plan = $this->proposeDownPayments($inv);
            }
            $inv->save();

            return $inv;
        });
    }

    /** Alasan Draft belum bisa di-Confirm (kosong = boleh). */
    public function confirmErrors(FinInvoice $inv): array
    {
        $errors = [];
        if (! $inv->company_bank_id) {
            $errors[] = t('Choose the bank account the customer transfers to.');
        }
        if ($inv->invoice_type === self::DOWN_PAYMENT) {
            if (! $inv->co_id) {
                $errors[] = t('A down payment must belong to a Customer Order.');
            }
            if ((float) $inv->total_amount <= 0) {
                $errors[] = t('The bill amount must be more than zero.');
            }
            $billed = (float) FinInvoice::withoutGlobalScopes()->where('invoice_type', self::DOWN_PAYMENT)->where('co_id', $inv->co_id)->where('doc_status', 'POSTED')
                ->where('invoice_id', '<>', $inv->invoice_id)->sum('total_amount');
            if ($inv->order_amount !== null && $billed + (float) $inv->total_amount > (float) $inv->order_amount + 0.005) {
                $errors[] = t('Down payments for this order would exceed the order value.');
            }

            return $errors;
        }
        $inv->load('lines');
        if ($inv->lines->isEmpty()) {
            $errors[] = t('Add at least one delivery note line.');
        }
        if (! $inv->co_id || $inv->lines->contains(fn (FinInvoiceLine $l) => ! $l->do_line_id)) {
            $errors[] = t('An invoice must come from delivery note lines of one Customer Order.');
        }
        $live = DoLine::query()->whereKey($inv->lines->pluck('do_line_id')->filter()->all())->get()->keyBy('id_line');
        foreach ($inv->lines as $l) {
            $do = $live->get($l->do_line_id);
            if ((float) $l->qty_invoiced <= 0) {
                $errors[] = tf('Line :line: the invoiced quantity must be more than zero.', ['line' => $l->line_no]);
            } elseif (! $do || $do->qty_received === null || (float) $l->qty_invoiced > $this->remaining($do) + 0.005) {
                $errors[] = tf('Line :line: only :qty is left to invoice on delivery note :no.', ['line' => $l->line_no, 'qty' => $do ? $this->remaining($do) : 0, 'no' => $l->delivery_no]);
            }
        }
        $t = $this->totals($inv);
        if ($t['total'] <= 0) {
            $errors[] = t('The invoice total must be more than zero.');
        }
        $available = $this->downPaymentsAvailable($inv->co_id, $inv->invoice_id);
        foreach ((array) $inv->dp_plan as $id => $apply) {
            if ((float) $apply > (float) ($available[(int) $id]['remaining'] ?? 0) + 0.005) {
                $errors[] = t('A down payment deduction is more than what is left of that down payment.');
            }
        }
        if ($t['dp'] > $t['total'] + 0.005) {
            $errors[] = t('The down payment deduction is more than the invoice total.');
        }

        return $errors;
    }

    // ---------------------------------------------------------------- Confirm / Cancel / Delete

    public function confirm(FinInvoice $inv, string $user): FinInvoice
    {
        $this->fail($inv->doc_status !== 'DRAFT', t('Only a Draft can be confirmed.'));
        $this->fail((bool) ($errors = $this->confirmErrors($inv)), implode(' ', $errors));
        $date = $inv->invoice_date->toDateString();
        $series = ($inv->invoice_type === self::DOWN_PAYMENT ? 'DP_' : 'INV_').($inv->is_ppn ? 'LJM' : 'TM');
        $stamp = ['doc_status' => 'POSTED', 'confirmed_by' => $user, 'confirmed_at' => now(), 'updated_by' => $user, 'dp_plan' => null];

        if ($inv->invoice_type === self::DOWN_PAYMENT) {
            // Tagihan uang muka: hanya nomor. Bukan piutang dan belum ada jurnal.
            return DB::connection('tm2acc')->transaction(function () use ($inv, $series, $date, $stamp) {
                $inv->update(['invoice_number' => DocSeries::next($series, $date, 'tm2acc')] + $stamp);

                return $inv;
            });
        }

        $this->guard->assertOpen($date);
        $customer = CustCustomer::withoutGlobalScopes()->findOrFail($inv->partner_id);

        return DB::connection('tm2acc')->transaction(function () use ($inv, $series, $date, $stamp, $customer, $user) {
            $this->recalc($inv);
            $t = $this->totals($inv);
            $number = DocSeries::next($series, $date, 'tm2acc');
            $plan = $this->splitPlan((array) $inv->dp_plan);
            $applied = round(array_sum(array_map(fn ($p) => $p['base'] + $p['tax'], $plan)), 2);

            $voucher = $this->journal->post('SAL', 'CUSTOMER_INVOICE', $inv->invoice_id, 'Invoice '.$number.' — '.$customer->customer_name,
                $this->journalLines($inv, $customer, $t, $plan, $number), $user, $date, [
                    'journal_kind' => 'SYSTEM', 'reference_no' => $number, 'partner_name' => $customer->customer_name, 'ppn_type' => $t['tax'] > 0 ? 'PPN_OUT' : null,
                    'efaktur_no' => $inv->tax_invoice_no, 'data_origin' => $inv->data_origin ?: 'LIVE',
                ]);
            foreach ($plan as $dpId => $p) {
                FinDpAllocation::create(['dp_invoice_id' => $dpId, 'final_invoice_id' => $inv->invoice_id, 'allocated_base' => $p['base'], 'allocated_tax' => $p['tax'],
                    'allocation_date' => $date, 'created_by' => $user, 'updated_by' => $user]);
            }
            // qty tertagih per baris Surat Jalan: lewat koneksi yang sama supaya ikut transaksi ini (satu server MySQL).
            foreach ($inv->lines as $l) {
                DB::connection('tm2acc')->table(DbName::table('tm2sdi.do_line'))->where('id_line', $l->do_line_id)->increment('qty_invoiced', (float) $l->qty_invoiced);
            }
            $inv->update(['invoice_number' => $number, 'voucher_id' => $voucher->voucher_id, 'paid_amount' => $applied, 'dp_applied_amount' => $applied,
                'payment_status' => $applied + 0.005 >= $t['total'] ? 'PAID' : ($applied > 0 ? 'PARTIAL' : 'UNPAID')] + $stamp);

            return $inv;
        });
    }

    // [id tagihan DP => nilai] -> [id => [base, tax]]: bagian PPN mengikuti perbandingan PPN pada tagihan uang mukanya.
    private function splitPlan(array $plan): array
    {
        $dps = FinInvoice::withoutGlobalScopes()->whereKey(array_keys($plan))->get()->keyBy('invoice_id');
        $out = [];
        foreach ($plan as $id => $apply) {
            $dp = $dps->get((int) $id);
            $apply = round((float) $apply, 2);
            if (! $dp || $apply <= 0) {
                continue;
            }
            $tax = (float) $dp->total_amount > 0 ? round($apply * (float) $dp->tax_amount / (float) $dp->total_amount, 2) : 0.0;
            $out[(int) $id] = ['base' => round($apply - $tax, 2), 'tax' => $tax, 'number' => $dp->invoice_number];
        }

        return $out;
    }

    /** Baris jurnal saat Confirm (juga dipakai pratinjau di form): piutang, penjualan, PPN, lalu potongan uang muka. */
    public function journalLines(FinInvoice $inv, CustCustomer $customer, array $t, array $plan, ?string $number = null): array
    {
        $ar = $this->arap->arAccountFor($customer);
        $label = $number ?: 'Draft #'.$inv->invoice_id;
        $partner = ['partner_type' => 'CUSTOMER', 'partner_id' => $customer->id_customer, 'co_id' => $inv->co_id];
        $lines = [
            ['account_id' => $ar, 'debit' => $t['total'], 'narration' => 'Piutang '.$label] + $partner,
            ['account_id' => $inv->revenue_account_id ?: $this->arap->accountId('411000'), 'credit' => $t['dpp'], 'co_id' => $inv->co_id, 'narration' => 'Penjualan '.$label],
        ];
        if ($t['tax'] > 0) {
            $lines[] = ['account_id' => $this->arap->accountId('212100'), 'credit' => $t['tax'], 'narration' => 'PPN Keluaran '.$label];
        }
        foreach ($plan as $p) {
            $lines[] = ['account_id' => $this->arap->accountId('213200'), 'debit' => $p['base'], 'narration' => 'Uang muka '.$p['number'].' dipotong'] + $partner;
            if ($p['tax'] > 0) {
                $lines[] = ['account_id' => $this->arap->accountId('212100'), 'debit' => $p['tax'], 'narration' => 'PPN uang muka '.$p['number']];
            }
            $lines[] = ['account_id' => $ar, 'credit' => round($p['base'] + $p['tax'], 2), 'narration' => 'Uang muka '.$p['number'].' ke '.$label] + $partner;
        }

        return $lines;
    }

    /** Pratinjau jurnal Draft invoice (tanpa menulis apa pun). */
    public function journalPreview(FinInvoice $inv): array
    {
        $customer = CustCustomer::withoutGlobalScopes()->find($inv->partner_id);

        return $customer && $inv->invoice_type === self::INVOICE ? $this->journalLines($inv, $customer, $this->totals($inv), $this->splitPlan((array) $inv->dp_plan)) : [];
    }

    public function cancel(FinInvoice $inv, string $reason, string $user): FinInvoice
    {
        $this->fail($inv->doc_status !== 'POSTED', t('Only a Confirmed document can be cancelled.'));
        $this->fail(trim($reason) === '', t('Fill in the reason for cancelling.'));
        $stamp = ['doc_status' => 'CANCELLED', 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'cancelled_by' => $user, 'cancelled_at' => now(), 'updated_by' => $user];

        if ($inv->invoice_type === self::DOWN_PAYMENT) {
            $this->fail((float) $inv->paid_amount > 0, t('This down payment has been received. Refunding a down payment is not available yet.'));
            $inv->update($stamp);

            return $inv;
        }

        $cash = round((float) $inv->paid_amount - (float) $inv->dp_applied_amount, 2);
        $this->fail($cash > 0.005 || FinPaymentAlloc::query()->where('invoice_id', $inv->invoice_id)->exists(), t('This invoice already has a payment. Correct it with a credit note instead.'));
        $date = $inv->invoice_date->toDateString();
        $this->guard->assertOpen($date);

        return DB::connection('tm2acc')->transaction(function () use ($inv, $stamp, $date, $user, $reason) {
            if ($voucher = FinVoucher::withoutGlobalScopes()->find($inv->voucher_id)) {
                $this->journal->reverse($voucher, $date, $user, 'Cancel '.$inv->invoice_number.': '.$reason);
            }
            FinDpAllocation::query()->where('final_invoice_id', $inv->invoice_id)->delete();
            foreach ($inv->lines()->get() as $l) {
                DB::connection('tm2acc')->table(DbName::table('tm2sdi.do_line'))->where('id_line', $l->do_line_id)->decrement('qty_invoiced', (float) $l->qty_invoiced);
            }
            $inv->update(['paid_amount' => 0, 'dp_applied_amount' => 0, 'payment_status' => 'UNPAID'] + $stamp);

            return $inv;
        });
    }

    public function delete(FinInvoice $inv): void
    {
        $this->fail($inv->doc_status !== 'DRAFT', t('Only a Draft can be deleted.'));
        $inv->delete();
    }

    // ---------------------------------------------------------------- uang muka diterima

    /**
     * Uang muka diterima: bukti bank / kas masuk + jurnal Bank pada Uang Muka Penjualan (+ PPN Keluaran untuk bagian PPN).
     * Tagihan uang muka lama yang sudah menjurnal piutang saat terbit (voucher_id terisi) ditutup ke Piutang.
     */
    public function receiveDownPayment(FinInvoice $dp, string $date, int $bankId, float $amount, ?string $taxInvoiceNo, string $user): FinVoucher
    {
        $this->fail($dp->invoice_type !== self::DOWN_PAYMENT || $dp->doc_status !== 'POSTED', t('Only a Confirmed down payment invoice can receive money.'));
        $amount = round($amount, 2);
        $left = round((float) $dp->total_amount - (float) $dp->paid_amount, 2);
        $this->fail($amount <= 0, t('The amount received must be more than zero.'));
        $this->fail($amount > $left + 0.005, t('The amount received is more than what is left of this down payment invoice.'));
        $bank = FinCompanyBank::query()->where('activate', 1)->find($bankId);
        $this->fail(! $bank, t('Choose the bank account that received the money.'));
        $this->fail(! ($bankAccount = $bank->glAccountId()), t('This bank account has no ledger account yet (Finance > Company Bank Accounts).'));
        $this->guard->assertOpen($date);
        $customer = CustCustomer::withoutGlobalScopes()->findOrFail($dp->partner_id);
        $partner = ['partner_type' => 'CUSTOMER', 'partner_id' => $customer->id_customer, 'co_id' => $dp->co_id];

        $lines = [['account_id' => $bankAccount, 'debit' => $amount, 'narration' => 'Terima uang muka '.$dp->invoice_number]];
        if ($dp->voucher_id) {
            $lines[] = ['account_id' => $this->arap->arAccountFor($customer), 'credit' => $amount, 'narration' => 'Tutup piutang uang muka '.$dp->invoice_number] + $partner;
            $tax = 0.0;
        } else {
            $tax = (float) $dp->total_amount > 0 ? round($amount * (float) $dp->tax_amount / (float) $dp->total_amount, 2) : 0.0;
            $lines[] = ['account_id' => $this->arap->accountId('213200'), 'credit' => round($amount - $tax, 2), 'narration' => 'Uang muka '.$dp->invoice_number] + $partner;
            if ($tax > 0) {
                $lines[] = ['account_id' => $this->arap->accountId('212100'), 'credit' => $tax, 'narration' => 'PPN Keluaran uang muka '.$dp->invoice_number];
            }
        }
        $cash = str_starts_with((string) $bank->gl_account_code, '1111');
        $type = $cash ? 'BKM' : 'BBM';

        return DB::connection('tm2acc')->transaction(function () use ($dp, $date, $amount, $lines, $user, $customer, $bankAccount, $cash, $type, $tax, $taxInvoiceNo) {
            $voucher = $this->journal->post($cash ? 'CSH' : 'BNK', 'CUSTOMER_DP_PAYMENT', $dp->invoice_id, 'Uang muka '.$dp->invoice_number.' — '.$customer->customer_name,
                $lines, $user, $date, [
                    'voucher_number' => $this->journal->nextInSeries($type.'/'.date('Y/m', strtotime($date)).'/', 3), 'voucher_type' => $type, 'journal_kind' => 'SYSTEM',
                    'main_account_id' => $bankAccount, 'reference_no' => $dp->invoice_number, 'partner_name' => $customer->customer_name,
                    'ppn_type' => $tax > 0 ? 'PPN_OUT' : null, 'efaktur_no' => $taxInvoiceNo ?: $dp->tax_invoice_no, 'data_origin' => $dp->data_origin ?: 'LIVE',
                ]);
            $paid = round((float) $dp->paid_amount + $amount, 2);
            $dp->update(['paid_amount' => $paid, 'payment_status' => $paid + 0.005 >= (float) $dp->total_amount ? 'PAID' : 'PARTIAL',
                'tax_invoice_no' => $taxInvoiceNo ?: $dp->tax_invoice_no, 'tax_validated' => (bool) ($taxInvoiceNo ?: $dp->tax_invoice_no), 'updated_by' => $user]);

            return $voucher;
        });
    }

    // ---------------------------------------------------------------- pelunasan diterima

    /** Invoice Confirmed satu customer (identitas pajak yang sama) yang masih punya sisa tagihan, urut jatuh tempo. */
    public function openInvoices(int $customerId, bool $ppn): Collection
    {
        return FinInvoice::withoutGlobalScopes()->where('invoice_type', self::INVOICE)->where('partner_id', $customerId)->where('doc_status', 'POSTED')
            ->where('payment_status', '<>', 'PAID')->where('is_ppn', $ppn)->orderBy('due_date')->orderBy('invoice_id')->get();
    }

    /**
     * Pelunasan diterima: satu penerimaan boleh melunasi beberapa invoice customer yang sama, boleh sebagian.
     * $allocations: [id invoice => nilai]. Bukti bank / kas masuk + jurnal Bank pada Piutang lewat ArApService::settle
     * (alokasi tidak boleh melebihi sisa invoice).
     */
    public function receivePayment(int $customerId, string $date, int $bankId, array $allocations, string $user, string $note = ''): FinVoucher
    {
        $allocations = array_filter(array_map(fn ($v) => round((float) $v, 2), $allocations), fn ($v) => $v > 0);
        $this->fail(! $allocations, t('Fill in the amount paid on at least one invoice.'));
        $invoices = FinInvoice::withoutGlobalScopes()->whereKey(array_keys($allocations))->get();
        $this->fail($invoices->count() !== count($allocations) || $invoices->contains(fn (FinInvoice $i) => $i->invoice_type !== self::INVOICE || $i->doc_status !== 'POSTED'
            || (int) $i->partner_id !== $customerId), t('Only Confirmed invoices of this customer can receive a payment.'));
        $this->fail($invoices->pluck('is_ppn')->unique()->count() > 1, t('One receipt cannot mix invoices of the two tax identities.'));
        foreach ($invoices as $i) {
            $this->fail($allocations[$i->invoice_id] > $i->outstanding() + 0.005, tf('The amount for :no is more than what is left to pay.', ['no' => $i->invoice_number]));
        }
        $bank = FinCompanyBank::query()->where('activate', 1)->find($bankId);
        $this->fail(! $bank, t('Choose the bank account that received the money.'));
        $this->fail(! $bank->usableFor((bool) $invoices->first()->is_ppn), t('This bank account is not for this tax identity.'));
        $this->fail(! ($bankAccount = $bank->glAccountId()), t('This bank account has no ledger account yet (Finance > Company Bank Accounts).'));

        return $this->arap->settle('RECEIPT', $bankAccount, $date, $allocations, $user, $note);
    }

    // ---------------------------------------------------------------- jejak: Surat Jalan -> customer terima -> invoice; uang muka -> invoice

    /**
     * Semua baris Surat Jalan customer satu order beserta bukti terimanya dan invoice yang menagihnya (user 2026-10-02:
     * tiap tagihan harus berasal dari Surat Jalan yang divalidasi customer). Per baris: line, pod (kedatangan terakhir),
     * invoices (nomor invoice / Draft yang masih berlaku), state.
     */
    public function deliveryChain(int $coId, ?array $doLineIds = null): Collection
    {
        $lines = DoLine::query()->with(['delivery', 'orderItem'])->when($doLineIds !== null, fn ($q) => $q->whereKey($doLineIds), fn ($q) => $q->where('co_order_id', $coId))
            ->whereHas('delivery', fn ($q) => $q->where('do_type', '<>', 'SUBCON')->whereNotIn('status', ['DRAFT', 'CANCELLED']))
            ->orderBy('delivery_id')->orderBy('line_no')->get();
        $pods = DoDeliveryPod::query()->whereIn('delivery_id', $lines->pluck('delivery_id')->unique())->orderBy('attempt_no')->get()->groupBy('delivery_id');
        $billed = FinInvoiceLine::query()->with(['invoice' => fn ($q) => $q->withoutGlobalScopes()])->whereIn('do_line_id', $lines->modelKeys())->get()
            ->filter(fn (FinInvoiceLine $l) => $l->invoice && $l->invoice->doc_status !== 'CANCELLED')->groupBy('do_line_id');

        return $lines->map(fn (DoLine $l) => ['line' => $l, 'pod' => $pods->get($l->delivery_id)?->last(),
            'invoices' => ($billed->get($l->id_line) ?? collect())->map(fn (FinInvoiceLine $b) => $b->invoice->invoice_number ?: 'Draft #'.$b->invoice_id)->unique()->values()->all(),
            'state' => match (true) {
                $l->qty_received === null => 'Waiting proof of delivery',
                $this->remaining($l) <= 0 => 'Fully invoiced',
                (float) $l->qty_invoiced > 0 => 'Partly invoiced',
                default => 'Ready to invoice',
            }]);
    }

    /** Nilai barang (sebelum diskon dokumen dan pajak) sejumlah $qty pada satu baris Surat Jalan; baris borongan = harganya. */
    public function goodsValue(DoLine $l, float $qty): float
    {
        return $qty <= 0 ? 0.0 : round($l->orderItem?->qty_minim ? (float) $l->unit_price : $qty * (float) $l->unit_price, 2);
    }

    /**
     * Buku uang muka satu Customer Order, urut tanggal: ditagih -> uang diterima -> dipotong di invoice, dengan saldo
     * berjalan (uang diterima yang belum dipotong). rows[] = date, event, dp_no, doc_no, invoice_id, billed, in, out, balance.
     */
    public function downPaymentLedger(int $coId): array
    {
        $dps = FinInvoice::withoutGlobalScopes()->where('invoice_type', self::DOWN_PAYMENT)->where('co_id', $coId)->where('doc_status', 'POSTED')
            ->orderBy('invoice_date')->orderBy('invoice_id')->get()->keyBy('invoice_id');
        $ids = $dps->keys()->all();
        $row = fn (FinInvoice $dp, ?string $date, int $order, string $event, ?string $doc, int $open, float $billed, float $in, float $out) => [
            'date' => $date, 'order' => $order, 'event' => $event, 'dp_id' => $dp->invoice_id, 'dp_no' => $dp->invoice_number, 'doc_no' => $doc, 'invoice_id' => $open,
            'billed' => round($billed, 2), 'in' => round($in, 2), 'out' => round($out, 2)];
        $rows = $dps->map(fn (FinInvoice $dp) => $row($dp, $dp->invoice_date?->toDateString(), 1, 'Down payment invoiced', $dp->invoice_number, $dp->invoice_id, (float) $dp->total_amount, 0, 0))->values()->all();
        // Uang diterima: bukti bank masuk Record Receipt (cara baru) + alokasi pembayaran (tagihan uang muka cara lama).
        foreach (FinVoucher::withoutGlobalScopes()->with('lines')->where('source_type', 'CUSTOMER_DP_PAYMENT')->whereIn('source_id', $ids)->where('stat_cancel', 0)->get() as $v) {
            $rows[] = $row($dps[$v->source_id], substr((string) $v->entry_date, 0, 10), 2, 'Money received', $v->voucher_number, (int) $v->source_id, 0, (float) $v->lines->sum('debit'), 0);
        }
        foreach (FinPaymentAlloc::query()->with('voucher')->whereIn('invoice_id', $ids)->get() as $a) {
            $rows[] = $row($dps[$a->invoice_id], substr((string) $a->alloc_date, 0, 10), 2, 'Money received', $a->voucher?->voucher_number, (int) $a->invoice_id, 0, (float) $a->amount, 0);
        }
        foreach (FinDpAllocation::query()->with(['finalInvoice' => fn ($q) => $q->withoutGlobalScopes()])->whereIn('dp_invoice_id', $ids)->get() as $a) {
            $rows[] = $row($dps[$a->dp_invoice_id], $a->allocation_date?->toDateString(), 3, 'Applied to invoice', $a->finalInvoice?->invoice_number, (int) $a->final_invoice_id, 0, 0,
                (float) $a->allocated_base + (float) $a->allocated_tax);
        }
        usort($rows, fn ($a, $b) => [$a['date'], $a['order'], $a['dp_id']] <=> [$b['date'], $b['order'], $b['dp_id']]);
        $balance = 0.0;
        foreach ($rows as &$r) {
            $r['balance'] = $balance = round($balance + $r['in'] - $r['out'], 2);
        }
        unset($r);

        return ['rows' => $rows, 'billed' => round(array_sum(array_column($rows, 'billed')), 2), 'received' => round(array_sum(array_column($rows, 'in')), 2),
            'applied' => round(array_sum(array_column($rows, 'out')), 2), 'balance' => $balance];
    }

    /** Ringkasan penagihan satu order: rantai Surat Jalan, dokumen (invoice + uang muka), buku uang muka, dan angka-angkanya. */
    public function orderBilling(CoOrder $co): array
    {
        $chain = $this->deliveryChain($co->id_order);
        $docs = FinInvoice::withoutGlobalScopes()->whereIn('invoice_type', [self::INVOICE, self::DOWN_PAYMENT])->where('co_id', $co->id_order)->orderBy('invoice_date')->orderBy('invoice_id')->get();
        $invoices = $docs->where('invoice_type', self::INVOICE)->where('doc_status', 'POSTED');
        $dp = $this->downPaymentLedger($co->id_order);
        $co->loadMissing(['items', 'externalCosts']);

        return ['chain' => $chain, 'documents' => $docs, 'dp' => $dp,
            'order_value' => round((float) $co->totals()['total_billing'], 2),
            'waiting_value' => round($chain->where('state', 'Waiting proof of delivery')->sum(fn ($r) => $this->goodsValue($r['line'], (float) $r['line']->qty_do)), 2),
            'ready_value' => round($chain->whereIn('state', ['Ready to invoice', 'Partly invoiced'])->sum(fn ($r) => $this->goodsValue($r['line'], $this->remaining($r['line']))), 2),
            'invoiced' => round((float) $invoices->sum('total_amount'), 2),
            'paid' => round((float) $invoices->sum(fn (FinInvoice $i) => (float) $i->paid_amount - (float) $i->dp_applied_amount), 2),
            'outstanding' => round((float) $invoices->sum(fn (FinInvoice $i) => $i->outstanding()), 2)];
    }

    // ---------------------------------------------------------------- bantu

    private function pct(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    private function fail(bool $when, string $message): void
    {
        if ($when) {
            throw new DomainException($message);
        }
    }
}
