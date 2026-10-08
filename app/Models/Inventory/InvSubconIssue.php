<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Kirim bahan ke subkon (v2.01, I-23, D22): induk = PO Subkon (po_id). Stok pindah ke bin SUBCON vendor lewat dokumen Stock Transfer
// (transfer_id); kepemilikan tetap COMPANY dan bernilai. Ditulis hanya oleh App\Services\Inventory\SubconStockService.
class InvSubconIssue extends Model
{
    public const STATUSES = ['SENT' => 'Sent', 'CLOSED' => 'Closed', 'CANCELLED' => 'Cancelled'];

    protected $connection = 'tm2inv';

    protected $table = 'inv_subcon_issue';

    protected $primaryKey = 'id_subcon_issue';

    protected $guarded = ['id_subcon_issue'];

    protected $casts = ['issue_date' => 'date', 'due_return_date' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(InvSubconIssueLine::class, 'issue_id', 'id_subcon_issue')->orderBy('line_no');
    }

    // Nomor: SC + 2 digit tahun + 5 digit urut (SC2600001).
    public static function nextNo(): string
    {
        $prefix = 'SC'.now()->format('y');
        $last = static::query()->where('issue_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('issue_no')->value('issue_no');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
