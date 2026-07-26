<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('ndc.admin_key');
        $provided = (string) ($request->session()->get('ndc_admin_ok') ? $expected : $request->input('admin_key', $request->header('X-Admin-Key')));

        if ($request->session()->get('ndc_admin_ok') === true) {
            return $next($request);
        }

        if ($provided !== '' && hash_equals($expected, $provided)) {
            $request->session()->put('ndc_admin_ok', true);

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return redirect()->route('admin.login');
    }
}
