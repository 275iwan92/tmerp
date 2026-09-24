<?php

namespace App\Support\Data;

// Data contoh fiktif modul Sales (situs tanpa database). Deterministik: sama tiap muat.
// Bentuk baris = kolom tabel tujuannya (lihat tooltip Src di layar), supaya Fase 3 tinggal ganti sumber.
final class Sales
{
    public const STATES = ['DRAFT', 'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED', 'PRICE_APPROVED', 'QUOTED', 'PROVING', 'PROVE_APPROVED', 'CONVERTED', 'CANCELLED', 'LOST'];

    public const STATE_TONES = ['DRAFT' => 'neutral', 'WAITING_COST' => 'warn', 'SUBMITTED' => 'warn', 'PRICE_CALCULATED' => 'warn', 'PRICE_APPROVED' => 'info',
        'QUOTED' => 'accent', 'PROVING' => 'accent', 'PROVE_APPROVED' => 'accent', 'CONVERTED' => 'success', 'CANCELLED' => 'danger', 'LOST' => 'danger'];

    public const TYPE_TONES = ['NEW' => 'info', 'RECALC' => 'accent', 'REVISE' => 'warn'];

    public static function customers(): array
    {
        return ['CV. Sinar Kemasan', 'PT. Boga Rasa Nusantara', 'Toko Manis Jaya', 'PT. Kosmetika Indah', 'PT. Sari Roti Jatim', 'UD. Kopi Arjuno', 'PT. Farma Sehat', 'CV. Batik Tulis Madura'];
    }

    public static function inquiries(int $n = 42): array
    {
        $cust = self::customers();
        $products = [['Box Lipat 4/4 Duplex 310gsm', 'CARD_BOX'], ['Paper Bag Twist Handle', 'SHALLOW_BOX'], ['Label Stiker Botol 60ml', 'LABEL_STIKER'], ['Box Kosmetik Serum 30ml', 'RTE_BOX'],
            ['Dus Nasi Kotak Premium', 'STE_BOX'], ['Sleeve Kopi Drip', 'SLEEVE'], ['Hang Tag Batik', 'HANG_TAG'], ['Box Hampers Lebaran', 'BOX_WITH_LID'], ['Kalender Duduk 2027', 'KALENDER_DUDUK'],
            ['Brosur Promo A4 Lipat 3', 'BROSUR'], ['Box Obat Strip 10', 'CRASH_LOCK'], ['Tube Teh Celup', 'CYLINDER']];
        $states = ['DRAFT', 'DRAFT', 'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED', 'PRICE_APPROVED', 'PRICE_APPROVED', 'QUOTED', 'PROVING', 'PROVE_APPROVED', 'CONVERTED', 'CONVERTED', 'CANCELLED', 'LOST'];
        $types = ['NEW', 'NEW', 'NEW', 'RECALC', 'REVISE'];
        $rows = [];
        for ($i = 1; $i <= $n; $i++) {
            [$product, $ptype] = $products[($i * 5) % count($products)];
            $state = $states[($i * 3) % count($states)];
            $prospect = $i % 7 === 0;
            $date = date('Y-m-d', strtotime('2026-09-23 -'.(($n - $i) * 2).' days'));
            $rows[] = [
                'id' => 148 - $n + $i,
                'inquiry_no' => $state === 'DRAFT' ? null : sprintf('IQ26%05d', 100 + $i),
                'inquiry_date' => $date,
                'customer' => $prospect ? ['PT. Calon Sukses Makmur', 'Ibu Ratna (Katering)'][$i % 2] : $cust[($i * 3) % count($cust)],
                'is_prospect' => $prospect,
                'product_draft_name' => $product,
                'part_type' => $ptype,
                'qty' => [1000, 2500, 5000, 10000, 20000, 3000][$i % 6],
                'sub_state' => $state,
                'inquiry_type' => $types[$i % count($types)],
                'stat_confirm' => ! in_array($state, ['DRAFT', 'WAITING_COST'], true),
                'is_approved' => in_array($state, ['PRICE_APPROVED', 'QUOTED', 'PROVING', 'PROVE_APPROVED', 'CONVERTED'], true),
                'pic_sales' => ['Andi Pratama', 'Citra Rahma', 'Maya Lestari'][$i % 3],
                'deadline_quotation' => date('Y-m-d', strtotime($date.' +20 days')),
            ];
        }

        return array_reverse($rows);
    }
}
