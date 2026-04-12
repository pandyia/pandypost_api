<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AuditLogResource;
use App\Services\AuditLogService;

class AuditLogController extends BaseController
{
    protected ?string $resourceClass = AuditLogResource::class;

    protected static string $permissionGroup = 'logs';
    protected static array $permissionMethods = [
        'view' => ['index'],
    ];

    public function __construct(AuditLogService $auditLogService)
    {
        parent::__construct($auditLogService);
    }
}
