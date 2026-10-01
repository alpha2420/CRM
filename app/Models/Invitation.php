<?php

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\BelongsToOrganization;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['email', 'role'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    use BelongsToOrganization;

    public const VALID_DAYS = 7;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * The invitee is not signed in, so the link token finds the invitation
     * across all workspaces.
     */
    public static function findPendingByToken(string $token): ?self
    {
        return static::withoutGlobalScope(OrganizationScope::class)
            ->with('organization')
            ->where('token_hash', hash('sha256', $token))
            ->pending()
            ->first();
    }
}
