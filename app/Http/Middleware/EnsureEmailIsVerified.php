<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->hasVerifiedEmail()) {
            return response()->json([
                'error' => 'email_not_verified',
                'message' => 'Seu email ainda não foi verificado.',
            ], 403);
        }

        return $next($request);
    }
}
