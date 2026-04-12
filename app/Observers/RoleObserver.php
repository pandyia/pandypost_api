<?php

namespace App\Observers;

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
}
