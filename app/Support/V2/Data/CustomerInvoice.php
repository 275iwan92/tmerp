<?php

namespace App\Support\V2\Data;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinCompanyBank;
use App\Models\Acc\FinDpAllocation;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinInvoiceLine;
use App\Models\Acc\FinPaymentAlloc;
use App\Models\Acc\FinVoucher;
use App\Models\Master\CustCustomer;
use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\DoLine;
use App\Services\Acc\CustomerInvoiceService as Service;
use App\Services\Sales\DeliveryPromiseService as Promise;
use Devlib\Locale;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Customer Invoice v2 (user 2026-10-02): penghubung layar ke App\Services\Acc\CustomerInvoiceService -- daftar, pilihan
// order / Surat Jalan, isi form, dan pemetaan kiriman form. Aturan dan jurnalnya ada di service, bukan di sini.
final class CustomerInvoice
{
    public const TYPES = ['INVOICE' => Service::INVOICE, 'DOWN_PAYMENT' => Service::DOWN_PAYMENT];

    public const STATE_TONES = ['Draft' => 'neutral', 'Open' => 'info', 'Partially paid' => 'info', 'Due this week' => 'warn', 'Overdue' => 'danger', 'Paid' => 'success',
        'Cancelled' => 'danger', 'Waiting payment' => 'warn', 'Received' => 'success'];

    public static function service(): Service
    {
        return app(Service::class);
    }

    public static function find(int $id): ?FinInvoice
    {
        return FinInvoice::query()->with(['lines', 'companyBank'])->whereIn('invoice_type', self::TYPES)->find($id);
    }

    // ---------------------------------------------------------------- daftar

    public static function rows(): array
    {
        $list = FinInvoice::query()->with('lines')->whereIn('invoice_type', self::TYPES)->orderByDesc('invoice_id')->limit(500)->get();
        $customers = CustCustomer::withoutGlobalScopes()->whereIn('id_customer', $list->pluck('partner_id')->unique())->pluck('customer_name', 'id_customer');
        $orders = CoOrder::query()->whereKey($list->pluck('co_id')->filter()->unique()->all())->pluck('co_no', 'id_order');

        return $list->map(function (FinInvoice $i) use ($customers, $orders) {
            $dp = $i->invoice_type === Service::DOWN_PAYMENT;
            $state = self::state($i);
            $open = $i->doc_status === 'POSTED' && $i->payment_status !== 'PAID';

            return ['id' => $i->invoice_id, 'no' => $i->invoice_number ?: 'Draft #'.$i->invoice_id, 'customer' => $customers[$i->partner_id] ?? '—',
                'kind' => $dp ? 'Down Payment' : 'Invoice', 'date' => $i->invoice_date?->toDateString(), 'due' => $i->due_date?->toDateString(), 'co_no' => $orders[$i->co_id] ?? null,
                'total' => (float) $i->total_amount, 'dp' => (float) $i->dp_applied_amount ?: null, 'outstanding' => $open ? $i->outstanding() : null,
                'state' => $state, 'tone' => $state === 'Overdue' ? 'danger' : ($i->doc_status === 'CANCELLED' ? 'muted' : null),
                'items' => $dp
                    ? [['product' => $i->description, 'dn' => null, 'received' => null, 'invoiced' => null, 'unit' => null, 'price' => null, 'amount' => (float) $i->total_amount]]
                    : ($i->lines->isEmpty()
                        ? [['product' => $i->description ?: '—', 'dn' => null, 'received' => null, 'invoiced' => null, 'unit' => null, 'price' => null, 'amount' => (float) $i->subtotal]]
                        : $i->lines->map(fn (FinInvoiceLine $l) => ['product' => $l->description, 'dn' => $l->delivery_no, 'received' => (float) $l->qty_received,
                            'invoiced' => (float) $l->qty_invoiced, 'unit' => $l->qty_unit, 'price' => (float) $l->unit_price, 'amount' => (float) $l->line_total])->all())];
        })->all();
    }

