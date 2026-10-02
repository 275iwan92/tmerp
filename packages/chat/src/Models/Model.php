<?php

namespace Chat\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

// Induk model chat: koneksi dari config('chat.connection') supaya paket bisa dipasang di database mana pun.
abstract class Model extends Eloquent
{
    public function getConnectionName()
    {
        return config('chat.connection') ?? parent::getConnectionName();
    }
}
