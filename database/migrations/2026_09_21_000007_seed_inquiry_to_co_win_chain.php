<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdProduct;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SecUser;
use App\Services\Sales\CoOrderBuilder;
use App\Services\Sales\QuotationBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-09-21: data dummy BERKESINAMBUNGAN, minimal 4 set, dari
// Inquiry Product sampai "win" dan siap dipindah ke Customer Order.
//
// Rantainya dilanjutkan dari 10 inquiry dummy yang SUDAH ada & sudah dihitung
// CostingEngine sungguhan (2026_09_18_000007, prefix IQ99DUM) -- bukan bikin
// angka baru hasil tebakan, supaya harga di Quotation/CO benar-benar keluaran
// engine (feedback_keep_dummy_data + prinsip "jangan palsukan data costing").
//
//   Set A-D (IQ99DUM001-004) : costing PUBLISHED -> Quotation PUBLISHED, BELUM
//                              ada CO -- inilah "siap dipindah ke CO" yang
//                              diminta, supaya jalur "+ New Record > INTEGRATED"
//                              bisa dicoba sendiri oleh user.
//   Set E-F (IQ99DUM005-006) : diteruskan sampai CO INTEGRATED (E di-Confirm
//                              lengkap dgn jadwal CPDEL, F dibiarkan DRAFT).
//   Set G-H                  : 2 CO INSTANT dari Master Product (tanpa Quotation)
//                              -- G Confirm, H DRAFT.
//
// Semua baris yang lahir di sini ditandai notes '[SEED-WIN]' + data_origin
// 'DUMMY' supaya down() presisi dan bisa direset lewat Setup > Dummy & Legacy.
return new class extends Migration
{
    private const MARK = '[SEED-WIN] data dummy rantai Inquiry-Quotation-CO 2026-09-21.';

    public function up(): void
    {
        $user = SecUser::find(1);

        if (! $user) {
            return;
        }

        Auth::login($user);

        $inquiries = IqpInquiry::whereIn('inquiry_no', [
            'IQ99DUM001', 'IQ99DUM002', 'IQ99DUM003', 'IQ99DUM004', 'IQ99DUM005', 'IQ99DUM006',
        ])->orderBy('inquiry_no')->get();

        if ($inquiries->count() < 4) {
            Auth::logout();

            return; // seeder ProCost belum jalan -- lewati, jangan gagalkan migrate
        }

        foreach ($inquiries as $i => $inquiry) {
            $quotation = $this->winChainFor($inquiry);

            if (! $quotation) {
                continue;
            }

            // 2 set terakhir diteruskan sampai CO supaya kedua mode & nota CO
            // langsung ada isinya; 4 set pertama sengaja BERHENTI di sini.
            if ($i >= 4) {
                $this->coFromQuotation($quotation, confirm: $i === 4);
            }
        }

        $this->instantCoDemo();

        Auth::logout();
    }

    // Inquiry -> publish costing (deklasifikasi harga Sisi A->B) -> Quotation
    // PUBLISHED. Memakai QuotationBuilder yang sama dengan tombol di layar
    // Costing, bukan insert manual ke quo_quotation_item.
    private function winChainFor(IqpInquiry $inquiry): ?QuoQuotation
    {
        $costing = IqpCosting::where('inquiry_id', $inquiry->id_inquiry)->latest('id_costing')->first();

        if (! $costing || $costing->priceTiers()->count() === 0) {
            return null;
        }

        $costing->update([
            'doc_status' => 'PUBLISHED',
            'published_flag' => true,
            'is_approved' => true,
            'approved_by' => 'system',
            'approved_at' => now(),
            'updated_by' => 'system',
        ]);

        $existing = QuoQuotation::where('customer_id', $inquiry->customer_id)
            ->where('notes', 'like', '%[SEED-WIN]%')
            ->whereHas('items', fn ($q) => $q->where('inquiry_id', $inquiry->id_inquiry))
            ->first();

        if ($existing) {
            return $existing; // idempoten
        }

        $quotation = QuoQuotation::create([
            'quotation_no' => QuoQuotation::nextQuotationNo(),
            'customer_id' => $inquiry->customer_id,
            'revision_no' => 0,
            'issued_date' => now()->subDays(7)->toDateString(),
            'valid_until' => now()->addDays(23)->toDateString(),
            'payment_term' => 'NET30',
            'tax_scheme' => 'EXCL',
            'currency_code' => 'IDR',
            'notes' => self::MARK,
            'doc_status' => 'DRAFT',
            'issued_by' => 'system',
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);

        if (! QuotationBuilder::addInquiry($quotation, $inquiry)) {
            $quotation->delete();

            return null;
        }

        $quotation->update(['doc_status' => 'PUBLISHED', 'updated_by' => 'system']);
        $this->markDummy('quo_quotation', 'id_quotation', [$quotation->id_quotation]);
        $this->markDummy('quo_quotation_item', 'quotation_id', [$quotation->id_quotation]);

        return $quotation;
    }

    private function coFromQuotation(QuoQuotation $quotation, bool $confirm): void
    {
        $order = CoOrderBuilder::findOrCreateFromQuotation($quotation);

        if (! $order) {
            return;
        }

        $order->update([
            'customer_po_no' => 'PO/'.$quotation->customer_id.'/'.now()->format('ymd'),
            'sales_person_id' => SalesPerson::where('activate', true)->value('id_sales_person'),
            'notes' => self::MARK,
            'updated_by' => 'system',
        ]);

        // due_date wajib di semua baris PRODUCT sebelum Confirm (guard CPDEL).
        foreach ($order->items()->where('line_kind', 'PRODUCT')->get() as $n => $item) {
            $item->update(['due_date' => now()->addDays(14 + $n * 7)->toDateString(), 'updated_by' => 'system']);
        }

        if ($confirm) {
            CoOrderBuilder::confirm($order->fresh()->load('items.deliveries'));
        }

        $this->markCoDummy($order->id_order);
    }

    // 2 CO INSTANT: tanpa Quotation, tiap baris dari Master Product (syarat mode).
    // Di DB dev sekarang hanya SATU customer yang punya produk master berharga,
    // jadi kalau cuma ketemu 1 kandidat, CO kedua dibuat untuk customer yang sama
    // (wajar secara bisnis) supaya ada contoh INSTANT berstatus DRAFT juga.
    private function instantCoDemo(): void
    {
        if (CoOrder::where('entry_mode', 'INSTANT')->where('notes', 'like', '%[SEED-WIN]%')->count() >= 2) {
            return;
        }

        $candidates = ProdProduct::where('activate', true)
            ->whereNotNull('customer_id')
            ->where('default_selling_price', '>', 0)
            ->orderBy('customer_id')
            ->limit(600)
            ->get()
            ->groupBy('customer_id')
            ->filter(fn ($g) => $g->isNotEmpty())
            ->take(2)
            ->values();

        if ($candidates->isEmpty()) {
            return;
        }

        if ($candidates->count() === 1) {
            $candidates->push($candidates->first());
        }

        $salesPersonId = SalesPerson::where('activate', true)->value('id_sales_person');

        foreach ($candidates as $n => $group) {
            $customer = CustCustomer::find($group->first()->customer_id);

            if (! $customer) {
                continue;
            }

            $order = CoOrderBuilder::createInstant($customer, $salesPersonId);
            $order->update([
                'customer_po_no' => 'VERBAL/'.now()->format('ymd').'/'.($n + 1),
                'notes' => self::MARK.' Order verbal, tanpa Inquiry/Quotation.',
                'updated_by' => 'system',
            ]);

            foreach ($group->take(2)->values() as $k => $product) {
                $item = CoOrderBuilder::addProductLine($order, $product, 5000 * ($k + 1));
                $item->update(['due_date' => now()->addDays(10 + $k * 5)->toDateString(), 'updated_by' => 'system']);
            }

            if ($n === 0) {
                CoOrderBuilder::confirm($order->fresh()->load('items.deliveries'));
            }

            $this->markCoDummy($order->id_order);
        }
    }

    private function markCoDummy(int $orderId): void
    {
        $this->markDummy('co_order', 'id_order', [$orderId]);
        $this->markDummy('co_order_item', 'order_id', [$orderId]);
        $this->markDummy('co_order_delivery', 'order_id', [$orderId]);
    }

    private function markDummy(string $table, string $column, array $ids): void
    {
        DB::connection('tm2sdi')->table($table)->whereIn($column, $ids)
            ->update(['data_origin' => 'DUMMY', 'created_by' => 'system', 'updated_by' => 'system']);
    }

    public function down(): void
    {
        $sdi = DB::connection('tm2sdi');

        $orderIds = $sdi->table('co_order')->where('notes', 'like', '%[SEED-WIN]%')->pluck('id_order');
        $sdi->table('co_order_delivery')->whereIn('order_id', $orderIds)->delete();
        $sdi->table('co_order_item')->whereIn('order_id', $orderIds)->delete();
        $sdi->table('co_order')->whereIn('id_order', $orderIds)->delete();

        $quotationIds = $sdi->table('quo_quotation')->where('notes', 'like', '%[SEED-WIN]%')->pluck('id_quotation');
        $sdi->table('quo_quotation_item')->whereIn('quotation_id', $quotationIds)->delete();
        $sdi->table('quo_quotation')->whereIn('id_quotation', $quotationIds)->delete();

        $inquiryIds = $sdi->table('iqp_inquiry')->where('inquiry_no', 'like', 'IQ99DUM%')->pluck('id_inquiry');
        $sdi->table('iqp_inquiry')->whereIn('id_inquiry', $inquiryIds)
            ->update(['co_id' => null, 'sub_state' => 'PRICE_CALCULATED']);
        $sdi->table('iqp_costing')->whereIn('inquiry_id', $inquiryIds)
            ->update(['doc_status' => 'CALCULATED', 'published_flag' => 0, 'is_approved' => 0, 'approved_by' => null, 'approved_at' => null]);
    }
};
