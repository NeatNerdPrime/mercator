<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perimeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Change le périmètre de travail courant (mémorisé en session), parmi les
 * périmètres de l'utilisateur authentifié ou `Perimeter::ALL_ID` (tous).
 * Choisir son propre périmètre actif n'est lié à aucune permission du
 * système : l'autorisation réelle est que la valeur soumise appartienne à
 * l'utilisateur, vérifiée ci-dessous et refusée sinon via abort_if().
 */
class PerimeterActiveController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_if(! auth()->check(), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $value = (int) $request->input('perimeter', Perimeter::ALL_ID);
        $allowed = $value === Perimeter::ALL_ID || in_array($value, auth()->user()->perimeterIds(), true);

        abort_if(! $allowed, Response::HTTP_FORBIDDEN, '403 Forbidden');

        session(['active_perimeter' => $value]);

        return redirect()->back();
    }
}
