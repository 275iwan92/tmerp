<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Pengganti doc_rmprefix1..5 (5 tabel snowflake) -- satu tabel self-referencing
// (adjacency list) pembentuk hirarki kategori bahan baku 1-5 level. Tiap baris
// adalah sumber NAMA (category_name, dipakai isi mat_raw.category1..5)
// DAN KODE (prefix_char, dipakai bentuk material_code) sekaligus. Level 1 saja
// yang boleh isi stock_class/default_*_account_id (fallback akun per kategori).
class MatCategory extends Model
{
    public $timestamps = true;

    protected $connection = 'tm2mst';

    protected $table = 'mat_category';

    protected $primaryKey = 'id_category';

    protected $fillable = [
        'parent_id', 'level', 'category_name', 'prefix_char', 'note',
        'stock_class', 'default_coa_account_id', 'default_stock_asset_account_id',
        'default_gr_ir_account_id', 'activate', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'level' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id_category');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id_category');
    }

    public function scopeLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    // Jalan dari root sampai baris ini, urut level 1 -> N.
    public function ancestorChain(): array
    {
        $chain = [];
        $node = $this;

        while ($node) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }

    // Concat prefix_char sepanjang rantai root -> baris ini = pembentuk material_code.
    public function fullPrefix(): string
    {
        return implode('', array_map(fn ($n) => (string) $n->prefix_char, $this->ancestorChain()));
    }
}
