<?php

namespace App\Observers;

use App\Events\InviteEvent;
use App\Models\Invite;

class InviteObserver
{
    public function created(Invite $invite): void
    {
        event(new InviteEvent('created', $invite));
    }

    public function updated(Invite $invite): void
    {
        if (! $invite->wasChanged('status')) {
            return;
        }

        event(new InviteEvent(strtolower($invite->status), $invite));
    }
}
