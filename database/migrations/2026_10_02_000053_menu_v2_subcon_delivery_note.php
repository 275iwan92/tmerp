<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// backlog02 §19.1: layar Surat Jalan Subkon v2 (subcon-delivery-note) -- baris menu baru di Sales > Delivery, setelah
// Delivery Notes; konstanta penanda tangan cetakannya (kategori PRINT, grup SIGNER, kosong = garis tanda tangan tangan).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const SIGNERS = ['SIGN_SDN_APPROVER' => 'Approved By', 'SIGN_SDN_ACKNOWLEDGER' => 'Acknowledged By'];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $notes = $sys->table('sys_app_menu')->where('code', 'SALES_DN')->first();
        if ($notes && ! $sys->table('sys_app_menu')->where('code', 'SALES_DN_SUBCON')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'parent_id' => $notes->parent_id, 'code' => 'SALES_DN_SUBCON', 'label' => 'Subcontract Delivery Note', 'icon' => 'fa-solid fa-dolly',
                'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:subcon-delivery-note', 'sort_order' => $notes->sort_order + 5,
                'activate' => 1, 'required_object' => $notes->required_object, 'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
            ]);
        }
        foreach (self::SIGNERS as $key => $label) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRINT', 'constant_group' => 'SIGNER', 'constant_text' => null,
                    'description' => "Name printed under \"{$label}\" on the Subcontract Delivery Note print. Empty = blank line to sign by hand.",
                    'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->where('code', 'SALES_DN_SUBCON')->delete();
        $sys->table('sys_constant')->whereIn('constant_key', array_keys(self::SIGNERS))->delete();
    }
};
