<?php

namespace App\Observers;

use App\Events\RoleEvent;
use App\Exceptions\RoleException;
use App\Models\Role;

class RoleObserver
{
    public function deleting(Role $role): void
    {
        // Verifica se o acesso tem usuários vinculados, caso não, permite a exclusão
        if ($role->accesses()->exists()) {
            throw RoleException::hasLinkedUsers();
        }
    }
    public function created(Role $role): void
    {
        event(new RoleEvent('created', $role));
    }

    public function updated(Role $role): void
    {
        if ($role->wasChanged('deleted_at'))
            return;
        event(new RoleEvent('updated', $role));
    }

    public function deleted(Role $role): void
    {
        event(new RoleEvent('deleted', $role));
    }
    public function restored(Role $role): void
    {
        event(new RoleEvent('restored', $role));
    }

}
