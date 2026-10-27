<?php

namespace App\Http\Middleware;

use App\Models\Server;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API власника: `Authorization: Bearer <секретний ключ сервера>` з кабінету.
 */
class AuthenticateServerKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $server = $request->route('server');
        $token = (string) $request->bearerToken();

        if (! $server instanceof Server || blank($server->callback_secret) || ! hash_equals($server->callback_secret, $token)) {
            return response()->json(['message' => 'Invalid server key'], 401);
        }

        return $next($request);
    }
}
