<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broker extends Model
{
    protected $fillable = [
        'name', 'slug', 'logo', 'description', 'website_url', 'affiliate_url',
        'mt5_supported', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['mt5_supported' => 'boolean', 'status' => 'boolean'];
    }
}
