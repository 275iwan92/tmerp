<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ACC customer per product: EMAIL / DIGITAL (wajib sebelum Cetak) / SAMPLE (bentuk dari sheet, dicatat saja).
class ProdProductAcc extends Model
{
    // Kolom master card: ACC E-mail (desain lewat e-mail), ACC Digital (sample cetak plat CTP), Box Polos (sample bentuk sheet).
    // Disimpan = kode (kunci); label English, tampil lewat typeOptions() / channelOptions() (kamus t()).
    public const TYPES = ['EMAIL' => 'ACC E-mail (design)', 'DIGITAL' => 'ACC Digital Artwork', 'SAMPLE' => 'ACC Shape Sample (plain box)'];

    public const CHANNELS = ['WA' => 'WhatsApp', 'EMAIL' => 'E-mail', 'LANGSUNG' => 'In person'];

    public static function typeOptions(): array
    {
        return array_map('t', self::TYPES);
    }

    public static function channelOptions(): array
    {
        return array_map('t', self::CHANNELS);
    }

    protected $connection = 'tm2mst';

    protected $table = 'prod_product_acc';

    protected $primaryKey = 'id_acc';

    protected $fillable = ['product_id', 'acc_type', 'acc_at', 'approved_by', 'channel', 'evidence_path', 'evidence_filename', 'note', 'created_by', 'updated_by'];

    protected $casts = ['acc_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }
}
