<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Penanda tangan cetakan Sales Invoice dan Payment Voucher (backlog01 UI-19): konstanta kategori PRINT, grup SIGNER, sama
// polanya dengan 2026_10_02_000030. Kosong = garis untuk tanda tangan tangan; diisi di Setup > Constants.
return new class extends Migration
{
    private const DOCS = ['INV' => 'Sales Invoice', 'PVC' => 'Payment Voucher'];

    private const ROLES = ['APPROVER' => 'Approved By', 'ACKNOWLEDGER' => 'Acknowledged By'];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::DOCS as $code => $doc) {
            foreach (self::ROLES as $role => $label) {
                $key = "SIGN_{$code}_{$role}";
                if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                    $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRINT', 'constant_group' => 'SIGNER', 'constant_text' => null,
                        'description' => "Name printed under \"{$label}\" on the {$doc} print. Empty = blank line to sign by hand.",
                        'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
                }
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['SIGN_INV_APPROVER', 'SIGN_INV_ACKNOWLEDGER', 'SIGN_PVC_APPROVER', 'SIGN_PVC_ACKNOWLEDGER'])->delete();
    }
};
