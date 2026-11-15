<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const PRIORITIES = ['NORMAL', 'HIGH', 'URGENT'];
    public const CATEGORIES = ['GENERAL', 'PAYMENT', 'SUBSCRIPTION', 'LICENSE', 'MT5', 'OTHER'];
    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'CLOSED'];

    protected $fillable = ['user_id', 'subject', 'status', 'category', 'priority', 'assigned_admin_id', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }
}
