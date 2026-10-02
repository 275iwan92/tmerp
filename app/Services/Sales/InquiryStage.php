<?php

namespace App\Services\Sales;

use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Tahap dokumen Inquiry lewat tiga tanda di iqp_inquiry (user 2026-10-02, rulebook A.20):
//   stat_confirm_sales   = Confirm oleh Sales (dipasang layar Inquiry sendiri)
//   stat_confirm_prodev  = ProDev sudah Confirm SEMUA part -> dihitung di sini dari layout terbaru tiap part
//   stat_confirm_procost = costing published -> dihitung di sini dari iqp_costing.published_flag
// Grid tiap bagian = saringan (jenis AND tanda) atas tabel yang sama. RECALC tidak lewat ProDev.
// Query builder (bukan Eloquent) supaya DataOriginScope web tidak ikut menyaring hitungan.
final class InquiryStage
{
    public const PRODEV_TYPES = ['NEW', 'REVISE'];

    // Status ProDev tiap part komponen inquiry: id iqp_part => NOT_STARTED | DRAFT | CONFIRMED (layout terbaru part master-nya).
    public static function partStatuses(array $inquiryIds): Collection
    {
        $parts = DB::connection('tm2sdi')->table('iqp_part')->whereIn('inquiry_id', $inquiryIds)
            ->where(fn ($q) => $q->whereNull('part_kind')->orWhere('part_kind', '<>', 'ASSEMBLY'))->get(['id_part', 'inquiry_id', 'prod_part_id']);
        $latest = DB::connection('tm2mst')->table('prod_layout_group_part as gp')->join('prod_layout_group as g', 'g.id_layout_group', '=', 'gp.id_layout_group')
            ->whereIn('gp.part_id', $parts->pluck('prod_part_id')->filter()->unique()->all())->orderBy('g.id_layout_group')
            ->get(['gp.part_id', 'g.status'])->pluck('status', 'part_id');

        return $parts->map(fn ($p) => (object) ['id_part' => $p->id_part, 'inquiry_id' => $p->inquiry_id, 'prod_part_id' => $p->prod_part_id,
            'status' => ! $p->prod_part_id || ! isset($latest[$p->prod_part_id]) ? 'NOT_STARTED' : ($latest[$p->prod_part_id] === 'CONFIRMED' ? 'CONFIRMED' : 'DRAFT')]);
    }

    // Dipanggil setiap layout disimpan / di-Confirm. Naik ke 1 saat part terakhir Confirm; turun ke 0 hanya selama ProCost
    // belum mulai (belum ada costing), supaya inquiry yang sedang dihitung tidak hilang dari meja ProCost.
    public static function syncProdev(int $inquiryId): void
    {
        $inq = DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $inquiryId)->first();
        if (! $inq || ! in_array($inq->inquiry_type, self::PRODEV_TYPES, true) || ! $inq->stat_confirm_sales) {
            return;
        }
        $parts = self::partStatuses([$inquiryId]);
        $done = $parts->isNotEmpty() && $parts->every(fn ($p) => $p->status === 'CONFIRMED');
        if ($done && ! $inq->stat_confirm_prodev) {
            self::set($inquiryId, 'prodev', true);
        } elseif (! $done && $inq->stat_confirm_prodev && ! DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $inquiryId)->exists()) {
            self::set($inquiryId, 'prodev', false);
        }
    }

    public static function syncProdevForParts(array $prodPartIds): void
    {
        foreach (DB::connection('tm2sdi')->table('iqp_part')->whereIn('prod_part_id', $prodPartIds)->distinct()->pluck('inquiry_id') as $id) {
            self::syncProdev((int) $id);
        }
    }

    public static function syncProcost(int $inquiryId): void
    {
        $inq = DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $inquiryId)->first();
        if (! $inq) {
            return;
        }
        $published = DB::connection('tm2sdi')->table('iqp_costing')->where('inquiry_id', $inquiryId)->where('published_flag', 1)->exists();
        if ($published !== (bool) $inq->stat_confirm_procost) {
            self::set($inquiryId, 'procost', $published);
        }
    }

    // Grid ProDev: satu baris = satu inquiry. Saat dibuka, part master (prod_part) dibuat bila belum ada -- pengganti tombol
    // "Kirim ke ProDev" di form Sales. Kembalian: id prod_part yang dibuka (part pertama yang belum Confirmed), null = tanpa part.
    public static function openInProdev(IqpInquiry $inquiry, string $login): ?int
    {
        abort_unless($inquiry->stat_confirm_sales && ! $inquiry->stat_cancel && in_array($inquiry->inquiry_type, self::PRODEV_TYPES, true), 422,
            t('Only a New / Revise inquiry confirmed by Sales can be opened in Pro-Dev.'));
        $writer = new InquiryWriter($login);
        $parts = IqpPart::where('inquiry_id', $inquiry->id_inquiry)->where(fn ($q) => $q->whereNull('part_kind')->orWhere('part_kind', '<>', 'ASSEMBLY'))
            ->orderBy('part_seq')->get();
        foreach ($parts->whereNull('prod_part_id') as $p) {
            $writer->sendToProDev($inquiry, $p);
        }
        $status = self::partStatuses([$inquiry->id_inquiry])->keyBy('id_part');
        $first = $parts->first(fn ($p) => ($status[$p->id_part]->status ?? '') !== 'CONFIRMED') ?? $parts->first();

        return $first ? (int) $first->refresh()->prod_part_id : null;
    }

    private static function set(int $inquiryId, string $stage, bool $on): void
    {
        DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $inquiryId)->update([
            "stat_confirm_$stage" => $on ? 1 : 0,
            "confirm_{$stage}_by" => $on ? (Auth::user()?->login ?? 'system') : null,
            "confirm_{$stage}_at" => $on ? now() : null,
        ]);
    }

    // Tanda tahap berikut ikut berubah bila sumbernya berubah di layar mana pun (v1 maupun v2).
    public static function listen(): void
    {
        \App\Models\Master\ProdLayoutGroup::saved(function ($g) {
            self::syncProdevForParts(DB::connection('tm2mst')->table('prod_layout_group_part')->where('id_layout_group', $g->getKey())->pluck('part_id')->all());
        });
        \App\Models\Sdi\IqpCosting::saved(fn ($c) => $c->inquiry_id ? self::syncProcost((int) $c->inquiry_id) : null);
    }
}
