<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Alur Inquiry -> ProDev -> ProCost lewat tiga tanda di SATU tabel (user 2026-10-02, rulebook A.20):
//   stat_confirm_sales  (dulu stat_confirm) = Confirm oleh Sales     -> NEW / REVISE mengalir ke ProDev, RECALC langsung ke ProCost
//   stat_confirm_prodev                     = semua part di-Confirm ProDev -> mengalir ke ProCost
//   stat_confirm_procost                    = costing published      -> kembali ke Sales, siap Quotation
// Tiap bagian membaca tabel yang sama dengan saringan (jenis AND tanda) berbeda. Siapa & kapan dicatat per tanda.
return new class extends Migration
{
    private const AFTER_PRODEV = ['PRICE_CALCULATED', 'PRICE_APPROVED', 'QUOTED', 'PROVING', 'PROVE_APPROVED', 'CONVERTED'];

    public function up(): void
    {
        $schema = Schema::connection('tm2sdi');
        if ($schema->hasColumn('iqp_inquiry', 'stat_confirm')) {
            $schema->table('iqp_inquiry', fn (Blueprint $t) => $t->renameColumn('stat_confirm', 'stat_confirm_sales'));
        }
        if (! $schema->hasColumn('iqp_inquiry', 'stat_confirm_prodev')) {
            $schema->table('iqp_inquiry', function (Blueprint $t) {
                $t->string('confirm_sales_by', 100)->nullable()->after('stat_confirm_sales');
                $t->dateTime('confirm_sales_at')->nullable()->after('confirm_sales_by');
                $t->boolean('stat_confirm_prodev')->default(false)->after('confirm_sales_at');
                $t->string('confirm_prodev_by', 100)->nullable()->after('stat_confirm_prodev');
                $t->dateTime('confirm_prodev_at')->nullable()->after('confirm_prodev_by');
                $t->boolean('stat_confirm_procost')->default(false)->after('confirm_prodev_at');
                $t->string('confirm_procost_by', 100)->nullable()->after('stat_confirm_procost');
                $t->dateTime('confirm_procost_at')->nullable()->after('confirm_procost_by');
                $t->index(['inquiry_type', 'stat_confirm_sales', 'stat_confirm_prodev', 'stat_confirm_procost'], 'idx_inq_stage');
            });
        }

        $inq = fn () => DB::connection('tm2sdi')->table('iqp_inquiry');
        $inq()->where('stat_confirm_sales', 1)->whereNull('confirm_sales_at')
            ->update(['confirm_sales_by' => DB::raw('updated_by'), 'confirm_sales_at' => DB::raw('updated_at')]);

        // ProCost selesai = costing published (sumber kebenaran yang dipakai Quotation sejak awal).
        $published = DB::connection('tm2sdi')->table('iqp_costing')->where('published_flag', 1)->whereNotNull('inquiry_id')
            ->get(['inquiry_id', 'approved_by', 'approved_at'])->keyBy('inquiry_id');
        foreach ($published as $id => $c) {
            $inq()->where('id_inquiry', $id)->update(['stat_confirm_procost' => 1, 'confirm_procost_by' => $c->approved_by ?: 'migration',
                'confirm_procost_at' => $c->approved_at ?: now()]);
        }

        // ProDev selesai (data lama): inquiry NEW / REVISE yang sudah lewat tahap ProDev -- sudah punya costing, statusnya sudah
        // di atas SUBMITTED, atau semua part-nya punya layout terbaru CONFIRMED.
        // Costing tanpa inquiry (Pro-Cost Instant) dilewati.
        $costed = DB::connection('tm2sdi')->table('iqp_costing')->whereNotNull('inquiry_id')->pluck('inquiry_id')->flip();
        $latest = DB::connection('tm2mst')->table('prod_layout_group_part as gp')->join('prod_layout_group as g', 'g.id_layout_group', '=', 'gp.id_layout_group')
            ->orderBy('g.id_layout_group')->get(['gp.part_id', 'g.status'])->pluck('status', 'part_id');
        $parts = DB::connection('tm2sdi')->table('iqp_part')->where(fn ($q) => $q->whereNull('part_kind')->orWhere('part_kind', '<>', 'ASSEMBLY'))
            ->get(['inquiry_id', 'prod_part_id'])->groupBy('inquiry_id');
        foreach ($inq()->whereIn('inquiry_type', ['NEW', 'REVISE'])->where('stat_confirm_sales', 1)->where('stat_confirm_prodev', 0)->get() as $i) {
            $own = $parts->get($i->id_inquiry, collect());
            $layoutDone = $own->isNotEmpty() && $own->every(fn ($p) => $p->prod_part_id && ($latest[$p->prod_part_id] ?? null) === 'CONFIRMED');
            if ($layoutDone || $costed->has($i->id_inquiry) || in_array($i->sub_state, self::AFTER_PRODEV, true)) {
                $inq()->where('id_inquiry', $i->id_inquiry)->update(['stat_confirm_prodev' => 1, 'confirm_prodev_by' => 'migration', 'confirm_prodev_at' => $i->updated_at ?: now()]);
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tm2sdi');
        if ($schema->hasColumn('iqp_inquiry', 'stat_confirm_prodev')) {
            $schema->table('iqp_inquiry', function (Blueprint $t) {
                $t->dropIndex('idx_inq_stage');
                $t->dropColumn(['confirm_sales_by', 'confirm_sales_at', 'stat_confirm_prodev', 'confirm_prodev_by', 'confirm_prodev_at',
                    'stat_confirm_procost', 'confirm_procost_by', 'confirm_procost_at']);
            });
        }
        if ($schema->hasColumn('iqp_inquiry', 'stat_confirm_sales')) {
            $schema->table('iqp_inquiry', fn (Blueprint $t) => $t->renameColumn('stat_confirm_sales', 'stat_confirm'));
        }
    }
};
