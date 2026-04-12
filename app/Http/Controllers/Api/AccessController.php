<?php

namespace App\Http\Controllers\Api;

use App\Services\AccessService;
class AccessController extends BaseController
{
    protected static string $permissionGroup = 'accesses';
    protected static array $permissionMethods = [
        'view'   => ['index', 'show'],
        'create' => ['store'],
        'update' => ['update'],
        'delete' => ['destroy'],
    ];

    public function __construct(
        private AccessService $accessService
    ) {
        parent::__construct($accessService);
    }
}
