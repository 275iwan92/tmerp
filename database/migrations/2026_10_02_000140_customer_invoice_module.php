<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Customer Invoice + Accounts Receivable, tahap 1 (keputusan user 2026-10-02; ketentuan keuangan dan akuntansi:
// docs/md_for_systems/customerinvoice_accounting.md, rencana: customerinvoice_plan.md).
//   tm2sys  sys_doc_series + sys_doc_series_counter   nomor dokumen dari seri (format = data), terbit saat Confirm
//   tm2acc  fin_company_bank                          rekening perusahaan yang dicetak di invoice / menerima uang
//   tm2acc  fin_invoice (+kolom) + fin_invoice_line   invoice berbaris, dari baris Surat Jalan
//   tm2sdi  do_line.qty_received                      qty yang diterima customer (Proof of Delivery) = dasar tagih
// Tagihan uang muka tidak menjurnal; jurnal lahir saat uangnya diterima. Relasi ke tm2sdi / tm2mst = logis (beda database).
return new class extends Migration
{
    // kode => [nama, format, urut ulang]
    private const SERIES = [
        'INV_TM' => ['Customer Invoice - Telasih Makmur (non PPN)', '{SEQ}/TM/{ROMAN}/{YYYY}', 'MONTH'],
        'INV_LJM' => ['Customer Invoice - CV. Langgeng Jaya Makmur (PPN)', '{SEQ}/LJM/{ROMAN}/{YYYY}', 'MONTH'],
        'DP_TM' => ['Down Payment Invoice - Telasih Makmur (non PPN)', '{SEQ}/UM-TM/{ROMAN}/{YYYY}', 'MONTH'],
        'DP_LJM' => ['Down Payment Invoice - CV. Langgeng Jaya Makmur (PPN)', '{SEQ}/UM-LJM/{ROMAN}/{YYYY}', 'MONTH'],
    ];

    // id rekening di sistem lama (tm2old.mst_bank_account) => [identitas pajak, bawaan]. Disalin atas izin user 2026-10-02.
    private const LEGACY_BANKS = [4 => ['PPN', true], 3 => ['PPN', false], 2 => ['NON_PPN', true], 5 => ['NON_PPN', false], 1 => ['NON_PPN', false]];

    private const CONSTANTS = [
        'DP_VAT_ON_RECEIPT' => [1, 'Down payment on a VAT order: 1 = VAT is recognised when the money is received (tax invoice "Uang Muka", final invoice carries the rest). 0 = old practice, all VAT on the final invoice. Change only on a written decision.'],
        'DP_DEFAULT_PERCENT' => [50, 'Default percentage proposed on a new Down Payment Invoice (percent of the order total).'],
    ];

    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2acc')->getDatabaseName(), 'ddt');
    }

    public function up(): void
    {
        $sys = Schema::connection('tm2sys');
        if (! $sys->hasTable('sys_doc_series')) {
            $sys->create('sys_doc_series', function (Blueprint $t) {
                $t->id('id_doc_series');
                $t->string('series_code', 30)->unique();
                $t->string('series_name', 100);
                $t->string('number_format', 60);                  // token: {SEQ} {SEQ:5} {ROMAN} {MM} {YY} {YYYY}
                $t->string('reset_period', 10)->default('MONTH'); // MONTH | YEAR | NONE
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
            });
        }
        if (! $sys->hasTable('sys_doc_series_counter')) {
            $sys->create('sys_doc_series_counter', function (Blueprint $t) {
                $t->id('id_doc_series_counter');
                $t->foreignId('doc_series_id')->constrained('sys_doc_series', 'id_doc_series')->cascadeOnDelete();
                $t->string('period_key', 10);                     // 2026-09 | 2026 | ALL
                $t->unsignedInteger('last_no')->default(0);
                $t->timestamp('updated_at')->nullable();
                $t->unique(['doc_series_id', 'period_key']);
            });
        }

        $acc = Schema::connection('tm2acc');
        if (! $acc->hasTable('fin_company_bank')) {
            $acc->create('fin_company_bank', function (Blueprint $t) {
                $t->id('id_company_bank');
                $t->string('bank_name', 60);
                $t->string('account_no', 40);
                $t->string('account_holder', 120);
                $t->string('gl_account_code', 20)->nullable();     // fin_account.account_code: akun bank di buku besar
                $t->string('tax_identity', 10)->default('BOTH');   // PPN | NON_PPN | BOTH
                $t->boolean('is_default')->default(false);         // bawaan untuk identitasnya
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
                $t->unique(['bank_name', 'account_no']);
            });
        }

        // Draft belum bernomor: nomor terbit saat Confirm (UNIQUE tetap; NULL boleh berulang).
        DB::connection('tm2acc')->statement('ALTER TABLE fin_invoice MODIFY invoice_number VARCHAR(50) NULL');
        $acc->table('fin_invoice', function (Blueprint $t) use ($acc) {
            $add = fn (string $col, Closure $def) => $acc->hasColumn('fin_invoice', $col) ?: $def($t);
            $add('is_ppn', fn ($t) => $t->boolean('is_ppn')->default(false)->after('co_id'));
            $add('tax_rate', fn ($t) => $t->decimal('tax_rate', 5, 2)->default(0)->after('is_ppn'));               // persen yang dipakai saat Confirm
            $add('payment_term_code', fn ($t) => $t->string('payment_term_code', 50)->nullable()->after('tax_rate'));
            $add('customer_po_no', fn ($t) => $t->string('customer_po_no', 100)->nullable()->after('payment_term_code'));
            $add('bill_to_name', fn ($t) => $t->string('bill_to_name', 200)->nullable()->after('customer_po_no'));
            $add('bill_to_address', fn ($t) => $t->text('bill_to_address')->nullable()->after('bill_to_name'));
            $add('company_bank_id', fn ($t) => $t->unsignedBigInteger('company_bank_id')->nullable()->after('bill_to_address'));
            $add('discount_pct', fn ($t) => $t->decimal('discount_pct', 5, 2)->default(0)->after('subtotal'));     // salinan diskon dokumen CO
            $add('discount_amount', fn ($t) => $t->decimal('discount_amount', 18, 4)->default(0)->after('discount_pct'));
            $add('charge_amount', fn ($t) => $t->decimal('charge_amount', 18, 4)->default(0)->after('discount_amount')); // biaya lain (ongkos kirim, FOB)
            $add('charge_note', fn ($t) => $t->string('charge_note', 100)->nullable()->after('charge_amount'));
            $add('dp_applied_amount', fn ($t) => $t->decimal('dp_applied_amount', 18, 4)->default(0)->after('paid_amount')); // bagian paid_amount yang berasal dari uang muka
            $add('dp_plan', fn ($t) => $t->json('dp_plan')->nullable()->after('dp_applied_amount'));               // Draft: {id tagihan DP: nilai yang akan dipotong}
            $add('order_amount', fn ($t) => $t->decimal('order_amount', 18, 4)->nullable()->after('dp_plan'));     // DP: nilai order saat ditagih
            $add('dp_percent', fn ($t) => $t->decimal('dp_percent', 5, 2)->nullable()->after('order_amount'));     // DP: persen; kosong = nominal
            $add('notes', fn ($t) => $t->string('notes', 255)->nullable()->after('dp_percent'));
            $add('confirmed_by', fn ($t) => $t->string('confirmed_by', 50)->nullable()->after('notes'));
            $add('confirmed_at', fn ($t) => $t->dateTime('confirmed_at')->nullable()->after('confirmed_by'));
            $add('cancel_reason', fn ($t) => $t->string('cancel_reason', 255)->nullable()->after('confirmed_at'));
            $add('cancelled_by', fn ($t) => $t->string('cancelled_by', 50)->nullable()->after('cancel_reason'));
            $add('cancelled_at', fn ($t) => $t->dateTime('cancelled_at')->nullable()->after('cancelled_by'));
        });
        if (! $acc->hasTable('fin_invoice_line')) {
            $acc->create('fin_invoice_line', function (Blueprint $t) {
                $t->id('id_invoice_line');
                $t->foreignId('invoice_id')->constrained('fin_invoice', 'invoice_id')->cascadeOnDelete();
                $t->unsignedInteger('line_no')->default(1);
                $t->unsignedBigInteger('do_line_id')->nullable()->index();   // tm2sdi.do_line
                $t->unsignedBigInteger('delivery_id')->nullable();           // tm2sdi.do_delivery
                $t->string('delivery_no', 20)->nullable();                   // salinan nomor Surat Jalan
                $t->date('delivery_date')->nullable();
                $t->unsignedBigInteger('co_order_item_id')->nullable()->index(); // tm2sdi.co_order_item
                $t->unsignedBigInteger('product_id')->nullable();            // tm2mst.prod_product
                $t->string('description', 255);
                $t->string('color_config', 10)->nullable();                  // CFG di cetakan (mis. 4/0)
                $t->string('order_type', 10)->nullable();                    // NEW | RTP | RDP
                $t->boolean('is_lump_sum')->default(false);                  // baris Qty Minim: nilai = harga
                $t->decimal('qty_delivered', 14, 2)->default(0);
                $t->decimal('qty_received', 14, 2)->default(0);
                $t->decimal('qty_invoiced', 14, 2)->default(0);
                $t->string('qty_unit', 20)->nullable();
                $t->decimal('unit_price', 18, 4)->default(0);
                $t->decimal('line_total', 18, 2)->default(0);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
            });
        }

        $sdi = Schema::connection('tm2sdi');
        if (! $sdi->hasColumn('do_line', 'qty_received')) {
            // NULL = Surat Jalan belum kembali (belum bisa ditagih).
            $sdi->table('do_line', fn (Blueprint $t) => $t->decimal('qty_received', 14, 2)->nullable()->after('qty_do'));
            // Surat Jalan yang sudah dicatat diterima penuh sebelum kolom ini ada: diterima = dikirim.
            DB::connection('tm2sdi')->statement("UPDATE do_line l JOIN do_delivery_pod p ON p.delivery_id = l.delivery_id AND p.result = 'RECEIVED' SET l.qty_received = l.qty_do WHERE l.qty_received IS NULL");
        }

        // Umur piutang hanya memuat invoice yang sudah Confirmed (dulu Draft dan Cancelled ikut terhitung).
        DB::connection('tm2acc')->statement("CREATE OR REPLACE VIEW v1_fin_ar_aging AS
            SELECT i.invoice_id, i.invoice_number, i.partner_id, i.co_id, i.invoice_date, i.due_date, i.total_amount, i.paid_amount,
                   (i.total_amount - i.paid_amount) AS outstanding, (TO_DAYS(CURDATE()) - TO_DAYS(i.due_date)) AS days_overdue, i.payment_status
            FROM fin_invoice i
            WHERE i.invoice_type = 'CUSTOMER_INVOICE' AND i.payment_status <> 'PAID' AND i.doc_status = 'POSTED'");

        $this->seed();
    }

    private function seed(): void
    {
        $db = DB::connection('tm2sys');
        foreach (self::SERIES as $code => [$name, $format, $reset]) {
            if (! $db->table('sys_doc_series')->where('series_code', $code)->exists()) {
                $db->table('sys_doc_series')->insert(['series_code' => $code, 'series_name' => $name, 'number_format' => $format, 'reset_period' => $reset,
                    'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }
        foreach (self::CONSTANTS as $key => [$number, $description]) {
            if (! $db->table('sys_constant')->where('constant_key', $key)->exists()) {
                $db->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'FINANCE', 'constant_group' => 'INVOICE', 'constant_number' => $number,
                    'description' => $description, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }

        // Rekening perusahaan dari sistem lama (data nyata). Akun buku besarnya belum ada per rekening: di database dummy
        // semuanya sementara menunjuk 111200 supaya alurnya bisa dicoba; di database nyata dibiarkan kosong sampai Finance
        // mengisinya di layar Company Bank Accounts (penerimaan uang ditolak selama kosong).
        $acc = DB::connection('tm2acc');
        if (! $acc->table('fin_company_bank')->exists() && Schema::connection('tm2old')->hasTable('mst_bank_account')) {
            $old = DB::connection('tm2old')->table('mst_bank_account')->get()->keyBy('bank_account_id');
            foreach (self::LEGACY_BANKS as $id => [$identity, $default]) {
                if ($b = $old->get($id)) {
                    $acc->table('fin_company_bank')->insert(['bank_name' => trim(preg_replace('/^BANK\s+/i', '', (string) $b->bank_name)), 'account_no' => trim((string) $b->bank_account_no),
                        'account_holder' => trim((string) $b->bank_account_name), 'gl_account_code' => $this->isDummy() ? '111200' : null, 'tax_identity' => $identity,
                        'is_default' => $default, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
                }
            }
        }

        // Menu: Customer Invoice di Finance > Transaction Flows; dua layar isi master.
        $menu = fn () => $db->table('sys_app_menu');
        $rows = [
            'FIN_AR_INVOICE' => ['FIN_AR', 18, 'Customer Invoice', 'fa-solid fa-file-invoice-dollar', 'customer-invoice', 'form'],
            'FIN_COMPANY_BANK' => ['FIN_AP_RECEIPT', 90, 'Company Bank Accounts', 'fa-solid fa-building-columns', 'fin-company-bank', 'grid'],
            'SET_DOC_SERIES' => ['SET_CONSTANTS', 39, 'Document Number Series', 'fa-solid fa-list-ol', 'setup-doc-series', 'grid'],
        ];
        foreach ($rows as $code => [$sibling, $sort, $label, $icon, $slug, $object]) {
            $near = $menu()->where('code', $sibling)->first();
            if ($near && ! $menu()->where('code', $code)->exists()) {
                $menu()->insert(['parent_id' => $near->parent_id, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $object,
                    'target' => 'v2:'.$slug, 'sort_order' => $sort, 'activate' => 1, 'required_object' => $near->required_object, 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        $db->table('sys_app_menu')->whereIn('code', ['FIN_AR_INVOICE', 'FIN_COMPANY_BANK', 'SET_DOC_SERIES'])->delete();
        $db->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
        Schema::connection('tm2sys')->dropIfExists('sys_doc_series_counter');
        Schema::connection('tm2sys')->dropIfExists('sys_doc_series');

        $sdi = Schema::connection('tm2sdi');
        if ($sdi->hasColumn('do_line', 'qty_received')) {
            $sdi->table('do_line', fn (Blueprint $t) => $t->dropColumn('qty_received'));
        }
        $acc = Schema::connection('tm2acc');
        $acc->dropIfExists('fin_invoice_line');
        $acc->dropIfExists('fin_company_bank');
        $cols = array_values(array_filter(['is_ppn', 'tax_rate', 'payment_term_code', 'customer_po_no', 'bill_to_name', 'bill_to_address', 'company_bank_id', 'discount_pct', 'discount_amount',
            'charge_amount', 'charge_note', 'dp_applied_amount', 'dp_plan', 'order_amount', 'dp_percent', 'notes', 'confirmed_by', 'confirmed_at', 'cancel_reason', 'cancelled_by',
            'cancelled_at'], fn ($c) => $acc->hasColumn('fin_invoice', $c)));
        if ($cols) {
            $acc->table('fin_invoice', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
