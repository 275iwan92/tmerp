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

    // Baris lookup customer (bentuk tm2mst.cust_customer).
    public static function customerRows(): array
    {
        return array_map(fn ($n, $i) => ['id_customer' => 101 + $i, 'customer_code' => sprintf('C%04d', 101 + $i), 'customer_name' => $n], self::customers(), array_keys(self::customers()));
    }

    // Bentuk tm2mst.sales_person (nama fiktif).
    public static function salesPersons(): array
    {
        return [['id_sales_person' => 1, 'sales_name' => 'Andi Pratama', 'sales_type' => 'INTERNAL'], ['id_sales_person' => 2, 'sales_name' => 'Citra Rahma', 'sales_type' => 'INTERNAL'],
            ['id_sales_person' => 3, 'sales_name' => 'Maya Lestari', 'sales_type' => 'INTERNAL'], ['id_sales_person' => 4, 'sales_name' => 'Budi Agen Jatim', 'sales_type' => 'EXTERNAL']];
    }

    // Ekspor baca-saja tm2mst (bahan kertas, jenis finishing, tipe multi-part): resources/data/sales_masters.json.
    public static function masters(): array
    {
        static $m;

        return $m ??= json_decode(file_get_contents(resource_path('data/sales_masters.json')), true);
    }

    // Tipe produk + dieline_spec + parameter + ukuran pasaran (ekspor baca-saja tm2mst.prod_type*, milik paket mockup-parts).
    public static function dielineTypes(): array
    {
        static $t;

        return $t ??= json_decode(file_get_contents(base_path('../packages/mockup-parts/data/dieline_types.json')), true);
    }

    // Satu dokumen inkuiri lengkap (header + tier + part) untuk dibuka dari worklist.
    public static function inquiryDoc(int $id): ?array
    {
        $row = collect(self::inquiries())->firstWhere('id', $id);
        if (! $row) {
            return null;
        }
        $types = self::dielineTypes();
        $code = isset($types[$row['part_type']]) ? $row['part_type'] : 'CARD_BOX';
        $mat = self::masters()['materials'][$id % 60];
        $multi = in_array($code, ['BOX_WITH_LID', 'ROUND_BOX', 'SHALLOW_BOX'], true);
        $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $row['product_draft_name']));
        $part = fn (string $role) => ['part_name' => $slug.'-'.$role, 'part_role' => $role, 'part_type' => 'DC', 'qty_per_sku' => 1, 'pack_qty' => 400,
            'dims' => array_map(fn ($p) => $p['default'], $types[$code]['params']), 'cfg_color' => '4/4', 'cfg_ink' => '',
            'materials' => [['material' => $mat['material_id'], 'material_label' => $mat['material_name'], 'brand' => $mat['brand'], 'category' => $mat['category'], 'is_primary' => true]],
            'finishings' => $id % 2 ? [['finishing_type_id' => 1, 'attribute' => '']] : [], 'sent' => in_array($row['sub_state'], ['PRICE_APPROVED', 'QUOTED', 'PROVING', 'PROVE_APPROVED', 'CONVERTED'], true)];
        $cust = collect(self::customerRows())->firstWhere('customer_name', $row['customer']);

        return $row + [
            'source' => ['EMAIL', 'WA', 'PHONE', 'VISIT'][$id % 4], 'customer_id' => $cust['id_customer'] ?? null, 'sales_note' => '', 'material_source' => 'OWN',
            'type_code' => $code, 'stat_cancel' => $row['sub_state'] === 'CANCELLED',
            'tiers' => [['qty' => $row['qty'], 'is_primary' => true], ['qty' => $row['qty'] * 2, 'is_primary' => false]],
            'parts' => $multi ? [$part('body'), $part('lid')] : [$part('monoform')],
            'toolings' => [], 'external_costs' => [],
        ];
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
