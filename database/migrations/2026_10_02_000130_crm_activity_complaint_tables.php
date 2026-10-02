<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CRM (user 2026-10-02: Sales = Inquiry & Quotation + Order + CRM; "CRM adalah bangun hubungan dengan customer").
// 1. Log follow-up quotation (quo_activity) DIPERLUAS menjadi aktivitas per customer: tabel berganti nama crm_activity,
//    customer wajib, quotation boleh kosong (kunjungan rutin, penagihan, prospek). Follow-up punya tanda selesai sendiri.
// 2. Komplain / retur penjualan (crm_complaint + _item): informasinya milik Sales.
// 3. Mutu milik QA: jawaban QA atas komplain di tm2prd.qc_complaint_review (database lain -> complaint_id relasi logis).
// customer_id / contact_id / product_id = relasi logis ke tm2mst (beda database, tanpa FK).
return new class extends Migration
{
    public function up(): void
    {
        $sdi = Schema::connection('tm2sdi');
        if ($sdi->hasTable('quo_activity') && ! $sdi->hasTable('crm_activity')) {
            $sdi->rename('quo_activity', 'crm_activity');
        }
        if ($sdi->hasTable('crm_activity') && ! $sdi->hasColumn('crm_activity', 'customer_id')) {
            $sdi->table('crm_activity', function (Blueprint $t) {
                $t->unsignedBigInteger('customer_id')->nullable()->after('id_activity');
                $t->unsignedBigInteger('contact_id')->nullable()->after('customer_id');
                $t->string('topic', 20)->default('QUOTATION')->after('activity_type');
                $t->boolean('next_done')->default(false)->after('next_action');
                $t->timestamp('next_done_at')->nullable()->after('next_done');
                $t->string('next_done_by', 100)->nullable()->after('next_done_at');
                $t->index(['customer_id', 'activity_date']);
                $t->index(['next_done', 'next_action_date']);
            });
            DB::connection('tm2sdi')->statement('UPDATE crm_activity a JOIN quo_quotation q ON q.id_quotation = a.quotation_id SET a.customer_id = q.customer_id');
            $sdi->table('crm_activity', function (Blueprint $t) {
                $t->unsignedBigInteger('customer_id')->nullable(false)->change();
                $t->unsignedBigInteger('quotation_id')->nullable()->change();
            });
        }

        if (! $sdi->hasTable('crm_complaint')) {
            $sdi->create('crm_complaint', function (Blueprint $t) {
                $t->id('id_complaint');
                $t->string('complaint_no', 20)->nullable()->unique();     // lahir saat Confirm
                $t->date('complaint_date');
                $t->string('complaint_type', 10)->default('COMPLAINT');   // COMPLAINT | RETURN (barang kembali)
                $t->unsignedBigInteger('customer_id')->index();
                $t->unsignedBigInteger('contact_id')->nullable();
                $t->string('contact_person', 150)->nullable();
                $t->string('received_via', 20)->nullable();
                $t->string('category', 20)->default('QUALITY');
                $t->boolean('needs_qa')->default(true);                   // true = diteruskan ke QA setelah Confirm
                $t->foreignId('co_order_id')->nullable()->constrained('co_order', 'id_order')->nullOnDelete();
                $t->string('ref_doc_no', 60)->nullable();                 // nomor CO lama / Surat Jalan / PO customer
                $t->text('description')->nullable();
                $t->string('requested_action', 20)->nullable();
                $t->string('resolution', 20)->nullable();                 // diisi Sales saat Close
                $t->string('resolution_note', 255)->nullable();
                $t->boolean('stat_confirm')->default(false);
                $t->timestamp('confirmed_at')->nullable();
                $t->string('confirmed_by', 100)->nullable();
                $t->boolean('stat_close')->default(false);
                $t->timestamp('closed_at')->nullable();
                $t->string('closed_by', 100)->nullable();
                $t->boolean('stat_cancel')->default(false);
                $t->string('cancel_reason', 255)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
            });
        }
        if (! $sdi->hasTable('crm_complaint_item')) {
            $sdi->create('crm_complaint_item', function (Blueprint $t) {
                $t->id('id_complaint_item');
                $t->foreignId('complaint_id')->constrained('crm_complaint', 'id_complaint')->cascadeOnDelete();
                $t->unsignedInteger('line_no')->default(1);
                $t->unsignedBigInteger('product_id')->nullable();         // tm2mst.prod_product
                $t->string('product_name', 255);                          // salinan nama / diketik bila belum ada di master
                $t->decimal('qty_complaint', 14, 2)->default(0);
                $t->decimal('qty_return', 14, 2)->default(0);             // yang benar-benar kembali ke pabrik
                $t->string('qty_unit', 20)->nullable();
                $t->string('defect_note', 255)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
            });
        }

        $prd = Schema::connection('tm2prd');
        if (! $prd->hasTable('qc_complaint_review')) {
            $prd->create('qc_complaint_review', function (Blueprint $t) {
                $t->id('id_complaint_review');
                $t->unsignedBigInteger('complaint_id')->unique();         // tm2sdi.crm_complaint (1 komplain = 1 jawaban QA)
                $t->string('complaint_no', 20)->nullable();               // salinan nomor
                $t->string('verdict', 20)->nullable();                    // ACCEPTED | PARTIAL | REJECTED
                $t->text('root_cause')->nullable();
                $t->text('corrective_action')->nullable();
                $t->text('preventive_action')->nullable();
                $t->string('disposition', 20)->nullable();                // nasib barang kembali: REWORK | SCRAP | RESTOCK | NONE
                $t->boolean('stat_confirm')->default(false);
                $t->timestamp('reviewed_at')->nullable();
                $t->string('reviewed_by', 100)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
            });
        }

        $sys = DB::connection('tm2sys');
        if (Schema::connection('tm2sys')->hasTable('sys_constant_category') && ! $sys->table('sys_constant_category')->where('code', 'SALES')->exists()) {
            $sys->table('sys_constant_category')->insert(['code' => 'SALES', 'label' => 'Sales', 'description' => 'Sales settings: inquiry and quotation, order, CRM',
                'sort_order' => (int) $sys->table('sys_constant_category')->max('sort_order') + 10, 'activate' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $constants = [
            ['CRM_FOLLOWUP_SOON_DAYS', 'SALES', 'CRM', 2, null, 'CRM: a follow-up turns yellow when its date is this many working days away or less (Sunday and Holiday Calendar days are not counted)'],
            ['CRM_SLEEPING_DAYS', 'SALES', 'CRM', 180, null, 'CRM: a customer who ordered before is marked Sleeping when the last order is older than this many days'],
            ['SIGN_CCR_APPROVER', 'PRINT', 'SIGNER', null, null, 'Name printed under "Approved By" on the Customer Complaint print. Empty = blank line to sign by hand.'],
            ['SIGN_CCR_ACKNOWLEDGER', 'PRINT', 'SIGNER', null, null, 'Name printed under "Acknowledged By" on the Customer Complaint print. Empty = blank line to sign by hand.'],
        ];
        foreach ($constants as [$key, $cat, $group, $number, $text, $desc]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => $cat, 'constant_group' => $group, 'constant_number' => $number,
                    'constant_text' => $text, 'description' => $desc, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['CRM_FOLLOWUP_SOON_DAYS', 'CRM_SLEEPING_DAYS', 'SIGN_CCR_APPROVER', 'SIGN_CCR_ACKNOWLEDGER'])->delete();
        Schema::connection('tm2prd')->dropIfExists('qc_complaint_review');
        $sdi = Schema::connection('tm2sdi');
        $sdi->dropIfExists('crm_complaint_item');
        $sdi->dropIfExists('crm_complaint');
        if ($sdi->hasTable('crm_activity') && ! $sdi->hasTable('quo_activity')) {
            DB::connection('tm2sdi')->table('crm_activity')->whereNull('quotation_id')->delete();
            $sdi->table('crm_activity', function (Blueprint $t) {
                $t->dropIndex(['customer_id', 'activity_date']);
                $t->dropIndex(['next_done', 'next_action_date']);
                $t->dropColumn(['customer_id', 'contact_id', 'topic', 'next_done', 'next_done_at', 'next_done_by']);
            });
            $sdi->table('crm_activity', fn (Blueprint $t) => $t->unsignedBigInteger('quotation_id')->nullable(false)->change());
            $sdi->rename('crm_activity', 'quo_activity');
        }
    }
};
