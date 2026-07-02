<?php

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

test('failed zoho oauth callback does not log authorization code from query string', function () {
    config([
        'services.zoho.redirect' => 'https://app.example.test/auth/zoho/callback',
        'services.zoho.region' => 'com',
    ]);

    $provider = Mockery::mock(\Laravel\Socialite\Two\AbstractProvider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException('Invalid state.'));

    Socialite::shouldReceive('driver')->with('zoho')->andReturn($provider);

    /** @var list<MessageLogged> $logged */
    $logged = [];

    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event;
    });

    $sensitiveCode = 'zoho-oauth-code-must-not-appear-in-logs';

    $response = $this->get(route('auth.zoho.callback', [
        'code' => $sensitiveCode,
        'state' => 'test-state',
    ]));

    $response->assertRedirect(route('login'));

    $warning = collect($logged)->first(fn (MessageLogged $event): bool => $event->level === 'warning');

    expect($warning)->not->toBeNull();

    $payload = $warning->message.json_encode($warning->context);

    expect($payload)->not->toContain('code=');
    expect($payload)->not->toContain($sensitiveCode);
    expect($warning->context)->not->toHaveKey('request_url');
    expect($warning->context)->toHaveKeys([
        'step',
        'exception_class',
        'message',
        'request_host',
        'redirect_uri',
        'zoho_region',
    ]);
    expect($warning->context['redirect_uri'])->toBe('https://app.example.test/auth/zoho/callback');
    expect($warning->context['zoho_region'])->toBe('com');
    expect($warning->context['step'])->toBe('zoho_invalid_state');
});
