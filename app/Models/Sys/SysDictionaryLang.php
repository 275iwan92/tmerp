<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysDictionaryLang extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_dictionary_lang';

    protected $primaryKey = 'id_dictionary_lang';

    protected $fillable = ['id_dictionary', 'lang_code', 'text', 'created_by', 'updated_by'];
}
