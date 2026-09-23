<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keputusan user 2026-09-23 (backlog01 UI-09):
// - Nama perusahaan "Telasih Makmur" dipakai di seluruh aplikasi & cetakan.
// - Nama terdaftar "CV. Langgeng Jaya Makmur" hanya PILIHAN saat mencetak
//   dokumen ber-PPN (tidak disimpan), disertai NPWP a.n. nama terdaftar.
// - CO punya flag PPN (default dari Inquiry asal / profil pajak customer,
//   boleh diubah saat input); Delivery Order/Surat Jalan ikut CO-nya.
return new class extends Migration
{
    // NPWP asli menyusul dari user -- pola nol ini sengaja jelas palsu.
    private const DUMMY_NPWP = '00.000.000.0-000.000';

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach ([
            ['COMPANY_NAME', 'Telasih Makmur', 'Nama perusahaan -- dipakai di seluruh aplikasi & kop cetak'],
            ['COMPANY_LEGAL_NAME', 'CV. Langgeng Jaya Makmur', 'Nama terdaftar (badan hukum) -- pilihan kop saat mencetak dokumen ber-PPN'],
        ] as [$key, $text, $desc]) {
            $sys->table('sys_constant')->updateOrInsert(
                ['constant_key' => $key],
                ['category_code' => 'BRANDING', 'constant_group' => 'COMPANY', 'constant_text' => $text,
                    'description' => $desc, 'activate' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        DB::connection('tm2mst')->table('org_company')->where('company_code', 'TM')->whereNull('npwp_number')
            ->update(['npwp_number' => self::DUMMY_NPWP, 'updated_at' => now()]);

        Schema::connection('tm2sdi')->table('co_order', function (Blueprint $t) {
            $t->boolean('is_ppn')->default(false)->after('currency_code');
        });
        Schema::connection('tm2sdi')->table('do_delivery', function (Blueprint $t) {
            $t->boolean('is_ppn')->default(false)->after('do_type');
        });

        // Nilai awal CO: Inquiry asal ber-PPN -> PPN; tanpa Inquiry -> profil pajak customer.
        DB::connection('tm2sdi')->statement("
            UPDATE tm2sdi.co_order o
            LEFT JOIN tm2mst.cust_customer c ON c.id_customer = o.customer_id
            SET o.is_ppn = CASE
                WHEN EXISTS (SELECT 1 FROM tm2sdi.co_order_item i JOIN tm2sdi.iqp_inquiry q ON q.id_inquiry = i.inquiry_id
                             WHERE i.order_id = o.id_order) THEN
                    EXISTS (SELECT 1 FROM tm2sdi.co_order_item i JOIN tm2sdi.iqp_inquiry q ON q.id_inquiry = i.inquiry_id
                            WHERE i.order_id = o.id_order AND q.tax_scheme = 'PPN')
                ELSE COALESCE(c.tax_profile = 'PPN_11', 0)
            END
        ");

        // DO ikut CO-nya (DO tanpa CO, mis. kirim ke subkon, tetap non-PPN).
        DB::connection('tm2sdi')->statement("
            UPDATE tm2sdi.do_delivery d
            SET d.is_ppn = EXISTS (SELECT 1 FROM tm2sdi.do_line l JOIN tm2sdi.co_order o ON o.id_order = l.co_order_id
                                   WHERE l.delivery_id = d.id_delivery AND o.is_ppn = 1)
        ");
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('do_delivery', fn (Blueprint $t) => $t->dropColumn('is_ppn'));
        Schema::connection('tm2sdi')->table('co_order', fn (Blueprint $t) => $t->dropColumn('is_ppn'));
        DB::connection('tm2mst')->table('org_company')->where('npwp_number', self::DUMMY_NPWP)->update(['npwp_number' => null]);
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['COMPANY_NAME', 'COMPANY_LEGAL_NAME'])->delete();
    }
};
