<?php

use App\Exceptions\MonarcApiException;
use App\Services\MonarcApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'monarc.url' => 'http://monarc.test',
        'monarc.uid' => 'admin@admin.localhost',
        'monarc.password' => 'admin',
        'monarc.cache_ttl' => 300,
        'monarc.timeout' => 15,
    ]);
    Cache::flush();
});

function fakeMonarcAuth(string $token = 'fake-token'): void
{
    Http::fake([
        'monarc.test/auth' => Http::response(['token' => $token, 'uid' => 1, 'language' => 1], 200),
    ]);
}

test('authenticate returns the token from a successful login', function () {
    Http::fake([
        'monarc.test/auth' => Http::response(['token' => 'abc123', 'uid' => 1, 'language' => 1], 200),
    ]);

    $service = new MonarcApiService;

    expect($service->authenticate())->toBe('abc123');

    Http::assertSent(fn ($request) => $request->url() === 'http://monarc.test/auth'
        && $request['login'] === 'admin@admin.localhost'
        && $request['password'] === 'admin');
});

test('authenticate throws when the Monarc connection is not configured', function () {
    config(['monarc.url' => '', 'monarc.uid' => '', 'monarc.password' => '']);

    $service = new MonarcApiService;

    expect(fn () => $service->authenticate())->toThrow(MonarcApiException::class);
});

test('authenticate throws on invalid credentials (401)', function () {
    Http::fake([
        'monarc.test/auth' => Http::response(['errors' => ['Invalid credentials']], 401),
    ]);

    $service = new MonarcApiService;

    expect(fn () => $service->authenticate())->toThrow(MonarcApiException::class);
});

test('authenticate throws a translated error when the host is unreachable', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $service = new MonarcApiService;

    expect(fn () => $service->authenticate())->toThrow(MonarcApiException::class);
});

test('testConnection reports success', function () {
    fakeMonarcAuth();

    expect((new MonarcApiService)->testConnection())->toBe([trans('cruds.configuration.monarc.test_connection_success'), true]);
});

test('testConnection reports failure', function () {
    Http::fake([
        'monarc.test/auth' => Http::response([], 401),
    ]);

    [$message, $ok] = (new MonarcApiService)->testConnection();
    expect($ok)->toBeFalse();
    expect($message)->toBe(trans('cruds.configuration.monarc.error_auth_failed'));
});

test('getAnrs returns the anrs list and is cached across calls', function () {
    Http::fake([
        'monarc.test/auth' => Http::response(['token' => 'tok', 'uid' => 1, 'language' => 1], 200),
        'monarc.test/api/client-anr' => Http::response(['count' => 1, 'anrs' => [['id' => 3, 'label' => 'HIS']]], 200),
    ]);

    $service = new MonarcApiService;
    $anrs = $service->getAnrs();
    expect($anrs)->toBe([['id' => 3, 'label' => 'HIS']]);

    // Second call, even from a fresh service instance, hits the Laravel cache, not the API.
    $second = (new MonarcApiService)->getAnrs();
    expect($second)->toBe($anrs);

    Http::assertSentCount(2); // 1 auth + 1 client-anr, not repeated for the second getAnrs() call
});

test('fetchAnrExport-backed accessors map knowledgeBase sections correctly', function () {
    fakeMonarcAuth();
    Http::fake([
        'monarc.test/auth' => Http::response(['token' => 'tok', 'uid' => 1, 'language' => 1], 200),
        'monarc.test/api/client-anr/3/export' => Http::response([
            'knowledgeBase' => [
                'assets' => [['uuid' => 'a1', 'code' => 'BAT_LOC']],
                'threats' => [['uuid' => 't1']],
                'vulnerabilities' => [['uuid' => 'v1']],
                'informationRisks' => [['uuid' => 'amv1']],
                'referentials' => [['uuid' => 'r1']],
                'rolfTags' => [['id' => 1]],
                'operationalRisks' => [['id' => 1]],
            ],
            'scales' => ['1' => ['min' => 0, 'max' => 4]],
            'operationalRiskScales' => ['1' => []],
            'method' => ['steps' => []],
            'thresholds' => ['seuil1' => 8],
            'soas' => [['id' => 1]],
            'soaScaleComments' => [['id' => 1]],
            'monarc_version' => '2.13.3',
            'languageCode' => 'fr',
        ], 200),
    ]);

    $service = new MonarcApiService;

    expect($service->getAssets(3))->toBe([['uuid' => 'a1', 'code' => 'BAT_LOC']]);
    expect($service->getThreats(3))->toBe([['uuid' => 't1']]);
    expect($service->getVulnerabilities(3))->toBe([['uuid' => 'v1']]);
    expect($service->getAmvs(3))->toBe([['uuid' => 'amv1']]);
    expect($service->getReferentials(3))->toBe([['uuid' => 'r1']]);
    expect($service->getRolfTags(3))->toBe([['id' => 1]]);
    expect($service->getOperationalRisks(3))->toBe([['id' => 1]]);
    expect($service->getScales(3))->toBe(['1' => ['min' => 0, 'max' => 4]]);
    expect($service->getMethod(3))->toBe(['steps' => []]);
    expect($service->getThresholds(3))->toBe(['seuil1' => 8]);
    expect($service->getSoas(3))->toBe([['id' => 1]]);
    expect($service->getMonarcVersion(3))->toBe('2.13.3');

    // All the accessors above share the single cached export fetch.
    Http::assertSentCount(2); // 1 auth + 1 export
});

test('export request sends the field names the FO actually reads, not the response with* names', function () {
    fakeMonarcAuth();
    Http::fake([
        'monarc.test/auth' => Http::response(['token' => 'tok', 'uid' => 1, 'language' => 1], 200),
        'monarc.test/api/client-anr/3/export' => Http::response(['knowledgeBase' => []], 200),
    ]);

    (new MonarcApiService)->getAssets(3);

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/export')) {
            return true;
        }

        return $request['knowledgeBase'] === true
            && $request['assessments'] === true
            && $request['methodSteps'] === true
            && $request['soas'] === true
            && $request['assetsLibrary'] === false;
    });
});

test('a 401 mid-session triggers one re-authentication and retry', function () {
    Http::fake([
        'monarc.test/auth' => Http::sequence()
            ->push(['token' => 'expired-token', 'uid' => 1, 'language' => 1], 200)
            ->push(['token' => 'fresh-token', 'uid' => 1, 'language' => 1], 200),
        'monarc.test/api/client-anr' => Http::sequence()
            ->push(['errors' => ['expired']], 401)
            ->push(['count' => 0, 'anrs' => []], 200),
    ]);

    $service = new MonarcApiService;
    $anrs = $service->getAnrs();

    expect($anrs)->toBe([]);
    Http::assertSentCount(4); // auth, client-anr(401), re-auth, client-anr(200)
});

test('a persistent API failure throws a translated exception', function () {
    fakeMonarcAuth();
    Http::fake([
        'monarc.test/auth' => Http::response(['token' => 'tok', 'uid' => 1, 'language' => 1], 200),
        'monarc.test/api/client-anr' => Http::response(['errors' => ['boom']], 500),
    ]);

    $service = new MonarcApiService;

    expect(fn () => $service->getAnrs())->toThrow(MonarcApiException::class);
});
