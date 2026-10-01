<?php

namespace App\Billing;

use App\Enums\Feature;

final readonly class Plan
{
    /**
     * @param  list<Feature>  $features
     */
    public function __construct(
        public string $key,
        public string $name,
        public int $price,
        public int $maxUsers,
        public array $features,
        public ?string $razorpayPlanId,
    ) {}

    public function allows(Feature $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    public function isPaid(): bool
    {
        return $this->price > 0;
    }
}
