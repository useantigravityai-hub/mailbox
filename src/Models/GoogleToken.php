<?php

namespace Queen\GmailMailbox\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleToken extends Model
{
    use HasFactory;

    protected $table = 'google_tokens';

    protected $fillable = [
        'user_id',
        'email',
        'token',
    ];

    protected $casts = [
        'token' => 'array',
    ];
}
