<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Kurs mata uang, versioned (CLAUDE.md §4 -- pola sama dgn mat_coating_rate):
// 1 baris activate=1 per currency_id = kurs berlaku, effective_date utk riwayat.
class FinExchangeRate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'fin_exchange_rate';

    protected $primaryKey = 'id_exchange_rate';

    protected $fillable = [
        'currency_id',
        'rate_to_base',
        'effective_date',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate_to_base' => 'decimal:6',
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(FinCurrency::class, 'currency_id', 'id_currency');
    }

    // Accessor virtual utk kontrak HasSpreadsheetGrid (spreadsheetRows() baca
    // $row->{$key} langsung) -- grid tampilkan kode currency, bukan FK id.
    public function getCurrencyCodeAttribute(): ?string
    {
        return $this->currency?->currency_code;
    }

    public static function currentRate(int $currencyId, float $default = 0.0): float
    {
        return (float) (static::query()
            ->where('currency_id', $currencyId)
            ->where('activate', true)
            ->value('rate_to_base') ?? $default);
    }
}
