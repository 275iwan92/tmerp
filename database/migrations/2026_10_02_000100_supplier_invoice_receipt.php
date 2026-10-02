<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tanda Terima Tagihan Supplier (user 2026-10-02: "bukan tagihan supplier tapi tanda terima tagihan supplier", lalu "ya
// buatkan"): bukti bahwa invoice supplier sudah kami terima -- satu dokumen per penyerahan, satu baris per invoice.
// Bukan bukti bayar dan tidak membuat jurnal; tagihannya sendiri tetap tm2acc.fin_invoice (SUPPLIER_BILL).
// supplier_id / po_id = relasi logis ke tm2mst / tm2pur (beda database); invoice_id = FK fisik (sama-sama tm2acc).
// Sekaligus: baris menu Finance > Transaction Flows dan konstanta penanda tangan cetakan baru (tanda terima tagihan,
// tanda terima uang muka, QC inspection).
return new class extends Migration
{
    private const SIGNERS = ['SIR' => 'Supplier Invoice Receipt', 'DPR' => 'Down Payment Receipt', 'QCI' => 'QC Inspection'];

    private const ROLES = ['APPROVER' => 'Approved By', 'ACKNOWLEDGER' => 'Acknowledged By'];

    public function up(): void
    {
        $acc = Schema::connection('tm2acc');
        if (! $acc->hasTable('fin_invoice_receipt')) {
            $acc->create('fin_invoice_receipt', function (Blueprint $t) {
                $t->id('id_invoice_receipt');
                $t->string('receipt_no', 20)->nullable()->unique();       // lahir saat Confirm
                $t->date('receipt_date');
                $t->unsignedBigInteger('supplier_id')->index();           // tm2mst.sup_supplier
                $t->string('handed_by', 80)->nullable();                  // orang supplier yang menyerahkan
                $t->string('payment_term', 60)->nullable();               // salinan syarat bayar supplier saat dibuat
                $t->date('planned_payment_date')->nullable();
                $t->string('payment_method', 20)->nullable();             // TRANSFER | CASH | GIRO
                $t->boolean('att_invoice')->default(false);               // lampiran yang ikut diserahkan
                $t->boolean('att_tax_invoice')->default(false);
                $t->boolean('att_delivery_note')->default(false);
                $t->boolean('att_payment_receipt')->default(false);
                $t->string('notes', 255)->nullable();
                $t->boolean('stat_confirm')->default(false);
                $t->boolean('stat_cancel')->default(false);
                $t->string('cancel_reason', 255)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
            });
        }
        if (! $acc->hasTable('fin_invoice_receipt_line')) {
            $acc->create('fin_invoice_receipt_line', function (Blueprint $t) {
                $t->id('id_invoice_receipt_line');
                $t->foreignId('invoice_receipt_id')->constrained('fin_invoice_receipt', 'id_invoice_receipt')->cascadeOnDelete();
                $t->unsignedInteger('line_no')->default(1);
                $t->foreignId('invoice_id')->nullable()->constrained('fin_invoice', 'invoice_id')->nullOnDelete(); // tagihan yang sudah dicatat
                $t->string('supplier_invoice_no', 60);
                $t->date('invoice_date')->nullable();
                $t->unsignedBigInteger('po_id')->nullable();              // tm2pur.po_order
                $t->string('po_no', 30)->nullable();                      // salinan nomor PO
                $t->string('tax_invoice_no', 40)->nullable();
                $t->decimal('amount', 18, 4)->default(0);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
            });
        }

        $sys = DB::connection('tm2sys');
        $ap = $sys->table('sys_app_menu')->where('code', 'FIN_AP')->first();
        if ($ap && ! $sys->table('sys_app_menu')->where('code', 'FIN_AP_RECEIPT')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'parent_id' => $ap->parent_id, 'code' => 'FIN_AP_RECEIPT', 'label' => 'Supplier Invoice Receipt', 'icon' => 'fa-solid fa-file-circle-check',
                'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:supplier-invoice-receipt', 'sort_order' => $ap->sort_order + 2,
                'activate' => 1, 'required_object' => $ap->required_object, 'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
            ]);
        }
        foreach (self::SIGNERS as $code => $doc) {
            foreach (self::ROLES as $role => $label) {
                $key = "SIGN_{$code}_{$role}";
                if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                    $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRINT', 'constant_group' => 'SIGNER', 'constant_text' => null,
                        'description' => "Name printed under \"{$label}\" on the {$doc} print. Empty = blank line to sign by hand.",
                        'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
                }
            }
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->where('code', 'FIN_AP_RECEIPT')->delete();
        $sys->table('sys_constant')->where('constant_group', 'SIGNER')
            ->whereIn('constant_key', array_merge(...array_map(fn ($c) => ["SIGN_{$c}_APPROVER", "SIGN_{$c}_ACKNOWLEDGER"], array_keys(self::SIGNERS))))->delete();
        Schema::connection('tm2acc')->dropIfExists('fin_invoice_receipt_line');
        Schema::connection('tm2acc')->dropIfExists('fin_invoice_receipt');
    }
};
