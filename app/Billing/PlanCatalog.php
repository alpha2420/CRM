<?php

namespace App\Billing;

use App\Enums\Feature;
use InvalidArgumentException;

/**
 * Reads the plans from config/plans.php into Plan value objects.
 */
final class PlanCatalog
{
    public const TRIAL = 'trial';

    public function find(string $key): ?Plan
    {
        $config = config("plans.plans.{$key}");

        if (! is_array($config)) {
            return null;
        }

        return new Plan(
            key: $key,
            name: $config['name'],
            price: (int) $config['price'],
            maxUsers: (int) $config['max_users'],
            features: array_map(fn (string $feature) => Feature::from($feature), $config['features']),
            razorpayPlanId: $config['razorpay_plan_id'] ?: null,
        );
    }

    public function get(string $key): Plan
    {
        return $this->find($key) ?? throw new InvalidArgumentException("Unknown plan [{$key}].");
    }

    public function trial(): Plan
    {
        return $this->get(self::TRIAL);
    }

    /**
     * @return list<Plan>
     */
    public function paid(): array
    {
        return array_values(array_filter(
            array_map(fn (string $key) => $this->get($key), array_keys(config('plans.plans'))),
            fn (Plan $plan) => $plan->isPaid(),
        ));
    }
}
