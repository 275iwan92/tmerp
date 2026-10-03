<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Kelompok pesan Translation Dictionary (pohon kiri: Buttons, Menu, Grid, Form, Messages, ...). Baris kamus merujuk code-nya.
class SysDictionaryGroup extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_dictionary_group';

    protected $primaryKey = 'id_dictionary_group';

    protected $fillable = ['code', 'label', 'icon', 'sort_order', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
