<?php

/*
 * REMARK
 * Tujuan       : Periode berlaku susunan satu crew (dari-sampai, sampai kosong = terbuka) beserta anggotanya.
 * Sumber hulu  : tm2mst.prod_machine_crew_period.
 * Input        : id_machine_crew, valid_from, valid_to.
 * Output       : Relasi members(); covers(tanggal) = berlaku pada tanggal itu.
 * Dipakai oleh : MachineCrewService, layar v2 crew-form.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdMachineCrewPeriod extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_machine_crew_period';

    protected $primaryKey = 'id_crew_period';

    protected $fillable = ['id_machine_crew', 'valid_from', 'valid_to', 'created_by', 'updated_by'];

    protected $casts = ['valid_from' => 'date:Y-m-d', 'valid_to' => 'date:Y-m-d'];

    public function crew(): BelongsTo
    {
        return $this->belongsTo(ProdMachineCrew::class, 'id_machine_crew', 'id_machine_crew');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProdMachineCrewMember::class, 'id_crew_period', 'id_crew_period');
    }

    public function covers(string $date): bool
    {
        return $this->valid_from->toDateString() <= $date && ($this->valid_to === null || $this->valid_to->toDateString() >= $date);
    }
}
