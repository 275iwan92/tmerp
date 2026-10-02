<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Susunan bawaan papan per profil (MANAGEMENT): titik awal papan user dan tujuan Reset to Template.
class SysBoardTemplate extends Model
{
    public const PROFILES = ['MANAGEMENT' => 'Management (President Director, Director, General Manager)'];

    protected $connection = 'tm2sys';

    protected $table = 'sys_board_template';

    protected $primaryKey = 'id_board_template';

    protected $fillable = ['profile_code', 'widget_key', 'sort_order', 'size', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean', 'size' => 'integer'];
}
