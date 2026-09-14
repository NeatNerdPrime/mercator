<?php

namespace App\Models;

use App\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * App\Role
 */
class Role extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'roles';

    protected $fillable = [
        'title',
        'perimetre_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected static function newFactory(): Factory
    {
        return RoleFactory::new();
    }

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('permissions_roles_map');
            Cache::put('roles_last_update', now()->timestamp);
        });

        static::deleted(function () {
            Cache::forget('permissions_roles_map');
            Cache::put('roles_last_update', now()->timestamp);
        });
    }

    public static function getRoleByTitle(string $title): ?Role
    {
        return Role::whereTitle($title)->first();
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /** @return HasMany<Cartographer, $this> */
    public function cartographerEntries(): HasMany
    {
        return $this->hasMany(Cartographer::class, 'role_id');
    }

    /** @return BelongsTo<Perimetre, $this> */
    public function perimetre(): BelongsTo
    {
        return $this->belongsTo(Perimetre::class);
    }
}
