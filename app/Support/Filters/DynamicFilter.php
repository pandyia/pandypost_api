<?php

namespace App\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Monta a listagem paginada a partir dos parâmetros da query string.
 *
 * Quem decide QUAIS parâmetros podem filtrar é o service (via $normalFilter e
 * $whereHas no BaseService). Qualquer parâmetro fora dessas listas é ignorado.
 */
class DynamicFilter
{
    /**
     * Ponto de entrada, chamado pelo BaseService::paginate().
     *
     * Ordem: carrega as relações ($with) → aplica os filtros → ordena → pagina.
     */
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
            // Eager loading das relações (evita N+1).
            ->when($with, fn($q) => $q->with($with))
            // Os filtros ficam agrupados entre parênteses: WHERE (filtro1 AND filtro2).
            // Assim eles nunca se misturam com outras condições da query, como o
            // escopo de workspace.
            ->where(fn($q) => self::applyFilters($q, $data, $normalFilter, $whereHas));

        self::applyOrderBy($query, $orderBy);

        return self::resolvePagination($query, $paginate);
    }

    /**
     * Percorre cada parâmetro recebido e aplica o filtro, se ele for permitido.
     *
     * Ex.: GET /users?name=ana&per_page=10 com $normalFilter = ['name']
     *      → filtra por name; per_page não é filtro e é ignorado aqui.
     */
    private static function applyFilters(
        Builder $query,
        array $data,
        array $normalFilter,
        array $whereHas
    ): void {
        foreach ($data as $column => $value) {
            // Coluna da própria tabela (ex.: 'name', 'email').
            if (in_array($column, $normalFilter, true)) {
                self::applyDirectFilter($query, $column, $value);
            }

            // Coluna de uma tabela relacionada (ex.: 'role_uuid' → role.uuid).
            if (array_key_exists($column, $whereHas)) {
                self::applyRelationFilter($query, $whereHas[$column], $column, $value);
            }
        }
    }

    /**
     * Aplica o filtro numa coluna:
     * - valor numérico → igualdade exata (WHERE coluna = 5);
     * - texto → busca parcial sem diferenciar maiúsculas
     *   (WHERE LOWER(coluna) LIKE '%valor%').
     *
     * O valor vai sempre como parâmetro (?), nunca concatenado no SQL. O nome da
     * coluna é concatenado, mas só chega aqui se estiver na lista do service.
     */
    private static function applyDirectFilter(Builder $query, string $column, mixed $value): void
    {
        if (is_numeric($value)) {
            $query->where($column, $value);
            return;
        }

        // Se a coluna for uuid ou terminar com _uuid, precisamos de tratamento especial no PostgreSQL
        // (lá o tipo uuid não aceita LOWER/LIKE, então convertemos para texto antes).
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
    /**
     * Filtra pela coluna de uma relação, usando whereHas.
     *
     * A configuração no $whereHas do service pode vir de duas formas:
     * - 'sender' => ['invitedBy', 'email']  → ?sender=x filtra invitedBy.email
     * - 'uuid'   => 'user'                  → ?uuid=x filtra user.uuid (mesmo nome)
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

    /**
     * Ordena conforme o $orderBy do service (padrão: ['created_at' => 'desc']).
     */
    private static function applyOrderBy(Builder $query, ?array $orderBy): void
    {
        if (!$orderBy) {
            return;
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }
    }

    /**
     * Pagina o resultado.
     *
     * per_page = -1 (padrão quando o request não envia per_page) → traz TUDO numa
     * página só, mas no mesmo formato de resposta paginada, para o front tratar
     * igual. Qualquer outro valor → paginação normal do Laravel (?page=N).
     */
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
