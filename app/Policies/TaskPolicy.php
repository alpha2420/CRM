<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * A to-do belongs to the person it is for and the person who set it;
 * admins can manage everyone's.
 */
class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        return $user->isAdmin() || $task->user_id === $user->id || $task->created_by === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
