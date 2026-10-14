<?php

namespace App\Services;

use App\Models\Access;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class AccessService extends BaseService
{
    protected array $with = ['user', 'role', 'workspace'];

    public function __construct(Access $access)
    {
        parent::__construct($access);
    }

    public function workspaceAccesses(array $data): LengthAwarePaginator
    {
        $workspaceId = auth()->user()->resolveCurrentAccess()->workspace_id;

        return $this->paginate($data, query: Access::forWorkspace($workspaceId));
    }

    public function findById(int|string $id): Model
    {
        $workspaceId = auth()->user()->resolveCurrentAccess()->workspace_id;

        return Access::forWorkspace($workspaceId)->findOrFail($id);
    }
}
