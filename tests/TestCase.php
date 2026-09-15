<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou anti-effacement de la vraie base de données.
     *
     * Incident du 2026-09-15 : un bootstrap/cache/config.php resté en cache localement a fait
     * ignorer .env.testing (APP_ENV=local, DB=mercator au lieu de testing/mercator_test), et
     * RefreshDatabase a alors migré/effacé la vraie base de dev à chaque lancement des tests.
     * On vérifie donc explicitement, avant toute migration, que la connexion résolue ressemble
     * bien à une base de test — indépendamment de ce que dit la config mise en cache ou non.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        if (! $this->app->environment('testing')) {
            throw new RuntimeException(
                'Tests interrompus : APP_ENV="'.$this->app->environment().'" au lieu de "testing". '.
                'Un bootstrap/cache/config.php ou un .env mal résolu ferait tourner RefreshDatabase '.
                'sur le mauvais environnement. Lance `php artisan config:clear` puis relance les tests.'
            );
        }

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($database !== null && $database !== ':memory:' && ! str_contains(strtolower((string) $database), 'test')) {
            throw new RuntimeException(
                'Tests interrompus : la base de données résolue ("'.$database.'") ne ressemble pas à une '.
                'base de test (doit contenir "test" ou être ":memory:"). RefreshDatabase migrerait/effacerait '.
                'cette base. Vérifie APP_ENV, .env.testing et un éventuel bootstrap/cache/config.php resté en cache.'
            );
        }
    }
}
