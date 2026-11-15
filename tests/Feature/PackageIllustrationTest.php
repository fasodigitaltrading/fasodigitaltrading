<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Support\PackageIllustration;
use PHPUnit\Framework\TestCase;

final class PackageIllustrationTest extends TestCase
{
    public function test_starter_math_matches_the_package_card_model_and_fixed_xof_rate(): void
    {
        $plan = new SubscriptionPlan([
            'name' => 'Starter',
            'price' => '10.00',
            'list_price' => '10.00',
            'capital_required' => '100.00',
            'currency' => 'USD',
        ]);

        $illustration = PackageIllustration::forPlan($plan);

        self::assertSame(10_000, $illustration['capital']);
        self::assertSame(200, $illustration['daily']);
        self::assertSame(4_000, $illustration['gross']);
        self::assertSame(1_000, $illustration['monthly']);
        self::assertSame(3_000, $illustration['net']);
        self::assertSame(3_000, $illustration['net_return_basis_points']);
        self::assertSame(0, $illustration['discount_basis_points']);
        self::assertSame(6_000, $illustration['monthly_xof']);
    }

    public function test_black_discount_and_after_subscription_return_are_rounded_to_display_precision(): void
    {
        $plan = new SubscriptionPlan([
            'name' => 'Black',
            'price' => '116.22',
            'list_price' => '300.00',
            'capital_required' => '3000.00',
            'currency' => 'USD',
        ]);

        $illustration = PackageIllustration::forPlan($plan);

        self::assertSame(120_000, $illustration['gross']);
        self::assertSame(108_378, $illustration['net']);
        self::assertSame(3_613, $illustration['net_return_basis_points']);
        self::assertSame('61,26', PackageIllustration::percent($illustration['discount_basis_points']));
        self::assertSame(69_732, $illustration['monthly_xof']);
    }

    public function test_non_usd_packages_are_not_given_usd_projection_values(): void
    {
        $plan = new SubscriptionPlan([
            'name' => 'Unverified',
            'price' => '6000.00',
            'list_price' => '6000.00',
            'capital_required' => '100.00',
            'currency' => 'XOF',
        ]);

        self::assertNull(PackageIllustration::forPlan($plan));
    }
}
