<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SysDocShare extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_doc_share';

    protected $primaryKey = 'id_share';

    protected $fillable = ['token', 'doc_type', 'doc_id', 'doc_no', 'title', 'html', 'html_hash', 'expires_at', 'view_count', 'last_viewed_at', 'created_by', 'updated_by'];

    protected $casts = ['expires_at' => 'datetime', 'last_viewed_at' => 'datetime'];

    public function emails(): HasMany
    {
        return $this->hasMany(SysDocEmail::class, 'share_id', 'id_share');
    }

    public function url(): string
    {
        return route('doc.share', $this->token);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
