<?php

namespace App\Observers;

use App\Models\User;
use App\Support\PerimeterSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * Assigne `perimeter_id` à la création d'un objet quand il n'a pas été
 * fourni explicitement (formulaire d'un utilisateur mono/zéro-périmètre,
 * ou appel API sans le champ) : périmètre actif de l'utilisateur, ou son
 * premier périmètre, ou le périmètre par défaut (voir
 * User::activeOrDefaultPerimeterId()). No-op tant que la fonctionnalité est
 * désactivée, ou si `perimeter_id` a déjà été fixé (saisie validée).
 */
class PerimeterAssignmentObserver
{
    public function creating(Model $model): void
    {
        if (! PerimeterSettings::isEnabled() || $model->getAttribute('perimeter_id') !== null) {
            return;
        }

        $user = auth()->user();
        if ($user instanceof User) {
            $model->setAttribute('perimeter_id', $user->activeOrDefaultPerimeterId());
        }
    }
}
