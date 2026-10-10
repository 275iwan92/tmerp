<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// The officer can take a Work Order out of die (mall) grouping; the engine then plans it without the same-die preference. Dummy production database first (--env=mockup).
return new class extends Migration
{
    public function up(): void
    {
        $prd = Schema::connection('tm2prd');
        if (! $prd->hasColumn('prd_wo', 'die_group_off')) {
            $prd->table('prd_wo', fn ($t) => $t->boolean('die_group_off')->default(false));
        }
    }

    public function down(): void
    {
        $prd = Schema::connection('tm2prd');
        if ($prd->hasColumn('prd_wo', 'die_group_off')) {
            $prd->table('prd_wo', fn ($t) => $t->dropColumn('die_group_off'));
        }
    }
};
