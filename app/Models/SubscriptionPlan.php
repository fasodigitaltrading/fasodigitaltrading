<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class SubscriptionPlan extends Model
{
    public const PUBLIC_CATALOGUE_SLUGS = ['starter', 'standard', 'pro', 'premium'];
    public const PUBLIC_CATALOGUE_TERMS = [
        'starter' => ['name' => 'STARTER', 'capital' => '200.00', 'daily' => '5.00'],
        'standard' => ['name' => 'STANDARD', 'capital' => '300.00', 'daily' => '7.50'],
        'pro' => ['name' => 'PRO', 'capital' => '500.00', 'daily' => '12.50'],
        'premium' => ['name' => 'PREMIUM', 'capital' => '800.00', 'daily' => '20.00'],
    ];

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency', 'duration_days',
        'list_price', 'max_mt5_accounts', 'features', 'status', 'sort_order', 'capital_required',
        'projected_daily_gain', 'estimated_daily_gain_usd',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'list_price' => 'decimal:2',
        'capital_required' => 'decimal:2',
        'projected_daily_gain' => 'decimal:2',
        'estimated_daily_gain_usd' => 'decimal:2',
        'status' => 'boolean',
        'duration_days' => 'integer',
        'max_mt5_accounts' => 'integer',
        'sort_order' => 'integer',
    ];

    public function matchesApprovedPublicTerms(): bool
    {
        $terms = self::PUBLIC_CATALOGUE_TERMS[$this->slug] ?? null;

        return $terms !== null
            && (string) $this->name === $terms['name']
            && strtoupper((string) $this->currency) === 'USD'
            && (float) $this->price > 0
            && (float) $this->list_price >= (float) $this->price
            && (string) $this->capital_required === $terms['capital']
            && (string) $this->estimated_daily_gain_usd === $terms['daily']
            && (int) $this->duration_days === 30;
    }
}
