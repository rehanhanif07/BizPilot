<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::prefix('api/v1/testing')->group(function () {
        Route::post('validation', fn () => request()->validate(['email' => ['required', 'email']]));
        Route::get('forbidden', fn () => throw new AuthorizationException);
        Route::get('model-not-found', fn () => throw (new ModelNotFoundException)->setModel('App\Models\Customer', [42]));
        Route::get('server-error', fn () => throw new RuntimeException('Secret internal detail'));
    });
});

test('validation errors use the error envelope', function () {
    $response = $this->postJson('/api/v1/testing/validation', ['email' => 'not-an-email']);

    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonStructure(['errors' => ['email']]);
});

test('authorization failures return a 403 envelope', function () {
    $response = $this->getJson('/api/v1/testing/forbidden');

    $response->assertForbidden()->assertExactJson([
        'success' => false,
        'message' => 'This action is unauthorized.',
        'errors' => [],
    ]);
});

test('missing models return a 404 without revealing the model', function () {
    $response = $this->getJson('/api/v1/testing/model-not-found');

    $response->assertNotFound()->assertExactJson([
        'success' => false,
        'message' => 'Resource not found.',
        'errors' => [],
    ]);
});

test('unknown api routes return a 404 envelope', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');
});

test('unexpected errors hide their details when debug is off', function () {
    config(['app.debug' => false]);

    $response = $this->getJson('/api/v1/testing/server-error');

    $response->assertInternalServerError()->assertExactJson([
        'success' => false,
        'message' => 'Something went wrong. Please try again later.',
        'errors' => [],
    ]);
});

test('web routes keep their default error handling', function () {
    $this->get('/does-not-exist')
        ->assertNotFound()
        ->assertDontSee('Resource not found.');
});
