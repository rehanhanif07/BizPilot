<?php

test('health endpoint reports the api as running', function () {
    $response = $this->getJson(route('api.v1.health'));

    $response->assertOk()->assertExactJson([
        'success' => true,
        'message' => 'BizPilot API is running.',
        'data' => ['database' => 'ok', 'cache' => 'ok'],
    ]);
});

test('health endpoint reports a failing dependency with a 503', function () {
    config([
        'cache.limiter' => 'array',
        'cache.default' => 'redis',
        'database.redis.cache.port' => 1,
    ]);

    $response = $this->getJson(route('api.v1.health'));

    $response->assertServiceUnavailable()->assertExactJson([
        'success' => false,
        'message' => 'BizPilot API is degraded.',
        'errors' => ['database' => 'ok', 'cache' => 'failing'],
    ]);
});

test('health endpoint is not rate limited so monitors can poll it', function () {
    for ($attempt = 1; $attempt <= 61; $attempt++) {
        $this->getJson(route('api.v1.health'))->assertOk();
    }
});
