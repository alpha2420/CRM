<?php

namespace Tests;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationRegistrar;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * A fully set-up workspace (trial, default statuses and sources) and
     * its admin, whose email is already verified.
     */
    protected function registerOrganization(string $name = 'Acme'): User
    {
        $admin = app(OrganizationRegistrar::class)
            ->register($name, "{$name} Admin", Str::lower(Str::random(10)).'@example.com', 'password');
        $admin->markEmailAsVerified();

        return $admin;
    }

    protected function addAgent(Organization $organization, array $attributes = []): User
    {
        return User::factory()->for($organization)->create($attributes);
    }
}
