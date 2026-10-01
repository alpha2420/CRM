<?php

namespace App\Models;

use App\Observers\AuditTrail;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
#[ObservedBy(AuditTrail::class)]
class Source extends Model
{
    use BelongsToOrganization, HasFactory;

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
