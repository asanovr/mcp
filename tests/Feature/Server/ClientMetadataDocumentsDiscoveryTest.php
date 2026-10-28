<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;
use Symfony\Component\Process\Process;

/*
 * The Passport stand-ins are loaded once per process, and Pest cannot isolate a test, so each
 * case runs this same test again in a child process, where it finds the Passport it needs. The
 * child runs with Xdebug off: a coverage run has loaded it in the parent, and the child needs none.
 */
function inChildProcess(string $name): bool
{
    if (getenv('MCP_CHILD_PROCESS') === $name) {
        return false;
    }

    $process = new Process(
        [PHP_BINARY, dirname(__DIR__, 3).'/vendor/bin/pest', '--no-coverage', '--filter', $name, __FILE__],
        dirname(__DIR__, 3),
        ['MCP_CHILD_PROCESS' => $name, 'XDEBUG_MODE' => 'off'],
    );

    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());

    return true;
}

function discoverAuthorizationServer(): void
{
    Route::get('/oauth/authorize')->name('passport.authorizations.authorize');
    Route::post('/oauth/token')->name('passport.token');

    (new Registrar)->oauthRoutes();
}

it('does not advertise documents when the installed passport has no such query', function (): void {
    if (inChildProcess('does not advertise documents when the installed passport has no such query')) {
        return;
    }

    require_once __DIR__.'/../../Fixtures/PassportPassportWithoutClientMetadata.php';

    discoverAuthorizationServer();

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonMissingPath('client_id_metadata_document_supported');
});

it('does not advertise documents when passport is not installed', function (): void {
    if (inChildProcess('does not advertise documents when passport is not installed')) {
        return;
    }

    expect(class_exists(Passport::class, false))->toBeFalse();

    discoverAuthorizationServer();

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonMissingPath('client_id_metadata_document_supported');
});
