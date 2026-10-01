<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Penanda tangan cetakan dokumen = konstanta yang mudah diubah (keputusan user 2026-10-01, rulebook C.24):
// kategori PRINT, grup SIGNER, satu baris per dokumen per peran. Diubah di Settings > Constants.
// Kosong = garis kosong untuk tanda tangan tangan. Pembuat TIDAK disimpan di sini (selalu user yang login); bila dokumen
// disetujui lewat approval sistem, nama penyetuju yang sebenarnya dicetak dan konstanta APPROVER tidak dipakai.
// Purchase Order: Menyetujui = Hendra, Mengetahui = Wiwik (disebut user 2026-10-01); dokumen lain menunggu nama dari user.
return new class extends Migration
{
    private const DOCS = [
        'INQ' => 'Inquiry', 'QUO' => 'Quotation', 'CO' => 'Customer Order', 'CPDEL' => 'Customer Plan Delivery', 'DO' => 'Delivery Order',
        'DN' => 'Delivery Note', 'POD' => 'Proof of Delivery', 'PR' => 'Purchase Requisition', 'PQ' => 'Purchase Quotation', 'PO' => 'Purchase Order',
        'SPB' => 'Goods Receipt', 'MB' => 'Material Booking', 'MR' => 'Maintenance Request', 'MWO' => 'Maintenance Work Order', 'PC' => 'Petty Cash Voucher',
    ];

    private const ROLES = ['APPROVER' => 'Approved By', 'ACKNOWLEDGER' => 'Acknowledged By'];

    private const NAMES = ['SIGN_PO_APPROVER' => 'Hendra', 'SIGN_PO_ACKNOWLEDGER' => 'Wiwik'];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_constant_category')->where('code', 'PRINT')->exists()) {
            $sys->table('sys_constant_category')->insert(['code' => 'PRINT', 'label' => 'Print-out', 'description' => 'Printed documents: signer names per document',
                'sort_order' => 80, 'activate' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (self::DOCS as $code => $doc) {
            foreach (self::ROLES as $role => $label) {
                $key = "SIGN_{$code}_{$role}";
                if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                    $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRINT', 'constant_group' => 'SIGNER', 'constant_text' => self::NAMES[$key] ?? null,
                        'description' => "Name printed under \"{$label}\" on the {$doc} print. Empty = blank line to sign by hand.",
                        'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
                }
            }
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_constant')->where('category_code', 'PRINT')->where('constant_group', 'SIGNER')->delete();
        $sys->table('sys_constant_category')->where('code', 'PRINT')->delete();
    }
};
