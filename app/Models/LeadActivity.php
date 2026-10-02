<?php

namespace App\Models;

use App\Calls\CallOutcome;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['status_id', 'note', 'next_follow_up_at', 'call_outcome', 'call_seconds'])]
class LeadActivity extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'next_follow_up_at' => 'datetime',
            'call_outcome' => CallOutcome::class,
            'call_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LeadStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class);
    }
}
