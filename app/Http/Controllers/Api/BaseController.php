<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

class BaseController extends Controller implements HasMiddleware
{
    private $service;

    protected ?string $resourceClass = null;

    protected static string $permissionGroup = '';
    protected static array $permissionMethods = [];

    public static function middleware(): array
    {
        if (empty(static::$permissionGroup)) {
            return [];
        }

        $group = static::$permissionGroup;

        return collect(static::$permissionMethods)
            ->filter()
            ->map(fn (array $methods, string $ability) => new Middleware("permission:{$group}.{$ability}", only: $methods))
            ->values()
            ->all();
    }

    protected function __construct(object $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): Response
    {
        $data = $this->service->paginate($request->input());

        if ($this->resourceClass) {
            return $this->resourceClass::collection($data)->response();
        }

        return response($data, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->service->store($request->all());

        return response()->json(['message' => 'Registro inserido com sucesso.'], 201);
    }

    public function show(int|string $id): Response
    {
        $data = $this->service->findById($id);

        if ($this->resourceClass) {
            return (new $this->resourceClass($data))->response();
        }

        return response($data, 200);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {   
        $this->service->update($request->all(), $id);

        return response()->json(['message' => 'Registro atualizado com sucesso.'], 200);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $this->service->destroy($id);

        return response()->json(['message' => 'Registro deletado com sucesso.'], 200);
    }

    public function restore(int|string $id): JsonResponse
    {
        $this->service->restore($id);

        return response()->json(['message' => 'Registro restaurado com sucesso.'], 200);
    }
}
