<?php

namespace App\Support;

use App\Models\SubscriptionPlan;

/**
 * Reproduces the package-card arithmetic while keeping all calculations in
 * integer cents. The result is only an illustrative scenario, never a return
 * statement or a guarantee.
 */
final class PackageIllustration
{
    public static function forPlan(SubscriptionPlan $plan, string $fixedUsdXofRate = '600.0000'): ?array
    {
        $capital = self::toCents((string) $plan->capital_required);
        $price = self::toCents((string) $plan->price);
        $listPrice = self::toCents((string) $plan->list_price);

        if (strtoupper((string) $plan->currency) !== 'USD' || $capital === null || $capital <= 0 || $price === null || $price <= 0) {
            return null;
        }

        $daily = self::roundedRatio($capital * 2, 100);
        $gross = self::roundedRatio($capital * 40, 100);
        $net = $gross - $price;
        $discount = $listPrice !== null && $listPrice > 0 && $listPrice > $price
            ? self::roundedRatio(($listPrice - $price) * 10_000, $listPrice)
            : 0;
        $xofRate = self::toCents($fixedUsdXofRate);
        $monthlyXof = $xofRate === null ? null : self::roundedRatio($price * $xofRate, 10_000);

        return [
            'capital' => $capital,
            'daily' => $daily,
            'gross' => $gross,
            'monthly' => $price,
            'list' => $listPrice,
            'discount_basis_points' => $discount,
            'net' => $net,
            'net_return_basis_points' => self::roundedRatio($net * 10_000, $capital),
            'monthly_xof' => $monthlyXof,
        ];
    }

    public static function usd(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ');
    }

    public static function xof(?int $amount): ?string
    {
        return $amount === null ? null : number_format($amount, 0, ',', ' ');
    }

    public static function percent(int $basisPoints): string
    {
        $whole = intdiv(abs($basisPoints), 100);
        $fraction = abs($basisPoints) % 100;
        $formatted = number_format($whole, 0, ',', ' ').','.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);

        return ($basisPoints < 0 ? '−' : '').$formatted;
    }

    private static function toCents(string $amount): ?int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,4}))?$/', $amount, $parts)) {
            return null;
        }

        $fraction = str_pad(substr($parts[2] ?? '', 0, 2), 2, '0');
        if (isset($parts[2][2]) && (int) $parts[2][2] >= 5) {
            $fraction = str_pad((string) ((int) $fraction + 1), 2, '0');
            if (strlen($fraction) > 2) {
                return ((int) $parts[1] + 1) * 100;
            }
        }

        return ((int) $parts[1] * 100) + (int) $fraction;
    }

    private static function roundedRatio(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            return 0;
        }

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
