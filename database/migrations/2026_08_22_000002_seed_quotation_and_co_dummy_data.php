<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Grid Quotation (quotation/list.blade.php) & Customer Order (co/list.blade.php)
// kosong melompong -- 0 baris di quo_quotation/co_order. feedback_keep_dummy_data:
// layar baru/di-restandardisasi wajib ada data uji.
//
// TIDAK lewat App\Services\Sales\QuotationBuilder::addInquiry() -- itu butuh
// inquiry.costing.published_flag=1 + priceTiers hasil CostingEngine sungguhan,
// yang berarti harus menjalankan seluruh pipeline costing (rate bahan, proses,
// dll) yang belum ada datanya. Daripada mengarang angka HPP palsu, baris
// quotation di sini source_type='MANUAL' (inquiry_id null) -- jalur real yang
// sudah didukung skema (quo_quotation_item.inquiry_id nullable), bukan data
// yang dipalsukan seolah dari costing.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sdi');
        $custId = DB::connection('tm2mst')->table('cust_customer')->where('is_active', true)->orderBy('id_customer')->value('id_customer');

        if (! $custId) {
            return;
        }

        $quoDraftId = $db->table('quo_quotation')->insertGetId([
            'quotation_no' => 'QUO2600001',
            'customer_id' => $custId,
            'revision_no' => 0,
            'issued_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'tax_scheme' => 'EXCL',
            'currency_code' => 'IDR',
            'doc_status' => 'DRAFT',
            'issued_by' => 'system',
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $db->table('quo_quotation_item')->insert([
            'quotation_id' => $quoDraftId,
            'line_seq' => 1,
            'source_type' => 'MANUAL',
            'description' => 'Folding Box Duplex 350gr Kosmetik (contoh manual)',
            'qty' => 5000,
            'qty_unit' => 'Pcs',
            'unit_price' => 850,
            'discount_pct' => 0,
            'subtotal' => 5000 * 850,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quoPublishedId = $db->table('quo_quotation')->insertGetId([
            'quotation_no' => 'QUO2600002',
            'customer_id' => $custId,
            'revision_no' => 0,
            'issued_date' => now()->subDays(5)->toDateString(),
            'valid_until' => now()->addDays(25)->toDateString(),
            'tax_scheme' => 'EXCL',
            'currency_code' => 'IDR',
            'doc_status' => 'PUBLISHED',
            'issued_by' => 'system',
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $quoItemId = $db->table('quo_quotation_item')->insertGetId([
            'quotation_id' => $quoPublishedId,
            'line_seq' => 1,
            'source_type' => 'MANUAL',
            'description' => 'RSC Box Corrugated 5-Ply Ekspor (contoh manual)',
            'qty' => 1000,
            'qty_unit' => 'Pcs',
            'unit_price' => 12500,
            'discount_pct' => 0,
            'subtotal' => 1000 * 12500,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $coId = $db->table('co_order')->insertGetId([
            'co_no' => 'CO2600001',
            'co_date' => now()->subDays(2)->toDateString(),
            'quotation_id' => $quoPublishedId,
            'customer_po_no' => 'PO-DUMMY-001',
            'doc_status' => 'CONFIRMED',
            'notes' => 'Data uji -- dibuat manual, bukan dari alur Costing sungguhan.',
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $db->table('co_order_item')->insert([
            'order_id' => $coId,
            'line_seq' => 1,
            'quotation_item_id' => $quoItemId,
            'line_kind' => 'PRODUCT',
            'description' => 'RSC Box Corrugated 5-Ply Ekspor (contoh manual)',
            'qty' => 1000,
            'qty_unit' => 'Pcs',
            'unit_price' => 12500,
            'discount_pct' => 0,
            'subtotal' => 1000 * 12500,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sdi');
        $db->table('co_order_item')->whereIn('order_id', $db->table('co_order')->where('co_no', 'CO2600001')->pluck('id_order'))->delete();
        $db->table('co_order')->where('co_no', 'CO2600001')->delete();
        $db->table('quo_quotation_item')->whereIn('quotation_id', $db->table('quo_quotation')->whereIn('quotation_no', ['QUO2600001', 'QUO2600002'])->pluck('id_quotation'))->delete();
        $db->table('quo_quotation')->whereIn('quotation_no', ['QUO2600001', 'QUO2600002'])->delete();
    }
};
