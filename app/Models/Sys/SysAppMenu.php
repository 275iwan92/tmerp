<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Layer TAMPILAN menu (mega menu / sidebar / command palette) — TERPISAH dari
// sec_module (unit IZIN, LOCKED 2 level). Baris menu_type=module menaut ke
// sec_module.module_code lewat required_object; group/item mewarisi
// visibilitas dari leluhur module-nya (lihat SecUser::visibleAppMenuModules()).
class SysAppMenu extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_app_menu';

    protected $primaryKey = 'id';

    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'parent_id',
        'code',
        'label',
        'trans_key',
        'icon',
        'menu_type',
        'object_type',
        'target',
        'target_kind',
        'sort_order',
        'activate',
        'required_object',
        'status_code',
        'status_note',
        'status_updated_by',
        'status_updated_at',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'sort_order' => 'integer',
        'status_updated_at' => 'datetime',
    ];

    // Claude Code SENDIRI yang boleh panggil ini (module_status_system, globalrules.yaml)
    // -- setiap kali 1 komponen/mockup selesai dibangun. TIDAK PERNAH promosikan ke LOCKED;
    // hanya user yang boleh set LOCKED (lewat instruksi eksplisit atau Navicat manual).
    public function markReadyForQc(?string $note = null): void
    {
        $this->update([
            'status_code' => 'READY_FOR_QC',
            'status_note' => $note,
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
        ]);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')->orderBy('sort_order');
    }
}
