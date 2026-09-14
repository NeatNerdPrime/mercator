<?php

namespace App\Http\Middleware;

use App\Models\Perimetre;
use App\Support\PerimetreSettings;
use Closure;
use Illuminate\Http\Request;

/**
 * (Ré)initialise `session('active_perimetre')` pour l'utilisateur courant :
 * absente -> valeur par défaut (mono-périmètre : son id ; multi : 0 = tous) ;
 * présente mais invalide (ex. rôles modifiés) -> réinitialisée au même défaut.
 * Ne fait rien tant que la fonctionnalité périmètres est désactivée.
 */
class EnsureActivePerimetre
{
    public function handle(Request $request, Closure $next)
    {
        if (! PerimetreSettings::isEnabled()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user) {
            $ids = $user->perimetreIds();
            $current = session('active_perimetre');

            $isValid = $current !== null
                && ((int) $current === Perimetre::ALL_ID || in_array((int) $current, $ids, true));

            if (! $isValid) {
                session(['active_perimetre' => count($ids) === 1 ? $ids[0] : Perimetre::ALL_ID]);
            }
        }

        return $next($request);
    }
}
