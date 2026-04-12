<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'message' => 'Você não tem permissão para acessar este recurso.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
