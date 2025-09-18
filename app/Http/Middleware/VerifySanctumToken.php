<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

class VerifySanctumToken
{
    public function handle(Request $request, Closure $next)
    {
        if ($token = $request->bearerToken()) {
            if ($model = Sanctum::$personalAccessTokenModel::findToken($token)) {
                Auth::login($model->tokenable);
                return $next($request);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
