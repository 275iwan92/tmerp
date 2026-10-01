<?php

namespace App\Services\Sales;

use App\Models\Master\MatJoinType;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Rakitan (ASSEMBLY) di Inquiry Baru (docs/flow_product_master.md §3): part yang dibuat dengan menggabungkan part lain,
// boleh bertingkat. Node rakitan = baris iqp_part part_kind=ASSEMBLY; anaknya menunjuk lewat parent_part_id.
// Bahan tetap milik komponen; rakitan hanya membawa jenis sambungan (mat_join_type) & qty anak per rakitan.
// Saat WIN, ProductMasterSync menyalin struktur yang sama ke prod_part.
final class InquiryAssembly
{
    // part_seq rakitan di belakang komponen (komponen diberi 1..n ulang oleh InquiryWriter tiap Simpan).
    private const SEQ_BASE = 900;

    public function __construct(private string $login) {}

    public static function tree(IqpInquiry $inq): array
    {
        $parts = $inq->parts()->orderBy('part_seq')->get();
        $joins = MatJoinType::pluck('label', 'id_join_type');
        $node = function (IqpPart $p) use (&$node, $parts, $joins): array {
            return ['id' => $p->id_part, 'name' => $p->part_name, 'kind' => $p->part_kind ?: 'COMPONENT', 'qty' => (float) ($p->qty_in_parent ?? 1),
                'join' => $joins[$p->join_type_id] ?? null,
                'children' => $parts->where('parent_part_id', $p->id_part)->map(fn ($c) => $node($c))->values()->all()];
        };

        return $parts->whereNull('parent_part_id')->map(fn ($p) => $node($p))->values()->all();
    }

    public function create(IqpInquiry $inq, string $name, int $joinTypeId, array $childIds, float $qty = 1): IqpPart
    {
        abort_if($inq->stat_confirm_sales, 422, 'Inquiry sudah Confirm — struktur rakitan terkunci.');
        $children = $inq->parts()->whereIn('id_part', array_map('intval', $childIds))->whereNull('parent_part_id')->get();
        if ($children->count() < 2) {
            throw new InvalidArgumentException('Rakitan butuh minimal 2 part (komponen atau rakitan lain) yang belum masuk rakitan mana pun.');
        }
        MatJoinType::findOrFail($joinTypeId);

        return DB::connection('tm2sdi')->transaction(function () use ($inq, $name, $joinTypeId, $children, $qty) {
            $seq = max(self::SEQ_BASE, (int) $inq->parts()->max('part_seq')) + 1;
            $assembly = $inq->parts()->create(['part_seq' => $seq, 'part_name' => $name, 'part_kind' => 'ASSEMBLY', 'join_type_id' => $joinTypeId,
                'qty_per_sku' => 1, 'qty_in_parent' => 1, 'created_by' => $this->login, 'updated_by' => $this->login]);
            IqpPart::whereIn('id_part', $children->pluck('id_part'))->update(['parent_part_id' => $assembly->id_part, 'qty_in_parent' => $qty, 'updated_by' => $this->login]);

            return $assembly;
        });
    }

    // Bongkar rakitan: anaknya kembali berdiri sendiri (tidak ikut terhapus).
    public function remove(IqpInquiry $inq, int $assemblyId): void
    {
        abort_if($inq->stat_confirm_sales, 422, 'Inquiry sudah Confirm — struktur rakitan terkunci.');
        $a = $inq->parts()->where('part_kind', 'ASSEMBLY')->findOrFail($assemblyId);
        DB::connection('tm2sdi')->transaction(function () use ($a) {
            IqpPart::where('parent_part_id', $a->id_part)->update(['parent_part_id' => $a->parent_part_id, 'updated_by' => $this->login]);
            $a->delete();
        });
    }
}
