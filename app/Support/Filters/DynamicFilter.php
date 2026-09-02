<?php

namespace App\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class DynamicFilter
{
    public static function filter(
        Model|Builder $query,
        array $data,
        array $normalFilter,
        array $whereHas,
        array $with = [],
        ?int $paginate = null,
        ?array $orderBy = null
    ): LengthAwarePaginator {
        $query = $query
            ->when($with, fn($q) => $q->with($with))
            ->where(fn($q) => self::applyFilters($q, $data, $normalFilter, $whereHas));

        self::applyOrderBy($query, $orderBy);

        return self::resolvePagination($query, $paginate);
    }

    private static function applyFilters(
        Builder $query,
        array $data,
        array $normalFilter,
        array $whereHas
    ): void {
        foreach ($data as $column => $value) {
            if (in_array($column, $normalFilter, true)) {
                self::applyDirectFilter($query, $column, $value);
            }

            if (array_key_exists($column, $whereHas)) {
                self::applyRelationFilter($query, $whereHas[$column], $column, $value);
            }
        }
    }

    private static function applyDirectFilter(Builder $query, string $column, mixed $value): void
    {
        if (is_numeric($value)) {
            $query->where($column, $value);
            return;
        }

        // Se a coluna for uuid ou terminar com _uuid, precisamos de tratamento especial no PostgreSQL
        if ($column === 'uuid' || str_ends_with($column, '_uuid')) {
            $driverName = $query->getConnection()->getDriverName();
            if ($driverName === 'pgsql') {
                $query->whereRaw("LOWER(CAST({$column} AS text)) LIKE LOWER(?)", ["%{$value}%"]);
                return;
            }
        }

        $query->whereRaw("LOWER({$column}) LIKE LOWER(?)", ["%{$value}%"]);
    }


    // deixa comentado, versao antiga
    /*
        private static function applyRelationFilter(
        Builder $query,
        string $relation,
        string $column,
        mixed $value
    ): void {
        $query->whereHas($relation, function ($q) use ($column, $value) {
            self::applyDirectFilter($q, $column, $value);
        });
    }
    */
    private static function applyRelationFilter(
        Builder $query,
        string|array $relationConfig,
        string $column,
        mixed $value
    ): void {
        // se vier como array, pega os dois, senao pega so a relacao
        [$relationName, $mappedColumn] = match (true) {
            is_array($relationConfig) => $relationConfig,
            default => [$relationConfig, $column],
        };

        $query->whereHas($relationName, function ($q) use ($mappedColumn, $value) {
            self::applyDirectFilter($q, $mappedColumn, $value);
        });
    }

    private static function applyOrderBy(Builder $query, ?array $orderBy): void
    {
        if (!$orderBy) {
            return;
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }
    }

    private static function resolvePagination(Builder $query, ?int $perPage): LengthAwarePaginator
    {
        if ($perPage === -1) {
            $results = $query->get();

            return new LengthAwarePaginator(
                items: $results,
                total: $results->count(),
                perPage: max($results->count(), 1),
                currentPage: 1,
                options: ['path' => request()->url()]
            );
        }

        return $query->paginate($perPage);
    }

}
