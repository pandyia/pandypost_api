<?php

use App\Exceptions\GeneralException;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureSubscriptionAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(false);
        // Octane fica atrás do nginx: sem confiar nele, $request->ip() seria o
        // IP do container (rate limit único para todos) e o esquema seria http
        // (URLs assinadas inválidas). Só o nginx alcança o Octane em produção,
        // e ele sobrescreve esses headers em vez de repassar os do cliente.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT,
        );
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);
        $middleware->alias([
            'permission' => CheckPermission::class,
            'verified' => EnsureEmailIsVerified::class,
            'subscribed' => EnsureSubscriptionAccess::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'message' => 'Não autenticado',
                'error' => $e->getMessage(),
            ], 401);
        });

        $exceptions->render(function (RouteNotFoundException $e) {
            return response()->json([
                'message' => 'Rota de login não definida',
            ], 404);
        });

        $exceptions->render(function (AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Acesso negado',
            ], 403);
        });

        $exceptions->render(function (ThrottleRequestsException $e) {
            return GeneralException::tooManyAttempts()->render();
        });

        // $exceptions->render(function (NotFoundHttpException $e, $request) {
        //     if ($request->wantsJson() || $request->is('api/*')) {
        //         return response()->json([
        //             'message' => 'Nenhum resultado encontrado.',
        //         ], 404);
        //     }
        // });

        // $exceptions->render(function (\Throwable $e, $request) {
        //     if ($e instanceof \Illuminate\Validation\ValidationException) {
        //         return null; // Deixa o Laravel tratar normalmente
        //     }

        //     return response()->json([
        //         'message' => $e->getMessage(),
        //         'error' => 'Erro interno'
        //     ], 422);
        // });
    })->create();
