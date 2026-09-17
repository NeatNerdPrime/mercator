<?php

namespace App\Scopes;

use App\Models\Perimeter;
use App\Support\PerimeterSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restreint toute requête sur un modèle HasPerimeter au périmètre de travail
 * actif (session('active_perimeter'), voir User::activePerimeterId()).
 * No-op tant que la fonctionnalité est désactivée ou quand « tous »
 * (Perimeter::ALL_ID) est sélectionné. S'applique à tout le monde, admin
 * inclus, dès qu'un périmètre précis est actif — y compris aux requêtes de
 * liaison implicite de route (show/edit/update/destroy), pas seulement aux
 * listes : contrairement à Cartographer::scopedQuery(), qui n'est appelé que
 * par une minorité de contrôleurs, un global scope s'applique uniformément
 * partout où le modèle est interrogé.
 */
class PerimeterScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! PerimeterSettings::isEnabled()) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $active = $user->activePerimeterId();
        if ($active === Perimeter::ALL_ID) {
            return;
        }

        $builder->where($model->getTable().'.perimeter_id', $active);
    }
}
