<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Aturan user 2026-10-01: dummy lama yang tidak relevan & bikin kacau DIHAPUS, diganti yang baru.
// Sampel kasar migration 2026_09_17_000002 (IQUAT260901-903, product UAT-ALSAMSI-TOP/UAT-OSIMA/UAT-ALSAMSI-SIG:
// ukuran & bahan karangan, tanpa dieline) sudah digantikan UatMasterCardSeeder (IQ99MC0001-05, dari master card asli).
// Ikut dihapus: customer karangan "AL SAMSI - SARUNG TENUN" (customer aslinya PAK MUKRI) bila tak dipakai data lain.
// Tidak bisa dikembalikan (down kosong) — sumber aslinya tetap ada di migration 2026_09_17_000002.
return new class extends Migration
{
    private const INQUIRIES = ['IQUAT260901', 'IQUAT260902', 'IQUAT260903'];

    private const PRODUCTS = ['UAT-ALSAMSI-TOP', 'UAT-OSIMA', 'UAT-ALSAMSI-SIG'];

    public function up(): void
    {
        $sdi = DB::connection('tm2sdi');
        $mst = DB::connection('tm2mst');

        $inquiries = $sdi->table('iqp_inquiry')->whereIn('inquiry_no', self::INQUIRIES)->pluck('id_inquiry');
        $iqpParts = $sdi->table('iqp_part')->whereIn('inquiry_id', $inquiries)->pluck('id_part');
        $products = $mst->table('prod_product')->whereIn('product_code', self::PRODUCTS)->pluck('id_product');
        $prodParts = $mst->table('prod_part')->whereIn('product_id', $products)->orWhereIn('mastercard_code', self::PRODUCTS)->pluck('id_part');
        $groups = $mst->table('prod_layout_group')->whereIn('product_id', $products)->pluck('id_layout_group');
        $customers = $mst->table('cust_customer')->where('customer_name', 'AL SAMSI - SARUNG TENUN')->pluck('id_customer');

        foreach (['iqp_part_material', 'iqp_part_finishing', 'iqp_part_process', 'iqp_part_ink', 'iqp_layout'] as $t) {
            $sdi->table($t)->whereIn('part_id', $iqpParts)->delete();
        }
        $sdi->table('iqp_part')->whereIn('id_part', $iqpParts)->update(['parent_part_id' => null]);
        $sdi->table('iqp_part')->whereIn('id_part', $iqpParts)->delete();
        $sdi->table('iqp_tier')->whereIn('inquiry_id', $inquiries)->delete();
        $sdi->table('iqp_inquiry')->whereIn('id_inquiry', $inquiries)->delete();

        $mst->table('prod_part_attachment')->whereIn('id_layout_group', $groups)->orWhereIn('part_id', $prodParts)->delete();
        $mst->table('prod_layout_group_part')->whereIn('id_layout_group', $groups)->delete();
        $mst->table('prod_layout_group')->whereIn('id_layout_group', $groups)->delete();
        foreach (['prod_part_material', 'prod_part_ink', 'prod_part_process', 'prod_part_finishing', 'prod_part_artwork'] as $t) {
            $mst->table($t)->whereIn('part_id', $prodParts)->delete();
        }
        $mst->table('prod_part')->whereIn('id_part', $prodParts)->update(['parent_part_id' => null]);
        $mst->table('prod_part')->whereIn('id_part', $prodParts)->delete();
        $mst->table('prod_product_acc')->whereIn('product_id', $products)->delete();
        $mst->table('prod_product')->whereIn('id_product', $products)->delete();

        foreach ($customers as $c) {
            $used = $sdi->table('iqp_inquiry')->where('customer_id', $c)->exists() || $sdi->table('quo_quotation')->where('customer_id', $c)->exists()
                || $sdi->table('co_order')->where('customer_id', $c)->exists() || $mst->table('prod_product')->where('customer_id', $c)->exists();
            if (! $used) {
                foreach (['cust_customer_bank', 'cust_customer_branch', 'cust_customer_contact'] as $t) {
                    $mst->table($t)->where('customer_id', $c)->delete();
                }
                $mst->table('cust_customer')->where('id_customer', $c)->delete();
            }
        }

        foreach ($iqpParts as $id) {
            Storage::disk('public')->deleteDirectory("inquiry/{$id}");
        }
        foreach ($prodParts as $id) {
            Storage::disk('public')->deleteDirectory("product/{$id}");
        }
    }

    public function down(): void {}
};
