<?php

namespace App\Services;

use App\Support\Filters\DynamicFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseService
{
    protected Model $model;

    protected array $orderBy = ['created_at' => 'desc'];
    protected array $with = [];
    protected array $normalFilter = [];
    protected array $whereHas = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function getAll(): Collection
    {
        return $this->model->get();
    }

    public function paginate(
        array $data,
        Model|Builder|null $query = null,
        ?array $with = null,
        ?array $normalFilter = null,
        ?array $whereHas = null,
    ): LengthAwarePaginator {
        return DynamicFilter::filter(
            $query ?? $this->model,
            $data,
            $normalFilter ?? $this->normalFilter,
            $whereHas ?? $this->whereHas,
            $with ?? $this->with,
            $data['per_page'] ?? -1,
            $this->orderBy,
        );
    }

    public function findById(int|string $id): Model
    {
        return $this->model->findOrFail($id);
    }

    public function findByUuid(string $uuid): Model
    {
        return $this->model->where('uuid', $uuid)->firstOrFail();
    }

    public function store(array $data): Model
    {
        return $this->model->firstOrCreate($data);
    }

    public function update(object $entity, array $data): void
    {
        $entity->update($data);
    }

    public function updateByUuid(array $data, string $uuid): void
    {
        $entity = $this->findByUuid($uuid);
        $this->update($entity, $data);
    }

    public function destroy(int|string $id): void
    {
        $entity = $this->findById($id);
        $entity->delete();
    }

    public function destroyByUuid(string $uuid): void
    {
        $entity = $this->findByUuid($uuid);
        $entity->delete();
    }

    public function restore(int $id): void
    {
        $this->model->where('id', $id)->withTrashed()->restore();
    }
}
