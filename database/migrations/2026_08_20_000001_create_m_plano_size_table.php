<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Master ukuran plano tersedia, dipakai Imposition Engine (docs/mockups/inquiry_imposition.html)
// untuk saran "plano terbaik". Baru — belum ada di TIER_3A manapun sebelum ini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('m_plano_size', function (Blueprint $table) {
            $table->increments('id_plano_size');
            $table->string('plano_name', 30)->comment('Mis. 65x100, 79x109');
            $table->decimal('width_mm', 10, 2);
            $table->decimal('height_mm', 10, 2);
            $table->smallInteger('sort_order')->unsigned()->default(999);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        $db = DB::connection('tm2mst');
        $now = now();

        $sizes = [
            ['plano_name' => '65x100', 'width_mm' => 650, 'height_mm' => 1000, 'sort_order' => 10],
            ['plano_name' => '61x86', 'width_mm' => 610, 'height_mm' => 860, 'sort_order' => 20],
            ['plano_name' => '72x102', 'width_mm' => 720, 'height_mm' => 1020, 'sort_order' => 30],
            ['plano_name' => '79x109', 'width_mm' => 790, 'height_mm' => 1090, 'sort_order' => 40],
            ['plano_name' => '89x125', 'width_mm' => 890, 'height_mm' => 1250, 'sort_order' => 50],
        ];

        foreach ($sizes as $s) {
            $db->table('m_plano_size')->insert($s + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('m_plano_size');
    }
};
