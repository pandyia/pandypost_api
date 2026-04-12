<?php

namespace App\Services;

use App\Models\Access;

class AccessService extends BaseService
{
    protected array $with = ['user', 'role', 'workspace'];

    public function __construct(Access $access)
    {
        parent::__construct($access);
    }
}
