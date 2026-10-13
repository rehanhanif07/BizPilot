<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('guests receive a 401 error envelope', function () {
    $response = $this->getJson(route('api.v1.user'));

    $response->assertUnauthorized()->assertExactJson([
        'success' => false,
        'message' => 'Unauthenticated.',
        'errors' => [],
    ]);
});

test('authenticated users receive their profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson(route('api.v1.user'));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User retrieved successfully.')
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

test('a personal access token authenticates the request', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson(route('api.v1.user'));

    $response->assertOk()->assertJsonPath('data.id', $user->id);
});

test('api requests are rate limited with the error envelope', function () {
    Sanctum::actingAs(User::factory()->create());

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->getJson(route('api.v1.user'))->assertOk();
    }

    $response = $this->getJson(route('api.v1.user'));

    $response->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Too Many Attempts.');
});
