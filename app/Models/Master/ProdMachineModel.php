<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Katalog model mesin yang ada di pasar (mis. pond ML-750 .. ML-1300, pond otomatis 1050). Spek umum pabrikan;
// mesin pabrik sungguhan (prod_machine) memilih salah satunya lewat machine_model_id.
class ProdMachineModel extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_machine_model';

    protected $primaryKey = 'id_machine_model';

    // Disimpan = kode (kunci); label English, tampil lewat driveOptions() (kamus t()).
    public const DRIVES = ['MANUAL' => 'Manual', 'AUTO' => 'Automatic'];

    public static function driveOptions(): array
    {
        return array_map('t', self::DRIVES);
    }

    protected $fillable = [
        'model_code', 'alias_codes', 'machine_type', 'drive', 'maker_hint', 'work_length_mm', 'work_width_mm',
        'max_cut_length_mm', 'max_cut_width_mm', 'speed_per_hour', 'max_rule_length_m', 'motor_kw', 'weight_kg',
        'pressure_ton', 'source_note', 'activate', 'created_by', 'updated_by',
    ];

    protected $casts = ['activate' => 'boolean', 'max_rule_length_m' => 'decimal:1', 'motor_kw' => 'decimal:1'];
}
