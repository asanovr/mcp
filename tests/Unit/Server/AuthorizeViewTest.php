<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function renderAuthorize(Request $request, array $client = [], ?object $clientMetadata = null): string
{
    Route::delete('/oauth/authorize', fn (): null => null)->name('passport.authorizations.deny');
    Route::post('/oauth/authorize', fn (): null => null)->name('passport.authorizations.approve');

    app()->instance('request', $request);

    return view('mcp::authorize', [
        'client' => (object) ['id' => 'client-1', 'name' => 'Example', ...$client],
        'user' => (object) ['email' => 'user@example.com'],
        'scopes' => [],
        'authToken' => 'token',
        'request' => $request,
        ...($clientMetadata === null ? [] : ['clientMetadata' => $clientMetadata]),
    ])->render();
}

function documentClientMetadata(array $overrides = []): object
{
    return (object) [
        'url' => 'https://app.example.com/client.json',
        'host' => 'app.example.com',
        'name' => 'Example',
        'clientUri' => 'https://app.example.com',
        'clientUriMatchesOrigin' => true,
        'logoUri' => 'https://app.example.com/logo.png',
        'redirectIsLoopback' => false,
        'allRedirectsAreLoopback' => false,
        ...$overrides,
    ];
}

it('shows the requested redirect uri', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize', 'GET', ['redirect_uri' => 'https://app.example.com/callback']));

    expect($html)->toContain('You will be redirected to')
        ->and($html)->toContain('app.example.com')
        ->and($html)->toContain('https://app.example.com/callback');
});

it('falls back to the registered redirect uri', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize'), ['redirect_uris' => ['https://registered.example.com/cb']]);

    expect($html)->toContain('registered.example.com');
});

it('escapes the redirect uri', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize', 'GET', ['redirect_uri' => 'https://x.test/<script>alert(1)</script>']));

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('shows the host that publishes a client metadata document', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize'), clientMetadata: documentClientMetadata(['host' => 'claude.example']));

    expect($html)->toContain('Authorize Example')
        ->and($html)->toContain('Published at claude.example')
        ->and($html)->not->toContain('which is not where it is published')
        ->and($html)->not->toContain('program running on this computer');
});

it('never draws the logo of a client metadata document', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(
        Request::create('/oauth/authorize'),
        ['logo_uri' => 'https://app.example.com/registered.png'],
        documentClientMetadata(['logoUri' => 'https://app.example.com/logo.png']),
    );

    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('logo.png')
        ->and($html)->not->toContain('registered.png');
});

it('warns when the website of a client metadata document is on another origin', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize'), clientMetadata: documentClientMetadata([
        'clientUri' => 'https://other.example',
        'clientUriMatchesOrigin' => false,
    ]));

    expect($html)->toContain('which is not where it is published')
        ->and($html)->toContain('https://other.example');
});

it('escapes the website of a client metadata document', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize'), clientMetadata: documentClientMetadata([
        'clientUri' => 'https://x.test/<script>alert(1)</script>',
        'clientUriMatchesOrigin' => false,
    ]));

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('warns when approval sends the person to a program on this computer', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize'), clientMetadata: documentClientMetadata(['redirectIsLoopback' => true]));

    expect($html)->toContain('program running on this computer');
});

it('still draws the logo of a registered client', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize'), ['logo_uri' => 'https://app.example.com/registered.png']);

    expect($html)->toContain('<img src="https://app.example.com/registered.png"')
        ->and($html)->not->toContain('Published at')
        ->and($html)->not->toContain('program running on this computer');
});
