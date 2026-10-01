<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Users of another organization are reported as "not found" rather
     * than "forbidden", so their existence is not revealed.
     */
    public function update(User $actor, User $user): Response
    {
        if ($actor->organization_id !== $user->organization_id) {
            return Response::denyAsNotFound();
        }

        return $actor->isAdmin() ? Response::allow() : Response::deny();
    }

    public function delete(User $actor, User $user): Response
    {
        $response = $this->update($actor, $user);

        if ($response->allowed() && $actor->is($user)) {
            return Response::deny('You cannot delete your own account.');
        }

        return $response;
    }
}
