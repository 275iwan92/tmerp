<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Worklist Imposition Engine & Product Costing (inquiry/imposition-list.blade.php,
// inquiry/costing-list.blade.php) baru dibangun tapi ke-4 inquiry test yang ada
// semuanya masih DRAFT -- grid tampil kosong, tidak ada yang bisa dites/didemokan.
// feedback_keep_dummy_data: layar baru wajib ada data uji. Bukan bikin data
// fiktif baru -- cukup majukan 2 inquiry test yang SUDAH punya part (IQ2600001,
// IQ2600002) ke status SUBMITTED, persis efek yang sama seperti Sales
// memanggil confirm() sungguhan di inquiry/form.blade.php.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sdi')->table('iqp_inquiry')
            ->whereIn('inquiry_no', ['IQ2600001', 'IQ2600002'])
            ->update([
                'is_confirmed' => true,
                'sub_state' => 'SUBMITTED',
                'updated_by' => 'system',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sdi')->table('iqp_inquiry')
            ->whereIn('inquiry_no', ['IQ2600001', 'IQ2600002'])
            ->update([
                'is_confirmed' => false,
                'sub_state' => 'DRAFT',
                'updated_by' => 'system',
                'updated_at' => now(),
            ]);
    }
};
