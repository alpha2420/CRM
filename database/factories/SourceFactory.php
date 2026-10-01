<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => ucfirst(fake()->unique()->word()),
        ];
    }
}
