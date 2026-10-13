<?php

/*
 * REMARK
 * Tujuan       : Model log aksi Production Output (kapan, siapa, host, aksi, nilai sebelum dan sesudah) per operasi WO.
 * Sumber hulu  : tm2prd.prd_output_action_log (migrasi 2026_10_13_000300).
 * Input        : Baris ditambahkan oleh OutputActionLog::record(); tidak ada ubah/hapus.
 * Output       : Eloquent model baca-saja setelah dibuat.
 * Dipakai oleh : OutputActionLog, App\Support\V2\Data\ProductionOutput.
 * Status       : Aktif di mockup (ddt*).
 */

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

class PrdOutputActionLog extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_output_action_log';

    protected $primaryKey = 'log_id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['detail' => 'array', 'acted_at' => 'datetime'];

    // Append-only: a logged action is never changed or removed.
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('The Production Output log is append-only.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('The Production Output log is append-only.');
    }
}
