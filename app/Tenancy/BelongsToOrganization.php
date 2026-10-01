<?php

namespace App\Tenancy;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Makes a model tenant-owned: reads are filtered to the current
 * organization and new rows are stamped with it. A row created with no
 * organization fails at the database (organization_id is NOT NULL).
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model) {
            $model->organization_id ??= app(TenantContext::class)->id();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
