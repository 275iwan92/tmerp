<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use Illuminate\Support\Facades\DB;

// inventory_tier4.md #7: layar Stock In/Out/Adjustment/Return TIDAK BOLEH tulis
// langsung ke *_stock/*_stock_d dari Livewire component -- lewat sini saja.
// Nomor dokumen ikut pola inventory_tier1.md #2: {PREFIX}/{YY}/{#####}.
class StockDocumentService
{
    private const CATEGORY_HEADER = [
        'RM' => RmStock::class,
        'FG' => FgStock::class,
        'SFG' => SfgStock::class,
    ];

    public function nextDocNo(string $category, string $prefix): string
    {
        $headerClass = self::CATEGORY_HEADER[$category];
        $yy = now()->format('y');
        $like = "{$prefix}/{$yy}/%";

        $last = $headerClass::where('doc_no', 'like', $like)->orderByDesc('doc_no')->value('doc_no');
        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('%s/%s/%05d', $prefix, $yy, $seq);
    }

    /**
     * Simpan draft header + baris (replace-all baris tiap simpan -- dokumen
     * belum diposting jadi aman diganti total, bukan diff per baris).
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function saveDraft(string $category, ?int $docId, array $headerData, array $lines, string $actingUser): int
    {
        $headerClass = self::CATEGORY_HEADER[$category];

        return DB::connection('tm2inv')->transaction(function () use ($headerClass, $docId, $headerData, $lines, $actingUser) {
            if ($docId) {
                $header = $headerClass::findOrFail($docId);
                abort_if($header->stat_confirm, 422, t('The document has been posted and cannot be changed.'));
                $header->update([...$headerData, 'update_by' => $actingUser]);
                $header->lines()->delete();
            } else {
                $header = $headerClass::create([...$headerData, 'flow_status' => 'DRAFT', 'create_by' => $actingUser, 'update_by' => $actingUser]);
            }

            foreach ($lines as $seq => $line) {
                $header->lines()->create([...$line, 'seq_no' => $seq + 1, 'create_by' => $actingUser, 'update_by' => $actingUser]);
            }

            return $header->getKey();
        });
    }

    public function cancel(string $category, int $docId, string $actingUser): void
    {
        $headerClass = self::CATEGORY_HEADER[$category];
        $header = $headerClass::findOrFail($docId);
        abort_if($header->stat_confirm, 422, t('The document has been posted and cannot be cancelled -- correct it with a Stock Adjustment.'));

        $header->update(['stat_cancel' => true, 'flow_status' => 'CANCELLED', 'update_by' => $actingUser]);
    }
}
