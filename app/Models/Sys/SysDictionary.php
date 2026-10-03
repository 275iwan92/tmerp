<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Kamus penerjemah: satu teks English (bahasa dasar database) per konteks. Terjemahan per bahasa = SysDictionaryLang.
class SysDictionary extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_dictionary';

    protected $primaryKey = 'id_dictionary';

    protected $fillable = ['context', 'source_en', 'group_code', 'origin', 'first_seen', 'note', 'activate', 'created_by', 'updated_by'];

    // origin: 'app' = teks layar / pesan aplikasi; 'devlib' = kata bawaan objek DevLib (Devlib\Lang::DICT) yang boleh ditimpa.
    public const ORIGIN_APP = 'app';

    public const ORIGIN_DEVLIB = 'devlib';

    protected $casts = ['activate' => 'boolean'];

    public function langs(): HasMany
    {
        return $this->hasMany(SysDictionaryLang::class, 'id_dictionary', 'id_dictionary');
    }
}
