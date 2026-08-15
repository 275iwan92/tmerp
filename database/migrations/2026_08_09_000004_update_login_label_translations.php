<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// login.html (Tier 4) sekarang menerima username ATAU email di kolom yang sama
// — label diperbarui, submit() login.blade.php ikut dicocokkan (lookup
// login OR email).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sys_translations')->where('trans_key', 'label_username')->where('lang_code', 'id')->update(['trans_value' => 'Username atau email']);
        DB::table('sys_translations')->where('trans_key', 'label_username')->where('lang_code', 'en')->update(['trans_value' => 'Username or email']);
    }

    public function down(): void
    {
        DB::table('sys_translations')->where('trans_key', 'label_username')->where('lang_code', 'id')->update(['trans_value' => 'Username']);
        DB::table('sys_translations')->where('trans_key', 'label_username')->where('lang_code', 'en')->update(['trans_value' => 'Username']);
    }
};
