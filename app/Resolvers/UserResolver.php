<?php

namespace App\Resolvers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use OwenIt\Auditing\Contracts\UserResolver as UserResolverContract;

class UserResolver implements UserResolverContract
{
    /**
     * Resolve o usuário responsável pela ação:
     * 1. Tenta pelos guards configurados (sanctum, web, api).
     * 2. Fallback: se o model auditado for um User, assume que ele é o ator
     *    (cobre rotas públicas como login/registro que alteram o próprio usuário).
     */
    public static function resolve()
    {
        $guards = Config::get('audit.user.guards', [
            Config::get('auth.defaults.guard'),
        ]);

        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    return Auth::guard($guard)->user();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }
}
