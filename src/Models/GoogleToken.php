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
        'name',
        'avatar',
        'token',
    ];

    protected $casts = [
        'token' => 'array',
    ];

    /**
     * Scope a query to only include tokens for the current logged in user (if auth is enabled).
     */
    public function scopeForCurrentUser($query)
    {
        if (auth()->check()) {
            return $query->where('user_id', auth()->id());
        }
        return $query;
    }

    /**
     * Get display name or fallback to email username
     */
    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        if (!empty($this->email)) {
            return explode('@', $this->email)[0];
        }

        return 'Gmail User';
    }

    /**
     * Get single capital letter initial
     */
    public function getInitialAttribute(): string
    {
        $name = $this->name ?: $this->email ?: 'G';
        return strtoupper(substr(trim($name), 0, 1));
    }

    /**
     * Get consistent background color based on name/email
     */
    public function getAvatarColorAttribute(): string
    {
        $colors = ['#1abc9c', '#3498db', '#9b59b6', '#34495e', '#f1c40f', '#e67e22', '#e74c3c', '#e91e63'];
        $seed = crc32($this->email ?: $this->name ?: 'default');
        return $colors[abs($seed) % count($colors)];
    }
}
