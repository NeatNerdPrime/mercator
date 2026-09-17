<?php

namespace App\Http\Middleware;

use App\Models\Perimeter;
use App\Support\PerimeterSettings;
use Closure;
use Illuminate\Http\Request;

/**
 * (Ré)initialise `session('active_perimeter')` pour l'utilisateur courant :
 * absente -> valeur par défaut (mono-périmètre : son id ; multi : 0 = tous) ;
 * présente mais invalide (ex. rôles modifiés) -> réinitialisée au même défaut.
 * Ne fait rien tant que la fonctionnalité périmètres est désactivée.
 */
class EnsureActivePerimeter
{
    public function handle(Request $request, Closure $next)
    {
        if (! PerimeterSettings::isEnabled()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user) {
            $ids = $user->perimeterIds();
            $current = session('active_perimeter');

            $isValid = $current !== null
                && ((int) $current === Perimeter::ALL_ID || in_array((int) $current, $ids, true));

            if (! $isValid) {
                session(['active_perimeter' => count($ids) === 1 ? $ids[0] : Perimeter::ALL_ID]);
            }
        }

        return $next($request);
    }
}
