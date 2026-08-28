<?php

namespace Database\Seeders;

use App\Models\Sdi\IqpType;
use Illuminate\Database\Seeder;

class IqpTypeSeeder extends Seeder
{
    /**
     * Lookup tipe inquiry (iqp_type). DDL Tier 3A hanya membuat strukturnya,
     * baris data belum di-seed — tanpa ini insert iqp_inquiry gagal FK (fk_inq_type).
     */
    public function run(): void
    {
        $types = [
            ['type_code' => 'NEW', 'type_label' => 'Produk Baru'],
            ['type_code' => 'RECALC', 'type_label' => 'Hitung Ulang Harga'],
            ['type_code' => 'REVISE', 'type_label' => 'Ubah Spesifikasi'],
        ];

        foreach ($types as $type) {
            IqpType::query()->updateOrCreate(
                ['type_code' => $type['type_code']],
                ['type_label' => $type['type_label'], 'activate' => true]
            );
        }
    }
}
