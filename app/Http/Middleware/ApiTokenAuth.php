<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $header = (string) $request->bearerToken();
        if ($header === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::query()->where('api_token', hash('sha256', $header))->first();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($role === 'communicator' && ! $user->isCommunicator() && ! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($role === 'admin' && ! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
