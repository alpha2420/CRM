<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'phone' => '+91'.fake()->unique()->numerify('9#########'),
            'email' => fake()->safeEmail(),
            'company' => fake()->company(),
            'city' => fake()->city(),
            // Default to the organization's first open status.
            'status_id' => fn (array $attributes) => LeadStatus::withoutGlobalScopes()
                ->where('organization_id', $attributes['organization_id'])
                ->where('type', StatusType::Open)
                ->orderBy('sort_order')
                ->value('id')
                ?? LeadStatus::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'priority' => Priority::Medium,
        ];
    }

    public function withStatus(LeadStatus $status): static
    {
        return $this->state(fn () => ['organization_id' => $status->organization_id, 'status_id' => $status->id]);
    }
}
