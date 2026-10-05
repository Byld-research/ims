<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertOk();
});

test('name and digest preference can be updated', function () {
    $user = User::factory()->create(['notify_low_stock' => true]);

    $this->actingAs($user)
        ->patch('/profile', ['name' => 'Test User'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->notify_low_stock)->toBeFalse();
});

test('email and role cannot be changed through the profile', function () {
    $user = User::factory()->operator()->create(['email' => 'original@example.com']);

    $this->actingAs($user)->patch('/profile', [
        'name' => 'Test User',
        'email' => 'changed@example.com',
        'role' => 'ADMIN',
    ]);

    $user->refresh();

    expect($user->email)->toBe('original@example.com')
        ->and($user->isOperator())->toBeTrue();
});

test('accounts cannot be deleted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/profile')->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});
