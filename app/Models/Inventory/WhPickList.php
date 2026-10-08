<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Pick list per Work Order (W-05, B-12): dari booking Confirmed, mesin mengalokasikan palet / batch / bin, petugas gudang memindai
// palet lalu bin, Confirm Pick = Stock Out RM ke WO. Aturan dan alur: App\Services\Inventory\PickListService.
// Status: DRAFT (baru dialokasikan) / IN_PROGRESS (sudah ada yang dipindai) / DONE (Stock Out terposting) / CANCELLED.
class WhPickList extends Model
{
    public const STATUSES = ['DRAFT' => 'neutral', 'IN_PROGRESS' => 'warn', 'DONE' => 'success', 'CANCELLED' => 'danger'];

    public const OPEN = ['DRAFT', 'IN_PROGRESS'];

    protected $connection = 'tm2inv';

    protected $table = 'wh_pick_list';

    protected $primaryKey = 'id_pick_list';

    protected $guarded = ['id_pick_list'];

    protected $casts = ['pick_date' => 'date', 'need_date' => 'date', 'confirmed_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(WhPickListLine::class, 'pick_list_id', 'id_pick_list')->orderBy('line_no');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    // Nomor: PK + 2 digit tahun + 5 digit urut (PK2600012). Dipanggil di dalam transaksi pembuatan.
    public static function nextNo(): string
    {
        $prefix = 'PK'.now()->format('y');
        $last = static::query()->where('pick_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('pick_no')->value('pick_no');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
