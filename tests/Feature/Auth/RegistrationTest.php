<?php

use App\Models\Organization;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register and get an organization they own', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'organization_name' => 'Acme Ltd',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $organization = Organization::where('name', 'Acme Ltd')->firstOrFail();

    expect($user->current_organization_id)->toBe($organization->id);
    $this->assertDatabaseHas('organization_users', [
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});

test('organization name is required to register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('organization_name');
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('organizations with the same name get distinct slugs', function () {
    foreach (['first@example.com', 'second@example.com'] as $email) {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => $email,
            'organization_name' => 'Acme Ltd',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        auth()->logout();
    }

    expect(Organization::where('name', 'Acme Ltd')->pluck('slug')->unique())->toHaveCount(2);
});
