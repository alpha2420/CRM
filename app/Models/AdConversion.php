<?php

namespace App\Models;

use App\AdConversions\ConversionEvent;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event', 'status', 'error', 'sent_at'])]
class AdConversion extends Model
{
    use BelongsToOrganization;

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return ['event' => ConversionEvent::class, 'sent_at' => 'datetime'];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
