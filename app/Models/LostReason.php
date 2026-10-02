<?php

namespace App\Models;

use App\Observers\AuditTrail;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Why a lead was lost ("Price too high"). With win_back_after_days set,
 * win-back reopens such leads after that many days.
 */
#[Fillable(['name', 'win_back_after_days', 'sort_order'])]
#[ObservedBy(AuditTrail::class)]
class LostReason extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['win_back_after_days' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
