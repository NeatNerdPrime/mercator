<?php

namespace App\Http\Middleware;

use Closure;

class ForceXForwardedProto
{
    public function handle($request, Closure $next)
    {
        // Ne s'applique qu'en présence d'un reverse-proxy qui renseigne cet en-tête
        // (ex : stack Docker derrière nginx). Sans lui, on est en TLS natif
        // (ex : Apache avec SSLEngine on côté client) et $_SERVER['HTTPS'] déjà
        // positionné par le serveur web ne doit pas être écrasé.
        if ($request->hasHeader('X-Forwarded-Proto')) {
            $request->server->set('HTTPS', $request->header('X-Forwarded-Proto') === 'https' ? 'on' : 'off');
        }

        return $next($request);
    }
}
