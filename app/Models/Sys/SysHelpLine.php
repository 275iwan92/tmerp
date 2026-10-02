<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Help: satu potongan pendek milik satu topik -- langkah cara pakai (STEP) atau penjelasan satu isian / kolom (FIELD).
class SysHelpLine extends Model
{
    public const STEP = 'STEP';

    public const FIELD = 'FIELD';

    public const TYPES = [self::STEP => 'How to use (step)', self::FIELD => 'Field explanation'];

    protected $connection = 'tm2sys';

    protected $table = 'sys_help_line';

    protected $primaryKey = 'id_help_line';

    protected $fillable = ['help_topic_id', 'line_type', 'field_label', 'body', 'sort_order', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(SysHelpTopic::class, 'help_topic_id', 'id_help_topic');
    }
}
