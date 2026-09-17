<?php

namespace App\Scopes;

use App\Models\Perimeter;
use App\Models\PhysicalLink;
use App\Support\PerimeterSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Filtrage périmètre pour PhysicalLink. Même logique qu'ApplicationFlowPerimeterScope :
 * un lien physique relie deux équipements — source et destination — qui peuvent chacun
 * appartenir à un périmètre différent du sien, donc jusqu'à 3 périmètres distincts sont
 * pertinents (son perimeter_id explicite, celui de la source, celui de la destination).
 * Le lien reste visible dès que l'un d'eux correspond au périmètre actif.
 */
class PhysicalLinkPerimeterScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! $model instanceof PhysicalLink || ! PerimeterSettings::isEnabled()) {
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

        $table = $model->getTable();

        $builder->where(function (Builder $query) use ($model, $table, $active) {
            $query->where("{$table}.perimeter_id", $active);

            foreach ($model::perimeterRelations() as $column => $relationName) {
                $relatedTable = $model->{$relationName}()->getRelated()->getTable();

                $query->orWhereExists(function (QueryBuilder $sub) use ($relatedTable, $column, $table, $active) {
                    $sub->selectRaw('1')
                        ->from($relatedTable)
                        ->whereColumn("{$relatedTable}.id", "{$table}.{$column}")
                        ->where("{$relatedTable}.perimeter_id", $active);
                });
            }
        });
    }
}
