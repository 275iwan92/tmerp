<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Seed contoh nyata (bukan dihapus setelah QC, lihat memory
// feedback_keep_dummy_data) -- campuran INTERNAL & EXTERNAL spt diminta user.
return new class extends Migration
{
    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        $rows = [
            ['sales_name' => 'Johannes Iwan', 'sales_type' => 'INTERNAL', 'phone' => null],
            ['sales_name' => 'Siti Rahma', 'sales_type' => 'INTERNAL', 'phone' => '081200000001'],
            ['sales_name' => 'Budi Santoso', 'sales_type' => 'INTERNAL', 'phone' => '081200000002'],
            ['sales_name' => 'Agen Mitra Jaya (freelance)', 'sales_type' => 'EXTERNAL', 'phone' => '081300000001'],
            ['sales_name' => 'CV Perantara Cetak', 'sales_type' => 'EXTERNAL', 'phone' => '081300000002'],
        ];

        foreach ($rows as $row) {
            if ($mst->table('sales_person')->where('sales_name', $row['sales_name'])->exists()) {
                continue;
            }

            $mst->table('sales_person')->insert([
                ...$row,
                'activate' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('sales_person')->whereIn('sales_name', [
            'Johannes Iwan', 'Siti Rahma', 'Budi Santoso',
            'Agen Mitra Jaya (freelance)', 'CV Perantara Cetak',
        ])->delete();
    }
};
