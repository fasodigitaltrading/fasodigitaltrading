<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    protected $fillable = ['name', 'email', 'intent', 'package', 'message', 'status', 'privacy_accepted_at', 'privacy_notice_version'];

    protected function casts(): array
    {
        return ['privacy_accepted_at' => 'datetime'];
    }
}
