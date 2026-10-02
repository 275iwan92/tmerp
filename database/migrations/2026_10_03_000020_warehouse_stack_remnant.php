<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tumpukan (stack) dan sisa rim (user 2026-10-03: "lokasi penempatan barang selain posisi X dan Y juga ada stack, terutama
// untuk barang yang tersisa 1 atau 2 rim"; "tumpukan tidak jelas, oleh sebab itu butuh di monitor"; "sisa ditumpuk begitu
// saja dalam satu palet"). Rancangan: docs/warehouselayout_tier1.md bagian 15.
//   - inv_pallet.stack_no  = nomor tumpukan satuan itu di bin-nya, 1 = paling bawah (rak: tingkat rak sudah menjadi bin).
//   - inv_pallet.unit_kind = PALLET | PACK (pak lepas: sisa rim / dus / kaleng). Sisa = PACK.
//   - wh_zone.max_stack    = paling banyak berapa palet ditumpuk di satu tapak; kosong = tidak dibatasi, hanya dipantau.
// Tabel stok, kartu stok dan wh_loc tidak berubah. Satuan yang sudah ada diberi nomor: tertua di bawah.
return new class extends Migration
{
    private const BY = 'claude (v2 build)';

    private const CONSTANTS = [
        'WH_REMNANT_MAX_SHEETS' => [1000, 'Sheet goods (paper, ambri, sheet): a labelled unit with this many sheets or fewer is a remnant pack (1 ream = 500 sheets).'],
        'WH_PALLET_BASE_CM' => [15, 'Height of one wooden pallet (cm), added to the estimated height of every pallet in a stack.'],
    ];

    private const UI = [
        'Stack Monitor' => 'Stack Monitor', 'Stack' => 'Tumpukan', 'Stack height' => 'Tinggi tumpukan', 'Stack — top to bottom' => 'Tumpukan — atas ke bawah',
        'Pack' => 'Pak', 'Remnant' => 'Sisa', 'Old remnant' => 'Sisa lama', 'Buried' => 'Tertimbun', 'Remnant pile' => 'Tumpukan sisa', 'Stack not recorded' => 'Tumpukan belum dicatat',
        'Near height limit' => 'Hampir setinggi batas', 'Over stack limit' => 'Lebih dari batas tumpuk', 'units' => 'satuan', 'unit' => 'satuan', 'packs' => 'pak', 'above' => 'di atasnya',
        'not labelled' => 'belum berlabel', 'Move up' => 'Naikkan', 'Move down' => 'Turunkan', 'From pallet' => 'Dari palet', 'Unit kind' => 'Jenis satuan',
        'Automatic (remnant when at or below the limit)' => 'Otomatis (sisa bila di bawah atau sama dengan batas)', 'Pallet (full or opened)' => 'Palet (penuh atau terbuka)',
        'Pack (remnant: reams, boxes, cans)' => 'Pak (sisa: rim, dus, kaleng)', 'Now in bin' => 'Sekarang di bin', 'Stays in the same bin' => 'Tetap di bin yang sama',
        'Max. Stacked Pallets' => 'Maks. Palet Ditumpuk', 'Kind' => 'Jenis', 'Stack No.' => 'No. Tumpukan', 'Age (days)' => 'Umur (hari)', 'Height in Bin' => 'Tinggi di Bin', 'Mark' => 'Tanda',
        'Pallet / Pack No.' => 'No. Palet / Pak', 'Restacked' => 'Urutan tumpukan diubah',
        'The unit that should be used first is under :n other unit(s). Move those first.' => 'Satuan yang seharusnya dipakai lebih dulu berada di bawah :n satuan lain. Pindahkan dulu yang di atasnya.',
        ':n pallets are stacked here; this area allows :max.' => ':n palet ditumpuk di sini; area ini mengizinkan :max.',
        'Some stock in this bin has no label yet, so its place in the stack is not known.' => 'Sebagian stok di bin ini belum berlabel, jadi letaknya dalam tumpukan belum diketahui.',
        'This unit is already at the top.' => 'Satuan ini sudah paling atas.', 'This unit is already at the bottom.' => 'Satuan ini sudah paling bawah.',
        'Search bin, item, pallet...' => 'Cari bin, item, palet...',
        'Kind of the new units' => 'Jenis satuan baru',
        ':qty is at or below the remnant limit (:limit sheets): it is recorded as a remnant pack.' => ':qty sama dengan atau di bawah batas sisa (:limit lembar): dicatat sebagai pak sisa.',
        'Relabelled and moved to :bin (Stock Transfer :no).' => 'Dilabel ulang dan dipindah ke :bin (Stock Transfer :no).',
        'Relabelled. The move to :bin could not be done: :error' => 'Sudah dilabel ulang. Pemindahan ke :bin tidak bisa dilakukan: :error',
        'Remnant after use: moved to the remnant pile.' => 'Sisa sesudah pemakaian: dipindah ke tumpukan sisa.',
        'Type what is left on the pallet that was opened. At or below the remnant limit it becomes a remnant pack. If the remnant was put on another pile, choose that bin: a Stock Transfer is made and the pack goes on top.' => 'Ketik sisa di palet yang dibuka. Bila sama dengan atau di bawah batas sisa, ia menjadi pak sisa. Bila sisa itu ditaruh di tumpukan lain, pilih bin-nya: Stock Transfer dibuat dan pak itu menjadi paling atas.',
        'Stack 1 is the bottom. A new unit goes on top; when a unit leaves, the ones above move down by themselves.' => 'Tumpukan 1 = paling bawah. Satuan baru menjadi paling atas; saat satu satuan keluar, yang di atasnya turun sendiri.',
        'How many pallets may be stacked on one floor position; empty = no limit, the stack is only monitored.' => 'Berapa palet boleh ditumpuk di satu tapak lantai; kosong = tanpa batas, tumpukan hanya dipantau.',
        'Stock Units & Stack' => 'Satuan Stok & Tumpukan', 'Stack Order' => 'Urutan Tumpukan',
    ];

    public function up(): void
    {
        $inv = Schema::connection('tm2inv');
        $inv->table('inv_pallet', function (Blueprint $t) use ($inv) {
            if (! $inv->hasColumn('inv_pallet', 'unit_kind')) {
                $t->string('unit_kind', 10)->default('PALLET');           // PALLET | PACK (sisa: rim / dus / kaleng lepas)
            }
            if (! $inv->hasColumn('inv_pallet', 'stack_no')) {
                $t->unsignedSmallInteger('stack_no')->nullable();         // nomor tumpukan di bin itu, 1 = paling bawah
                $t->index(['location_id', 'stack_no']);
            }
        });
        $mst = Schema::connection('tm2mst');
        $mst->table('wh_zone', function (Blueprint $t) use ($mst) {
            if (! $mst->hasColumn('wh_zone', 'max_stack')) {
                $t->unsignedTinyInteger('max_stack')->nullable();         // palet per tapak; kosong = hanya dipantau
            }
        });

        // Satuan yang sudah ada: tertua di bawah (urut tanggal tiba), per bin.
        $db = DB::connection('tm2inv');
        $next = [];
        foreach ($db->table('inv_pallet')->where('status', 'ACTIVE')->whereNull('stack_no')->orderBy('arrived_at')->orderBy('id_pallet')->get(['id_pallet', 'location_id']) as $p) {
            $next[$p->location_id] ??= (int) $db->table('inv_pallet')->where('status', 'ACTIVE')->where('location_id', $p->location_id)->max('stack_no');
            $db->table('inv_pallet')->where('id_pallet', $p->id_pallet)->update(['stack_no' => ++$next[$p->location_id]]);
        }

        $sys = DB::connection('tm2sys');
        foreach (self::CONSTANTS as $key => [$value, $text]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'INVENTORY', 'constant_group' => 'WAREHOUSE', 'constant_number' => $value,
                    'description' => $text, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }

        // Menu Inventory > Warehouse > Stack Monitor (semua database).
        $menu = fn () => $sys->table('sys_app_menu');
        $parent = $menu()->where('code', 'INV_WH')->value('id');
        if ($parent && ! $menu()->where('code', 'INV_WH_STACK')->exists()) {
            $menu()->insert(['parent_id' => $parent, 'code' => 'INV_WH_STACK', 'label' => 'Stack Monitor', 'icon' => 'fa-solid fa-layer-group', 'menu_type' => 'item', 'object_type' => 'report',
                'target' => 'v2:stack-monitor', 'sort_order' => 30, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(),
                'created_at' => now(), 'doc_slug' => 'warehouselayout']);
        }

        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            foreach (self::UI as $en => $id) {
                $sys->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
                $key = $sys->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
                if ($key && ! $sys->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                    $sys->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
                }
            }
            Dictionary::flush();
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_WH_STACK')->delete();
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
        $mst = Schema::connection('tm2mst');
        $mst->table('wh_zone', fn (Blueprint $t) => $mst->hasColumn('wh_zone', 'max_stack') ? $t->dropColumn('max_stack') : null);
        $inv = Schema::connection('tm2inv');
        $inv->table('inv_pallet', function (Blueprint $t) use ($inv) {
            if ($inv->hasColumn('inv_pallet', 'stack_no')) {
                $t->dropIndex(['location_id', 'stack_no']);
                $t->dropColumn('stack_no');
            }
            if ($inv->hasColumn('inv_pallet', 'unit_kind')) {
                $t->dropColumn('unit_kind');
            }
        });
    }
};
