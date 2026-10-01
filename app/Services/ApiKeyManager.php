<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * Website lead-capture keys. Only a SHA-256 hash is stored, so a database
 * leak does not expose usable keys; the plain key is shown once.
 */
final class ApiKeyManager
{
    public function regenerate(Organization $organization): string
    {
        $key = 'crm_'.Str::random(40);

        $organization->forceFill(['api_key_hash' => $this->hash($key)])->save();

        return $key;
    }

    public function findOrganization(string $key): ?Organization
    {
        return Organization::query()->where('api_key_hash', $this->hash($key))->first();
    }

    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
