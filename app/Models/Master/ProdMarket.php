<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Lookup Product Market: nama pasar (bahasa sales) -> Tipe Produk + nilai awal Inquiry. Diisi officer ProDev.
class ProdMarket extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_market';

    protected $primaryKey = 'id_market';

    protected $fillable = [
        'parent_id',
        'market_code',
        'market_label',
        'type_code',
        'photo_path',
        'paper_category_id',
        'sheet_category_id',
        'default_color',
        'default_tiers',
        'notes',
        'sort_order',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'sort_order' => 'integer',
    ];

    // 2 tingkat: parent_id null = Kategori Produk (tanpa Tipe Produk), terisi = Detail Kategori.
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id_market');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id_market')->orderBy('sort_order')->orderBy('market_label');
    }

    public function isGroup(): bool
    {
        return $this->parent_id === null;
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProdType::class, 'type_code', 'type_code');
    }

    // Bahan awal = kategori bahan baku (mat_category, rantai 5 tingkat): #1 Paper, #2 Sheet/SF.
    public function paperCategory(): BelongsTo
    {
        return $this->belongsTo(MatCategory::class, 'paper_category_id', 'id_category');
    }

    public function sheetCategory(): BelongsTo
    {
        return $this->belongsTo(MatCategory::class, 'sheet_category_id', 'id_category');
    }

    // "Paper › Kraft › 275 GR" -- nama rantai dari root sampai kategori terpilih.
    public static function chainLabel(?MatCategory $c): ?string
    {
        return $c ? implode(' › ', array_map(fn ($n) => $n->category_name, $c->ancestorChain())) : null;
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProdTypeSize::class, 'market_id', 'id_market')->orderBy('sort_order');
    }

    // Foto sendiri, cadangan gambar Tipe Produk (/img/producttype/*.jpg), selain itu null -> kotak inisial.
    public function photoUrl(): ?string
    {
        if ($this->photo_path) {
            return asset('storage/'.$this->photo_path);
        }
        $typePhoto = $this->type?->photo_path;

        return $typePhoto && str_starts_with($typePhoto, '/') && is_file(public_path($typePhoto)) ? asset(ltrim($typePhoto, '/')) : null;
    }

    public function initials(): string
    {
        return mb_strtoupper(collect(preg_split('/\s+/', trim($this->market_label)))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
    }

    // "1000, 3000,5000" -> [1000, 3000, 5000]
    public function tierList(): array
    {
        return array_values(array_filter(array_map(fn ($v) => (int) preg_replace('/\D/', '', $v), explode(',', (string) $this->default_tiers))));
    }
}
