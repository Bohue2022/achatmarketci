<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force la réponse JSON sur les routes API.
 * Évite la redirection vers la route "login" (qui n'existe pas pour une API)
 * lorsque le client n'envoie pas l'en-tête Accept: application/json.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Force l'en-tête Accept pour que les clients ne recevant pas l'en-tête
        // (ex: curl avec `Accept: */*`) obtiennent une réponse 401 JSON au lieu
        // d'une redirection vers la route "login" (inexistante pour une API).
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}