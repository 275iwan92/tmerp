<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Help: satu layar / laporan / panduan umum. Teks English; terjemahan lewat kamus konteks App\Services\Help\HelpCenter::CONTEXT.
class SysHelpTopic extends Model
{
    public const INPUT = 'INPUT';

    public const REPORT = 'REPORT';

    public const GUIDE = 'GUIDE';

    public const TYPES = [self::INPUT => 'Input screen', self::REPORT => 'Report', self::GUIDE => 'General guide'];

    protected $connection = 'tm2sys';

    protected $table = 'sys_help_topic';

    protected $primaryKey = 'id_help_topic';

    protected $fillable = ['topic_key', 'topic_type', 'module_code', 'title', 'purpose', 'keywords', 'sort_order', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];

    public function lines(): HasMany
    {
        return $this->hasMany(SysHelpLine::class, 'help_topic_id', 'id_help_topic')->orderBy('line_type', 'desc')->orderBy('sort_order')->orderBy('id_help_line');
    }
}
