<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

// Riwayat perubahan aset dan dokumen Maintenance (tab History form DevLib).
class MntLog extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'tm2mnt';

    protected $table = 'mnt_log';

    protected $primaryKey = 'id_log';

    protected $guarded = ['id_log'];

    protected $casts = ['created_at' => 'datetime'];

    public static function add(string $type, int $id, string $action, ?string $note = null): void
    {
        static::create(['doc_type' => $type, 'doc_id' => $id, 'action' => $action, 'note' => $note, 'created_by' => auth()->user()?->login ?? 'system']);
    }

    // Bentuk :log x-dl::form (terbaru di atas).
    public static function forDoc(string $type, int $id): array
    {
        return static::query()->where('doc_type', $type)->where('doc_id', $id)->orderByDesc('id_log')->get()
            ->map(fn ($l) => ['at' => $l->created_at?->toDateTimeString(), 'by' => $l->created_by, 'action' => $l->action, 'note' => $l->note])->all();
    }
}
