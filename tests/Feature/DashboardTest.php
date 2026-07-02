<?php

use App\Models\User;

test('guests are redirected to the login page when visiting dashboard', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated non-supervisor users are redirected to whiteboard from dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('whiteboard'));
});

test('supervisor users can visit the dashboard', function () {
    $user = User::factory()->supervisor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