    // Status tagih satu dokumen (teks grid dan form).
    public static function state(FinInvoice $i): string
    {
        return match (true) {
            $i->doc_status === 'CANCELLED' => 'Cancelled',
            $i->doc_status !== 'POSTED' => 'Draft',
            $i->invoice_type === Service::DOWN_PAYMENT => $i->payment_status === 'PAID' ? 'Received' : 'Waiting payment',
            $i->payment_status === 'PAID' => 'Paid',
            $i->due_date && $i->due_date->lt(today()) => 'Overdue',
            $i->due_date && $i->due_date->lte(today()->addDays(7)) => 'Due this week',
            default => $i->payment_status === 'PARTIAL' ? 'Partially paid' : 'Open',
        };
    }

    // Status dokumen untuk panel form DevLib: draft | confirmed | cancelled | closed (lunas).
    public static function formStatus(FinInvoice $i): string
    {
        return match (true) {
            $i->doc_status === 'CANCELLED' => 'cancelled',
            $i->doc_status !== 'POSTED' => 'draft',
            $i->invoice_type === Service::INVOICE && $i->payment_status === 'PAID' => 'closed',
            default => 'confirmed',
        };
    }

    // ---------------------------------------------------------------- + New Record

    /** Order yang bisa dipilih: Invoice = punya baris Surat Jalan diterima yang belum ditagih; Down Payment = order Confirmed. */
    public static function orderOptions(string $type): array
    {
        $q = CoOrder::query()->where('stat_cancel', 0)->orderByDesc('id_order')->limit(300);
        if ($type === 'INVOICE') {
            $ids = DoLine::query()->whereNotNull('qty_received')->whereColumn('qty_received', '>', 'qty_invoiced')->whereNotNull('co_order_id')
                ->whereHas('delivery', fn ($d) => $d->where('do_type', '<>', 'SUBCON')->whereNotIn('status', ['DRAFT', 'CANCELLED']))->distinct()->pluck('co_order_id');
            $q->whereKey($ids->all());
        } else {
            $q->where('doc_status', 'CONFIRMED');
        }
        $orders = $q->get(['id_order', 'co_no', 'customer_id', 'is_ppn', 'customer_po_no']);
        $customers = CustCustomer::withoutGlobalScopes()->whereIn('id_customer', $orders->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');

        return $orders->mapWithKeys(fn (CoOrder $o) => [$o->id_order => implode(' · ', array_filter([$customers[$o->customer_id] ?? '—', $o->co_no, $o->is_ppn ? 'PPN' : 'Non PPN', $o->customer_po_no]))])->all();
    }

    /** Baris Surat Jalan satu order yang bisa ditagih (untuk dicentang sebelum Draft dibuat). */
    public static function billableRows(int $coId): array
    {
        return self::service()->billable($coId)->map(fn (DoLine $l) => ['id_line' => $l->id_line, 'dn' => $l->delivery->do_no, 'date' => Locale::d($l->delivery->do_date),
            'product' => $l->item_description, 'shipped' => Locale::number($l->qty_do, 2), 'received' => Locale::number($l->qty_received, 2), 'invoiced' => Locale::number($l->qty_invoiced, 2),
            'reason' => $l->receive_diff_reason ? t(Promise::DIFF_REASONS[$l->receive_diff_reason] ?? $l->receive_diff_reason) : '',
            'remaining' => Locale::number(self::service()->remaining($l), 2), 'unit' => $l->qty_unit, 'pick' => true])->all();
    }

    public static function create(string $type, int $coId, array $doLineIds = []): FinInvoice
    {
        $co = CoOrder::query()->findOrFail($coId);
        $user = Auth::user()->login;

        return $type === 'DOWN_PAYMENT' ? self::service()->createDownPayment($co, $user) : self::service()->createInvoice($co, $doLineIds, $user);
    }

    // ---------------------------------------------------------------- form

    public static function termOptions(FinInvoice $i): array
    {
        $terms = FinPaymentTerm::query()->where('activate', 1)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all();

        return $i->payment_term_code && ! isset($terms[$i->payment_term_code]) ? [$i->payment_term_code => $i->payment_term_code] + $terms : $terms;
    }

    public static function bankOptions(FinInvoice $i, bool $anyIdentity = false): array
    {
        return FinCompanyBank::query()->where('activate', 1)->orderByDesc('is_default')->orderBy('bank_name')->get()
            ->filter(fn (FinCompanyBank $b) => $anyIdentity || $b->usableFor((bool) $i->is_ppn))->mapWithKeys(fn (FinCompanyBank $b) => [$b->id_company_bank => $b->label()])->all();
    }

    public static function lineRows(FinInvoice $i): array
    {
        $live = $i->doc_status === 'DRAFT' ? DoLine::query()->whereKey($i->lines->pluck('do_line_id')->filter()->all())->get()->keyBy('id_line') : collect();

        return $i->lines->map(fn (FinInvoiceLine $l) => ['id_invoice_line' => $l->id_invoice_line, 'description' => $l->description.($l->is_lump_sum ? ' ('.t('lump sum').')' : ''),
            'color_config' => $l->color_config, 'order_type' => $l->order_type, 'delivery_no' => $l->delivery_no, 'qty_received' => Locale::number($l->qty_received, 2),
            'invoiced_before' => $i->doc_status === 'DRAFT' ? Locale::number($live->get($l->do_line_id)?->qty_invoiced ?? 0, 2) : '', 'qty_invoiced' => (float) $l->qty_invoiced, 'qty_unit' => $l->qty_unit,
            'unit_price' => Locale::number($l->unit_price, 2), 'line_total' => Locale::number($l->line_total, 2)])->values()->all();
    }

    /** Uang muka order ini: Draft = yang tersedia + usulan potongan; sesudah Confirm = yang benar-benar dipotong. */
    public static function downPaymentRows(FinInvoice $i): array
    {
        if ($i->doc_status !== 'DRAFT') {
            return FinDpAllocation::query()->with('dpInvoice')->where('final_invoice_id', $i->invoice_id)->get()->map(fn (FinDpAllocation $a) => [
                'dp_id' => $a->dp_invoice_id, 'no' => $a->dpInvoice?->invoice_number, 'date' => Locale::d($a->dpInvoice?->invoice_date),
                'received' => Locale::number($a->dpInvoice?->paid_amount, 2), 'used' => null, 'remaining' => null, 'apply' => round((float) $a->allocated_base + (float) $a->allocated_tax, 2)])->all();
        }
        $plan = (array) $i->dp_plan;

        return self::service()->downPaymentsAvailable($i->co_id, $i->invoice_id)->map(fn (array $r, $id) => ['dp_id' => $id, 'no' => $r['dp']->invoice_number,
            'date' => Locale::d($r['dp']->invoice_date), 'received' => Locale::number($r['dp']->paid_amount, 2), 'used' => Locale::number($r['used'], 2), 'remaining' => Locale::number($r['remaining'], 2),
            'apply' => (float) ($plan[$id] ?? 0)])->values()->all();
    }

    /** Jurnal: Draft = pratinjau; sesudah Confirm = baris voucher yang terposting. */
    public static function journalRows(FinInvoice $i): array
    {
        if ($i->doc_status === 'DRAFT') {
            $lines = collect(self::service()->journalPreview($i));
        } else {
            $vouchers = FinVoucher::withoutGlobalScopes()->with('lines')->where(fn ($q) => $q->whereKey($i->voucher_id ?: 0)
                ->orWhere(fn ($w) => $w->where('source_type', 'CUSTOMER_DP_PAYMENT')->where('source_id', $i->invoice_id)))->orderBy('voucher_id')->get();
            $lines = $vouchers->flatMap(fn (FinVoucher $v) => $v->lines->map(fn ($l) => ['voucher' => $v->voucher_number, 'account_id' => $l->account_id,
                'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'narration' => $l->narration]));
        }
        $accounts = FinAccount::withoutGlobalScopes()->whereKey($lines->pluck('account_id')->unique()->all())->get()->keyBy('account_id');

        return $lines->map(fn (array $l) => ['voucher' => $l['voucher'] ?? '('.t('on Confirm').')', 'account' => trim(($accounts->get($l['account_id'])?->account_code ?? '').' '.($accounts->get($l['account_id'])?->account_name ?? '')),
            'debit' => (float) ($l['debit'] ?? 0) ? Locale::number($l['debit'], 2) : '', 'credit' => (float) ($l['credit'] ?? 0) ? Locale::number($l['credit'], 2) : '',
            'narration' => $l['narration'] ?? ''])->values()->all();
    }

    /** Uang yang sudah masuk untuk dokumen ini: alokasi pembayaran + (uang muka) penerimaan dan pemakaiannya. */
    public static function paymentRows(FinInvoice $i): array
    {
        $rows = FinPaymentAlloc::query()->with('voucher')->where('invoice_id', $i->invoice_id)->orderBy('alloc_id')->get()
            ->map(fn (FinPaymentAlloc $a) => ['what' => t('Payment'), 'no' => $a->voucher?->voucher_number, 'date' => Locale::d($a->alloc_date) ?? '—', 'amount' => Locale::number($a->amount, 2)])->all();
        if ($i->invoice_type === Service::DOWN_PAYMENT) {
            foreach (FinVoucher::withoutGlobalScopes()->where('source_type', 'CUSTOMER_DP_PAYMENT')->where('source_id', $i->invoice_id)->where('stat_cancel', 0)->with('lines')->get() as $v) {
                $rows[] = ['what' => t('Down payment received'), 'no' => $v->voucher_number, 'date' => Locale::d($v->entry_date) ?? '—', 'amount' => Locale::number($v->lines->sum('debit'), 2)];
            }
            foreach (FinDpAllocation::query()->with('finalInvoice')->where('dp_invoice_id', $i->invoice_id)->get() as $a) {
                $rows[] = ['what' => t('Applied to invoice'), 'no' => $a->finalInvoice?->invoice_number, 'date' => Locale::d($a->allocation_date) ?? '—',
                    'amount' => '('.Locale::number((float) $a->allocated_base + (float) $a->allocated_tax, 2).')'];
            }
        } else {
            foreach (self::downPaymentRows($i) as $d) {
                if ($i->doc_status !== 'DRAFT') {
                    $rows[] = ['what' => t('Down payment applied'), 'no' => $d['no'], 'date' => $d['date'] ?? '—', 'amount' => Locale::number($d['apply'], 2)];
                }
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------- jejak Surat Jalan dan uang muka

    private static function chainRow(array $r): array
    {
        $l = $r['line'];
        $diff = $l->qty_received === null ? null : round((float) $l->qty_do - (float) $l->qty_received, 2);

        return ['delivery_id' => $l->delivery_id, 'dn' => $l->delivery->do_no, 'date' => Locale::d($l->delivery->do_date) ?? '—', 'product' => $l->item_description,
            'shipped' => Locale::number($l->qty_do, 2), 'received' => $l->qty_received === null ? '—' : Locale::number($l->qty_received, 2),
            'diff' => $diff ? Locale::number($diff, 2) : '', 'reason' => $l->receive_diff_reason ? t(Promise::DIFF_REASONS[$l->receive_diff_reason] ?? $l->receive_diff_reason) : '',
            'note' => $l->receive_note ?? '', 'invoiced' => Locale::number($l->qty_invoiced, 2),
            'to_invoice' => $l->qty_received === null ? '' : Locale::number(self::service()->remaining($l), 2), 'unit' => $l->qty_unit,
            'arrived' => Locale::dt($r['pod']?->arrived_at) ?? '—', 'received_by' => $r['pod']?->received_by ?? '—', 'photo' => $r['pod']?->photo_path ? t('Photo') : t('No photo'),
            'invoices' => implode(', ', $r['invoices']), 'state' => t($r['state'])];
    }

    /** Bukti di balik satu invoice: baris Surat Jalan yang ditagih + apa yang divalidasi customer (Proof of Delivery). */
    public static function deliveryRows(FinInvoice $i): array
    {
        $ids = $i->lines->pluck('do_line_id')->filter()->all();

        return $ids ? self::service()->deliveryChain((int) $i->co_id, $ids)->map(fn (array $r) => self::chainRow($r))->values()->all() : [];
    }

    private static function ledgerRows(array $ledger): array
    {
        return array_map(fn (array $r) => ['date' => Locale::d($r['date']) ?? '—', 'event' => t($r['event']), 'dp_no' => $r['dp_no'], 'doc_no' => $r['doc_no'] ?? '—',
            'billed' => $r['billed'] ? Locale::number($r['billed'], 2) : '', 'in' => $r['in'] ? Locale::number($r['in'], 2) : '',
            'out' => $r['out'] ? '('.Locale::number($r['out'], 2).')' : '', 'balance' => Locale::number($r['balance'], 2)], $ledger['rows']);
    }

    /** Buku uang muka order ini (semua tagihan uang muka order yang sama), urut tanggal dengan saldo berjalan. */
    public static function downPaymentHistory(?int $coId): array
    {
        return $coId ? self::ledgerRows(self::service()->downPaymentLedger($coId)) : [];
    }

    /** Uang muka dokumen ini: sudah dipotong di invoice mana saja dan berapa sisanya (uang diterima yang belum dipotong). */
    public static function downPaymentUse(FinInvoice $dp): array
    {
        $applied = round((float) FinDpAllocation::query()->where('dp_invoice_id', $dp->invoice_id)->selectRaw('COALESCE(SUM(allocated_base + allocated_tax), 0) used')->value('used'), 2);

        return ['applied' => $applied, 'left' => round(min((float) $dp->paid_amount, (float) $dp->total_amount) - $applied, 2)];
    }

    /** Tab Billing di Customer Order (hanya baca): angka, rantai Surat Jalan -> invoice, dokumen, buku uang muka. */
    public static function orderBilling(CoOrder $o): array
    {
        $b = self::service()->orderBilling($o);

        return [
            'summary' => [
                ['label' => t('Order Value'), 'value' => Locale::number($b['order_value'], 2)],
                ['label' => t('Waiting Proof of Delivery (value)'), 'value' => Locale::number($b['waiting_value'], 2)],
                ['label' => t('Ready to Invoice (value)'), 'value' => Locale::number($b['ready_value'], 2)],
                ['label' => t('Invoiced'), 'value' => Locale::number($b['invoiced'], 2)],
                ['label' => t('Down Payment Received'), 'value' => Locale::number($b['dp']['received'], 2)],
                ['label' => t('Down Payment Applied'), 'value' => '('.Locale::number($b['dp']['applied'], 2).')'],
                ['label' => t('Down Payment Left'), 'value' => Locale::number($b['dp']['balance'], 2)],
                ['label' => t('Paid by Customer'), 'value' => Locale::number($b['paid'], 2)],
                ['label' => t('Outstanding'), 'value' => Locale::number($b['outstanding'], 2)],
            ],
            'chain' => $b['chain']->map(fn (array $r) => self::chainRow($r))->values()->all(),
            'documents' => $b['documents']->map(fn (FinInvoice $i) => ['id' => $i->invoice_id, 'no' => $i->invoice_number ?: 'Draft #'.$i->invoice_id,
                'kind' => t($i->invoice_type === Service::DOWN_PAYMENT ? 'Down Payment' : 'Invoice'), 'date' => Locale::d($i->invoice_date) ?? '—', 'due' => Locale::d($i->due_date) ?? '—',
                'total' => Locale::number($i->total_amount, 2), 'paid' => Locale::number($i->paid_amount, 2),
                'outstanding' => $i->doc_status === 'POSTED' ? Locale::number(max(0, $i->outstanding()), 2) : '', 'state' => t(self::state($i))])->values()->all(),
            'ledger' => self::ledgerRows($b['dp']),
            'ready' => $b['ready_value'] > 0,
        ];
    }

    /** Uang muka lain order yang sama yang sudah ditagih (Confirmed), di luar dokumen ini. */
    public static function billedBefore(FinInvoice $dp): float
    {
        return (float) FinInvoice::withoutGlobalScopes()->where('invoice_type', Service::DOWN_PAYMENT)->where('co_id', $dp->co_id)->where('doc_status', 'POSTED')
            ->where('invoice_id', '<>', $dp->invoice_id)->sum('total_amount');
    }

    // ---------------------------------------------------------------- aksi

    private static function num(mixed $v): ?float
    {
        $n = Locale::input($v);

        return $n === null || $n === '' ? null : (float) $n;
    }

    /** Simpan Draft dari kiriman form (angka tampilan -> angka mentah). */
    public static function save(FinInvoice $i, array $in): void
    {
        $data = array_intersect_key($in, array_flip(['invoice_date', 'payment_term_code', 'company_bank_id', 'customer_po_no', 'bill_to_name', 'bill_to_address', 'notes',
            'tax_invoice_no', 'charge_note', 'description', 'remove']));
        if ($i->invoice_type === Service::DOWN_PAYMENT) {
            $data['dp_percent'] = self::num($in['dp_percent'] ?? null);
            $data['bill_amount'] = self::num($in['bill_amount'] ?? null) ?? (float) $i->total_amount;
        } else {
            $data['charge_amount'] = self::num($in['charge_amount'] ?? null) ?? 0.0;
            $data['lines'] = collect((array) ($in['lines'] ?? []))->map(fn ($row) => ['qty_invoiced' => self::num(((array) $row)['qty_invoiced'] ?? null) ?? 0.0])->all();
            if (array_key_exists('dp', $in)) {
                $data['dp'] = collect((array) $in['dp'])->map(fn ($row) => ['apply' => self::num(((array) $row)['apply'] ?? null) ?? 0.0])->all();
            }
        }
        self::service()->saveDraft($i, $data, Auth::user()->login);
    }

    // confirm | cancel | delete -> pesan salah (English), null = berhasil.
    public static function act(FinInvoice $i, string $action, string $reason = ''): ?string
    {
        return self::guard(fn () => match ($action) {
            'confirm' => self::service()->confirm($i, Auth::user()->login),
            'cancel' => self::service()->cancel($i, $reason, Auth::user()->login),
            'delete' => self::service()->delete($i),
        });
    }

    /** Uang muka diterima (dialog Record Receipt) -> pesan salah, null = berhasil. */
    public static function receive(FinInvoice $dp, array $in): ?string
    {
        return self::guard(fn () => self::service()->receiveDownPayment($dp, trim((string) ($in['received_date'] ?? '')) ?: now()->toDateString(),
            (int) ($in['company_bank_id'] ?? 0), (float) (self::num($in['amount'] ?? null) ?? 0), mb_substr(trim((string) ($in['tax_invoice_no'] ?? '')), 0, 50) ?: null, Auth::user()->login));
    }

    /** Invoice terbuka customer ini (identitas pajak yang sama) untuk dialog Record Payment; invoice yang sedang dibuka terisi sisanya. */
    public static function openInvoiceRows(FinInvoice $i): array
    {
        return self::service()->openInvoices((int) $i->partner_id, (bool) $i->is_ppn)->map(fn (FinInvoice $o) => ['invoice_id' => $o->invoice_id, 'no' => $o->invoice_number,
            'date' => Locale::d($o->invoice_date), 'due' => Locale::d($o->due_date), 'outstanding' => Locale::number($o->outstanding(), 2),
            'paid' => $o->invoice_id === $i->invoice_id ? $o->outstanding() : null])->values()->all();
    }

    /** Pelunasan diterima (dialog Record Payment): pay [id invoice => [paid]] -> pesan salah, null = berhasil. */
    public static function pay(FinInvoice $i, array $in): ?string
    {
        $alloc = collect((array) ($in['pay'] ?? []))->map(fn ($row) => (float) (self::num(((array) $row)['paid'] ?? null) ?? 0))->all();

        return self::guard(fn () => self::service()->receivePayment((int) $i->partner_id, trim((string) ($in['received_date'] ?? '')) ?: now()->toDateString(),
            (int) ($in['company_bank_id'] ?? 0), $alloc, Auth::user()->login, mb_substr(trim((string) ($in['note'] ?? '')), 0, 100)));
    }

    private static function guard(callable $do): ?string
    {
        try {
            $do();

            return null;
        } catch (DomainException|HttpException $e) {
            return $e->getMessage();
        }
    }
}
