<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenants\User;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
       $apiKey = $request->header('x-api-key');
       if (empty($apiKey)) {
            return response()->json(['message' => 'API Key required'], 401);
        }
        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Optionally, set user to request for further use
        $request->merge(['api_user' => $user]);
        return $next($request);
    }
}
