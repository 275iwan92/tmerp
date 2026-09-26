<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Quotation New lewat "Choose inquiry window" (user 2026-09-26): kontak customer,
// PIC sales (dari inquiry) dan deadline (tanggal dokumen + 14 hari) ikut tersimpan
// & bisa di-override officer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('quo_quotation', function (Blueprint $table) {
            $table->string('attn_contact', 150)->nullable()->after('customer_id');
            $table->string('sales_pic', 100)->nullable()->after('attn_contact');
            $table->date('deadline_date')->nullable()->after('valid_until');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('quo_quotation', fn (Blueprint $table) => $table->dropColumn(['attn_contact', 'sales_pic', 'deadline_date']));
    }
};
