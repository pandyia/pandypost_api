<?php

namespace App\Services;

use App\Models\Audit;

class AuditLogService extends BaseService
{
    protected array $with = ['user', 'auditable'];
    protected array $normalFilter = ['event', 'created_at'];
    protected array $whereHas = [
        'actor_uuid' => ['user', 'uuid'],
        'entity_type' => ['auditable', 'auditable_type'],
    ];

    public function __construct(Audit $audit)
    {
        parent::__construct($audit);
    }
}
