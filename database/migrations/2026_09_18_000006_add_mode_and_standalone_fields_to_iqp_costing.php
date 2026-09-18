<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ProCost 2 layar terpisah (Mode Integrasi vs Mode Instant/standalone, keputusan
// user 2026-09-18): 1 tabel `iqp_costing` tetap dipakai keduanya, dibedakan
// kolom `mode`. Mode INSTANT tidak wajib py inquiry -> `inquiry_id` jadi
// nullable (FK & UNIQUE index tetap aman, MySQL izinkan banyak NULL di
// unique index). `customer_id`/`product_name_draft` menampung identitas utk
// mode INSTANT (mode INTEGRATED snapshot dari inquiry saat dokumen dibuat,
// konsisten prinsip snapshot CLAUDE.md SS4). `reversed_at/by` = audit tombol
// "Reverse" (INSTANT -> INTEGRATED via App\Services\Costing\ReverseCostingService).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing', function (Blueprint $table) {
            $table->string('mode', 20)->default('INTEGRATED')->after('inquiry_id')
                ->comment('INSTANT|INTEGRATED. INSTANT = kalkulator standalone tanpa inquiry');
            $table->unsignedBigInteger('customer_id')->nullable()->after('mode')
                ->comment('LOGICAL -> mst.cust_customer. INSTANT: diisi manual. INTEGRATED: snapshot dari inquiry');
            $table->string('product_name_draft', 150)->nullable()->after('customer_id')
                ->comment('INSTANT: nama produk bebas ketik. INTEGRATED: snapshot inquiry.product_draft_name');
            $table->timestamp('reversed_at')->nullable()->after('published_flag')
                ->comment('Diisi saat tombol Reverse dieksekusi (INSTANT -> INTEGRATED)');
            $table->string('reversed_by', 100)->nullable()->after('reversed_at');
            $table->index('mode', 'idx_costing_mode');
        });

        // doctrine/dbal tidak terpasang (lihat memory project) -- ubah nullability
        // pakai raw SQL, bukan Blueprint::change().
        DB::connection('tm2sdi')->statement(
            "ALTER TABLE iqp_costing MODIFY inquiry_id BIGINT UNSIGNED NULL COMMENT 'FK -> iqp_inquiry, NULL untuk mode INSTANT'"
        );

        // Backfill eksplisit (jaga-jaga kalau DEFAULT tidak retroaktif di baris lama).
        DB::connection('tm2sdi')->table('iqp_costing')->update(['mode' => 'INTEGRATED']);
    }

    public function down(): void
    {
        DB::connection('tm2sdi')->statement(
            "ALTER TABLE iqp_costing MODIFY inquiry_id BIGINT UNSIGNED NOT NULL COMMENT 'FK -> iqp_inquiry'"
        );

        Schema::connection('tm2sdi')->table('iqp_costing', function (Blueprint $table) {
            $table->dropIndex('idx_costing_mode');
            $table->dropColumn(['mode', 'customer_id', 'product_name_draft', 'reversed_at', 'reversed_by']);
        });
    }
};
