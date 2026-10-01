<?php

namespace Database\Factories;

use App\Enums\StatusType;
use App\Models\LeadStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadStatus>
 */
class LeadStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => ucfirst(fake()->unique()->word()),
            'type' => StatusType::Open,
            'color' => fake()->hexColor(),
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}
