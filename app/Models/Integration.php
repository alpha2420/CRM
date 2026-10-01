<?php

namespace App\Models;

use App\Enums\IntegrationType;
use App\Observers\AuditTrail;
use App\Tenancy\BelongsToOrganization;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['type', 'settings', 'is_active'])]
#[Hidden(['settings'])]
#[ObservedBy(AuditTrail::class)]
class Integration extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'type' => IntegrationType::class,
            'settings' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Integration $integration) {
            $integration->webhook_key ??= Str::random(40);
        });
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Webhooks and public forms arrive without a signed-in user, so they
     * look the integration up across all tenants by its secret key.
     */
    public static function findByKey(string $key, IntegrationType ...$types): ?self
    {
        return static::withoutGlobalScope(OrganizationScope::class)
            ->where('webhook_key', $key)
            ->whereIn('type', $types)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Inbound traffic is processed only while the workspace is active and
     * its plan includes this channel.
     */
    public function acceptsTraffic(): bool
    {
        $feature = $this->type->feature();

        return $this->organization->isActive()
            && ($feature === null || $this->organization->canUse($feature));
    }
}
