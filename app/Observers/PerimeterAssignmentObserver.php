<?php

namespace App\Observers;

use App\Models\User;
use App\Support\PerimeterPermissions;
use App\Support\PerimeterSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * Assigne `perimeter_id` à la création d'un objet quand il n'a pas été
 * fourni explicitement (formulaire d'un utilisateur mono/zéro-périmètre,
 * ou appel API sans le champ) : périmètre actif de l'utilisateur, ou son
 * premier périmètre, ou le périmètre par défaut (voir
 * User::activeOrDefaultPerimeterId()). No-op tant que la fonctionnalité est
 * désactivée, ou si `perimeter_id` a déjà été fixé (saisie validée).
 *
 * Applique aussi, pour les non-administrateurs, le droit d'écriture PAR PÉRIMÈTRE
 * (create/edit/delete) à chaque écriture d'un modèle, y compris les suppressions et
 * modifications de masse qui n'appellent aucun Gate portant l'objet
 * (voir PerimeterPermissions::authorizeModel()).
 */
class PerimeterAssignmentObserver
{
    public function creating(Model $model): void
    {
        if (! PerimeterSettings::isEnabled()) {
            return;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        if ($model->getAttribute('perimeter_id') === null) {
            $model->setAttribute('perimeter_id', PerimeterPermissions::defaultPerimeterFor($user, $model));
        }

        PerimeterPermissions::authorizeModel($model, 'create');
    }

    public function updating(Model $model): void
    {
        PerimeterPermissions::authorizeModel($model, 'edit');
    }

    public function deleting(Model $model): void
    {
        PerimeterPermissions::authorizeModel($model, 'delete');
    }
}
