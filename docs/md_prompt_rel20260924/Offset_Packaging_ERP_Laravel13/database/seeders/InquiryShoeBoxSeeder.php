<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InquiryShoeBoxSeeder extends Seeder
{
    public function run(): void
    {
        $inquiryId = DB::table('inquiries')->insertGetId([
            'inquiry_no' => 'INQ-2026-09-0842',
            'inquiry_type' => 'NEW',
            'inquiry_date' => now()->toDateString(),
            'customer_need_date' => now()->addDays(21)->toDateString(),
            'sales_pic' => 'Budi Santoso — Senior Packaging Account Exec',
            'source' => 'WHATSAPP',
            'customer' => 'PT MAP Aktif Adiperkasa Tbk (Footwear Division)',
            'product_name_draft' => 'Dus Sepatu Sneaker Original 2-Pc (Badan & Tutup Terpisah)',
            'product_category' => 'Rigid Gift Box',
            'product_type' => 'RIGID_BOX',
            'market_variant_id' => 'Dus Sepatu / Courier E-Flute Mailer',
            'sales_notes' => 'Dus sepatu sneakers 2-piece dengan pisau pon terpisah (Badan & Tutup).',
            'customer_provides_raw_material' => false,
            'status' => 'SAVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Part 1: Badan Sepatu
        DB::table('inquiry_parts')->insert([
            'inquiry_id' => $inquiryId,
            'part_name' => 'Badan Sepatu (Base Tray / Bottom)',
            'part_type' => 'Base / Tray',
            'component_type' => 'BODY_TRAY',
            'qty_per_set' => 1,
            'content_per_pack' => 1,
            'length' => 320.0,
            'width' => 200.0,
            'height' => 120.0,
            'glue_flap' => 15.0,
            'bleed' => 3.0,
            'tuck_flap' => 15.0,
            'unit' => 'mm',
            'notes' => 'Pola baki lipat roll-end tray 4 dinding tegak.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Part 2: Tutup Sepatu
        DB::table('inquiry_parts')->insert([
            'inquiry_id' => $inquiryId,
            'part_name' => 'Tutup Sepatu (Top Lid / Cover)',
            'part_type' => 'Lid / Cover',
            'component_type' => 'LID_COVER',
            'qty_per_set' => 1,
            'content_per_pack' => 1,
            'length' => 325.0,
            'width' => 205.0,
            'height' => 35.0,
            'glue_flap' => 15.0,
            'bleed' => 3.0,
            'tuck_flap' => 15.0,
            'unit' => 'mm',
            'notes' => 'Tutup terpisah dengan toleransi +5mm agar nyaman dipasang.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tiers
        foreach ([1000, 3000, 5000, 10000] as $idx => $qty) {
            DB::table('inquiry_tiers')->insert([
                'inquiry_id' => $inquiryId,
                'tier_number' => $idx + 1,
                'qty' => $qty,
                'is_primary' => ($idx === 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
