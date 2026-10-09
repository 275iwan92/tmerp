<?php

use App\Support\DbName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Landed cost masuk kartu stok (2026-10-10): baris kartu khusus nilai (doc_type VA, qty 0, value_in = nilai) ditulis LandedCostCards.
//  - inv_landed_cost_line: card_value (nilai yang sudah ada di kartu), card_rev (urutan baris kartu, bagian dari kunci unik referensi),
//    card_location_id (bin yang memikul nilai).
//  - v_inv_movement (bagian RM): baris VA membawa nomor landed cost sebagai doc_no.
//  - v_rm_onhand: last_item_movement tidak menghitung VA (nilai tambahan bukan pergerakan barang; penuaan stok tidak boleh ter-reset).
return new class extends Migration
{
    private const MARK = 'inv_landed_cost_line';

    public function up(): void
    {
        $db = DB::connection('tm2inv');
        if (! Schema::connection('tm2inv')->hasColumn('inv_landed_cost_line', 'card_value')) {
            Schema::connection('tm2inv')->table('inv_landed_cost_line', function (Blueprint $t) {
                $t->decimal('card_value', 18, 4)->default(0)->after('to_variance');
                $t->unsignedSmallInteger('card_rev')->default(0)->after('card_value');
                $t->unsignedBigInteger('card_location_id')->nullable()->after('card_rev');
            });
        }

        $lc = DbName::table('tm2inv.inv_landed_cost');
        $line = DbName::table('tm2inv.inv_landed_cost_line');
        $movement = $this->definition('v_inv_movement');
        if ($movement && ! str_contains($movement, self::MARK)) {
            $sub = "(SELECT lc.doc_no FROM {$line} ll JOIN {$lc} lc ON lc.id_landed_cost = ll.id_landed_cost WHERE ll.id_landed_cost_line = sc.reference_detail_id AND sc.reference_name LIKE 'tm2inv.inv_landed_cost_line%')";
            $needle = 'coalesce(`h`.`doc_no`,`tf`.`transfer_no`) AS `doc_no`';
            $pos = strpos($movement, $needle);
            if ($pos !== false) {
                $movement = substr_replace($movement, "coalesce(`h`.`doc_no`,`tf`.`transfer_no`,{$sub}) AS `doc_no`", $pos, strlen($needle));
                $db->statement($this->replaceable($movement));
            }
        }

        $onhand = $this->definition('v_rm_onhand');
        $old = "ifnull(`s2`.`doc_type`,'') <> 'TF'";
        if ($onhand && str_contains($onhand, $old)) {
            $db->statement($this->replaceable(str_replace($old, "ifnull(`s2`.`doc_type`,'') not in ('TF','VA')", $onhand)));
        }
    }

    public function down(): void
    {
        // Tidak dibalik: baris kartu VA yang sudah ditulis butuh kolom dan view ini.
    }

    private function definition(string $view): ?string
    {
        $row = (array) DB::connection('tm2inv')->selectOne("SHOW CREATE VIEW {$view}");

        return $row['Create View'] ?? null;
    }

    private function replaceable(string $create): string
    {
        return preg_replace('/^CREATE\s.*?\sVIEW\s/s', 'CREATE OR REPLACE VIEW ', $create);
    }
};
