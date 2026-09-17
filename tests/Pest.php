<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

/*
pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Feature/Api', 'Feature/Controller', 'Unit');
*/

use Database\Seeders\PerimetersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->in('Feature/Api', 'Feature/Controller', 'Feature/View', 'Feature/Security', 'Feature/Cartographer', 'Feature/Report');

uses(TestCase::class, RefreshDatabase::class)->in('Unit');

/*
 * Le dump de schéma (database/schema/mysql-schema.sql) marque les migrations
 * "perimeter" comme déjà appliquées, donc leur up() (qui insère le périmètre
 * par défaut id=1) ne s'exécute jamais via RefreshDatabase. On garantit ici
 * son existence avant chaque test, indépendamment de la liste de seeders que
 * le test appelle lui-même via $this->seed([...]). Ce hook est déclaré
 * séparément (sans lier de TestCase) pour s'appliquer aussi aux fichiers qui
 * font leur propre uses(TestCase::class, RefreshDatabase::class) localement
 * (ex: tests/Feature/*.php à la racine, tests/Feature/Console).
 */
uses()
    ->beforeEach(function () {
        (new PerimetersTableSeeder)->run();
    })
    ->in('Feature', 'Unit');

// Grouper automatiquement certains dossiers
uses()->group('api')->in('Feature/Api');
uses()->group('console')->in('Feature/Console');
uses()->group('controller')->in('Feature/Controller');
uses()->group('security')->in('Feature/Security');
uses()->group('cartographer')->in('Feature/Cartographer');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//     return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
