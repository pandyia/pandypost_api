<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PermissionResource;
use App\Services\PermissionService;

class PermissionController extends BaseController
{
    protected ?string $resourceClass = PermissionResource::class;

    protected static string $permissionGroup = 'permissions';
    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
    ];

    public function __construct(
        private PermissionService $permissionService
    ) {
        parent::__construct($permissionService);
    }
}
