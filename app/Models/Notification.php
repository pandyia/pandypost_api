<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    // O id é um uuid gerado pelo Laravel; é o identificador externo da notificação.
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];
}
