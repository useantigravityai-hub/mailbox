<?php

namespace Queen\GmailMailbox\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GmailFavorite extends Model
{
    use HasFactory;

    protected $table = 'gmail_favorites';

    protected $fillable = [
        'user_id',
        'account_id',
        'email',
        'name',
        'notify_incoming',
        'notify_outgoing',
        'last_notified_at',
        'last_message_id',
    ];

    protected $casts = [
        'notify_incoming'  => 'boolean',
        'notify_outgoing'  => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    /**
     * Scope for current authenticated user or public session
     */
    public function scopeForCurrentUser($query)
    {
        if (auth()->check()) {
            return $query->where('user_id', auth()->id());
        }
        return $query;
    }

    /**
     * Scope for specific Google account token ID
     */
    public function scopeForAccount($query, ?int $accountId = null)
    {
        if ($accountId) {
            return $query->where('account_id', $accountId);
        }
        return $query;
    }

    /**
     * Relationship to Google Token
     */
    public function token()
    {
        return $this->belongsTo(GoogleToken::class, 'account_id');
    }

    /**
     * Get clean display name
     */
    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        return explode('@', $this->email)[0];
    }
}
