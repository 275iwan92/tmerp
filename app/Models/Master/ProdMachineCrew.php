<?php

/*
 * REMARK
 * Tujuan       : Crew satu mesin (indeks 1..4); nama dihitung "{Nickname} - {Nama Shift}", tidak disimpan.
 * Sumber hulu  : tm2mst.prod_machine_crew.
 * Input        : machine_id, crew_index, shift_id (emp_shift), activate.
 * Output       : Relasi machine(), shift(), periods(); name() = "Plong 1 - TM Production Shift 1" (+ indeks hanya bila shift yang sama punya dua crew aktif).
 * Dipakai oleh : MachineCrewService, layar v2 crew-form.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdMachineCrew extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_machine_crew';

    protected $primaryKey = 'id_machine_crew';

    protected $fillable = ['machine_id', 'crew_index', 'shift_id', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmpShift::class, 'shift_id', 'shift_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(ProdMachineCrewPeriod::class, 'id_machine_crew', 'id_machine_crew')->orderBy('valid_from');
    }

    /** "{Nickname} - {Nama Shift}"; indeks hanya ditambahkan bila mesin punya lebih dari satu crew aktif pada shift yang sama. */
    public function name(): string
    {
        $same = static::query()->where('machine_id', $this->machine_id)->where('shift_id', $this->shift_id)->where('activate', true)->orderBy('crew_index')->pluck('id_machine_crew');
        $rank = $same->count() > 1 ? $same->search($this->id_machine_crew) : false;

        return trim(($this->machine?->nickname ?: '').' - '.($this->shift?->shift_name ?? '')).($rank !== false ? ' '.($rank + 1) : '');
    }
}
