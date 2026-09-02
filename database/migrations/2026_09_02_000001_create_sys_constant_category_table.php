<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kategori tampilan utk layar Configuration (docs/configurationstandard.md) --
// level di ATAS constant_group yang sudah ada (TAX/PAYMENT/COSTING/PROCUREMENT/
// INV) supaya konstanta bisa ditampilkan tabular per kategori (keputusan user
// 2026-09-02) tanpa mengganti constant_group yang sudah dipakai kode lain.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->create('sys_constant_category', function (Blueprint $table) {
            $table->string('code', 30)->primary();
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('activate')->default(true);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['code' => 'BRANDING', 'label' => 'Identitas Aplikasi', 'description' => 'Nama app, tagline, logo, font', 'sort_order' => 10],
            ['code' => 'FINANCE', 'label' => 'Keuangan', 'description' => 'PPN, PPh, info bank/transfer', 'sort_order' => 20],
            ['code' => 'PRODUCTION', 'label' => 'Produksi & Costing', 'description' => 'Parameter costing (rim, plano, film, dst)', 'sort_order' => 30],
            ['code' => 'PROCUREMENT', 'label' => 'Pengadaan', 'description' => 'Toleransi terima, dst', 'sort_order' => 40],
            ['code' => 'INVENTORY', 'label' => 'Inventory', 'description' => 'Batas adjustment, alasan stok, dst', 'sort_order' => 50],
        ];
        foreach ($rows as $r) {
            Schema::connection('tm2sys')->getConnection()->table('sys_constant_category')->insert($r + ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->dropIfExists('sys_constant_category');
    }
};
