<?php

namespace App\Models;

use App\Factories\PerimeterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Perimeter extends Model
{
    use HasFactory;

    /**
     * Id du périmètre par défaut, seedé par la migration de création
     * de la table. Non supprimable.
     */
    public const DEFAULT_ID = 1;

    /**
     * Sentinelle « tous mes périmètres » (aucun filtre). N'est l'id
     * d'aucun périmètre réel puisque `id` est auto-incrémenté depuis 1.
     */
    public const ALL_ID = 0;

    protected $fillable = ['name'];

    public static array $searchable = [
    ];

    protected static function newFactory(): Factory
    {
        return PerimeterFactory::new();
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'perimeter_id');
    }

    public function isDefault(): bool
    {
        return $this->id === self::DEFAULT_ID;
    }

    /**
     * Vrai si le périmètre est référencé par au moins un rôle (les tables
     * d'objets ne sont pas encore scopées à ce stade).
     */
    public function isInUse(): bool
    {
        return $this->roles()->exists();
    }
}
