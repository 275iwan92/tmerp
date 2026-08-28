<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// docs/downpayment_tier1-4.md §0 "DELTA WAJIB" — uang muka (DP) dua
// arah di atas struktur GL yang SUDAH ADA. `fin_invoice.invoice_type` enum +
// tabel `fin_dp_allocation` TERNYATA SUDAH diterapkan langsung di MySQL
// (di luar migration Laravel) sebelum sesi ini — PK asli `allocation_id`
// (bukan `dp_allocation_id` seperti draf DDL di dokumen), sudah termasuk
// audit updated_by/updated_at. Migration ini idempotent: lewati apa yang
// sudah ada, cuma tambah CHECK guard + view sisa DP yang masih kosong.
return new class extends Migration
{
    public function up(): void
    {
        $acc = DB::connection('tm2acc');

        $acc->statement(
            'ALTER TABLE fin_invoice MODIFY COLUMN invoice_type '.
            "ENUM('CUSTOMER_INVOICE','SUPPLIER_BILL','CUSTOMER_DP','SUPPLIER_DP') ".
            'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL'
        );

        if (! Schema::connection('tm2acc')->hasTable('fin_dp_allocation')) {
            Schema::connection('tm2acc')->create('fin_dp_allocation', function (Blueprint $table) {
                $table->id('allocation_id');
                $table->unsignedBigInteger('dp_invoice_id');
                $table->unsignedBigInteger('final_invoice_id');
                $table->decimal('allocated_base', 18, 4)->default(0);
                $table->decimal('allocated_tax', 18, 4)->default(0);
                $table->date('allocation_date');
                $table->string('created_by', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 50)->nullable();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->foreign('dp_invoice_id', 'fk_dpa_dp')->references('invoice_id')->on('fin_invoice')->restrictOnDelete();
                $table->foreign('final_invoice_id', 'fk_dpa_final')->references('invoice_id')->on('fin_invoice')->restrictOnDelete();
                $table->index('dp_invoice_id', 'idx_dpa_dp');
                $table->index('final_invoice_id', 'idx_dpa_final');
            });
        }

        $hasCheck = $acc->table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', 'tm2acc')
            ->where('TABLE_NAME', 'fin_dp_allocation')
            ->where('CONSTRAINT_NAME', 'chk_dpa_not_self')
            ->exists();

        if (! $hasCheck) {
            $acc->statement('ALTER TABLE fin_dp_allocation ADD CONSTRAINT chk_dpa_not_self CHECK (dp_invoice_id <> final_invoice_id)');
        }

        $acc->statement('DROP VIEW IF EXISTS v1_fin_dp_outstanding');
        $acc->statement(<<<'SQL'
            CREATE VIEW v1_fin_dp_outstanding AS
            SELECT d.invoice_id AS dp_invoice_id, d.invoice_number, d.partner_id, d.co_id,
                   d.subtotal AS dp_base, d.tax_amount AS dp_tax, d.total_amount AS dp_total,
                   COALESCE(SUM(a.allocated_base),0) AS used_base,
                   COALESCE(SUM(a.allocated_tax),0)  AS used_tax,
                   d.total_amount - COALESCE(SUM(a.allocated_base+a.allocated_tax),0) AS dp_remaining
            FROM fin_invoice d
            LEFT JOIN fin_dp_allocation a ON a.dp_invoice_id = d.invoice_id
            WHERE d.invoice_type IN ('CUSTOMER_DP','SUPPLIER_DP')
            GROUP BY d.invoice_id
            SQL);
    }

    public function down(): void
    {
        $acc = DB::connection('tm2acc');

        $acc->statement('DROP VIEW IF EXISTS v1_fin_dp_outstanding');
    }
};
