<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'device',
        'sender',
        'message',
        'member',
        'name',
        'location',
        'url',
        'filename',
        'extension',
        'role',
    ];
}
