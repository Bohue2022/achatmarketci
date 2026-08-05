<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie que l'utilisateur authentifié est modérateur ou admin.
 */
class EnsureUserIsModerator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Non authentifié.');
        }

        if (! $user->isModerator()) {
            abort(403, 'Accès réservé à la modération.');
        }

        return $next($request);
    }
}