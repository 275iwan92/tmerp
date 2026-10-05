<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Amounts are prefixed with the currency code (IDR), not the symbol (user 2026-10-05).
// The message texts changed in code; the dictionary rows follow so their translations stay attached.
return new class extends Migration
{
    private const PAIRS = ['Rp :' => 'IDR :', '> Rp 1 ' => '> IDR 1 '];

    public function up(): void
    {
        $this->swap(self::PAIRS);
    }

    public function down(): void
    {
        $this->swap(array_flip(self::PAIRS));
    }

    private function swap(array $pairs): void
    {
        foreach ($pairs as $from => $to) {
            DB::table('sys_dictionary')->where('source_en', 'like', '%'.$from.'%')
                ->update(['source_en' => DB::raw('REPLACE(source_en, '.DB::getPdo()->quote($from).', '.DB::getPdo()->quote($to).')')]);
            DB::table('sys_dictionary_lang')->where('text', 'like', '%'.$from.'%')
                ->update(['text' => DB::raw('REPLACE(text, '.DB::getPdo()->quote($from).', '.DB::getPdo()->quote($to).')')]);
        }
    }
};
