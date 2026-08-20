<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// sys_constant sudah ada fisik di MySQL (7 baris COSTING) tapi TIDAK PERNAH
// tercatat migration apapun -- fresh install akan gagal karena tabelnya tak
// pernah dibuat lewat Laravel. Guard hasTable() supaya aman di kedua kondisi:
// DB yang sudah punya tabel ini (live sekarang) maupun DB baru dari nol.
//
// Nilai BANK_* sengaja kosong (bukan angka rekening karangan) -- dokumen
// Quotation (docs/mockups/quotation_mockup.html) butuh info transfer, tapi
// tidak ada sumber data rekening asli di database manapun. Admin isi manual
// lewat sys_constant nanti; sampai diisi, Quotation tampilkan placeholder.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tm2mst')->hasTable('sys_constant')) {
            Schema::connection('tm2mst')->create('sys_constant', function (Blueprint $table) {
                $table->id('id_constant');
                $table->string('constant_key', 60)->unique();
                $table->string('constant_group', 30)->nullable();
                $table->decimal('constant_number', 18, 6)->nullable();
                $table->text('constant_text')->nullable();
                $table->string('description', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->string('created_by', 100)->nullable();
                $table->string('updated_by', 100)->nullable();
            });
        }

        $db = DB::connection('tm2mst');
        $now = now();

        $constants = [
            ['constant_key' => 'BANK_NAME', 'constant_group' => 'PAYMENT', 'constant_text' => null, 'description' => 'Nama bank tujuan transfer di dokumen Quotation'],
            ['constant_key' => 'BANK_ACCOUNT_NAME', 'constant_group' => 'PAYMENT', 'constant_text' => null, 'description' => 'Nama pemegang rekening di dokumen Quotation'],
            ['constant_key' => 'BANK_ACCOUNT_NO', 'constant_group' => 'PAYMENT', 'constant_text' => null, 'description' => 'Nomor rekening di dokumen Quotation'],
        ];

        foreach ($constants as $c) {
            $db->table('sys_constant')->updateOrInsert(
                ['constant_key' => $c['constant_key']],
                $c + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('sys_constant')
            ->whereIn('constant_key', ['BANK_NAME', 'BANK_ACCOUNT_NAME', 'BANK_ACCOUNT_NO'])
            ->delete();
    }
};
