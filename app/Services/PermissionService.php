<?php

namespace App\Services;

use App\Models\Permission;

class PermissionService extends BaseService
{
    protected array $with = ['roles'];

    public function __construct(Permission $permission)
    {
        parent::__construct($permission);
    }
}
