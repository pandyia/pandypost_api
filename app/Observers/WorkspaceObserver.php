<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceObserver
{
    public function deleting(Workspace $workspace): void
    {
        $affectedUserIds = $workspace->accesses()->pluck('user_id');

        User::whereIn('id', $affectedUserIds)->each(function (User $user) {
            $user->update(['access_id' => null]);
            $user->resolveCurrentAccess();
        });
    }
}

