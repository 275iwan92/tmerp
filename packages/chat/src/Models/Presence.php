<?php

namespace Chat\Models;

// Denyut user yang sedang login: satu baris per user (user_id = id auth).
class Presence extends Model
{
    protected $table = 'sys_user_presence';

    protected $primaryKey = 'id_presence';

    public $timestamps = false;

    protected $fillable = ['user_id', 'signed_in', 'last_seen_at'];

    protected $casts = ['signed_in' => 'boolean', 'last_seen_at' => 'datetime'];
}
