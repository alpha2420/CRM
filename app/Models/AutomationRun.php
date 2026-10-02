<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One time a rule ran for a lead. Lets time-based and message rules fire
 * once per occasion instead of on every check.
 */
class AutomationRun extends Model
{
    use BelongsToOrganization, MassPrunable;

    public const UPDATED_AT = null;

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::withoutGlobalScopes()->where('created_at', '<', now()->subDays(180));
    }
}
