<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;
use Workbench\App\Providers\WorkbenchServiceProvider;

beforeEach(function (): void {
    if (! class_exists(Passport::class)) {
        require_once __DIR__.'/../../Fixtures/PassportPassport.php';
    }

    // An application booted before this test loaded the stand-in, or left its wrapper behind.
    Passport::validateClientMetadataDocumentsUsing(null);
});

afterEach(function (): void {
    Passport::validateClientMetadataDocumentsUsing(null);
    WorkbenchServiceProvider::$documentValidator = null;
});

/**
 * Run Passport's document validator the way its document validator does.
 *
 * @param  array<int, mixed>  $redirectUris
 */
function checkDocument(array $redirectUris): ?string
{
    return (Passport::$clientMetadataDocumentValidator)(
        ['client_id' => 'https://client.example/doc.json', 'redirect_uris' => $redirectUris],
        'https://client.example/doc.json',
    );
}

it('refuses a redirect outside the redirect domains and passes one inside', function (): void {
    config()->set('mcp.redirect_domains', ['https://allowed.example']);
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument(['https://evil.example/cb']))
        ->toBe('The redirect URI [https://evil.example/cb] is not a permitted redirect domain.')
        ->and(checkDocument(['https://allowed.example/cb']))->toBeNull();
});

it('lets anything through when every domain is allowed, and the application still decides', function (): void {
    config()->set('mcp.redirect_domains', ['*']);
    Passport::validateClientMetadataDocumentsUsing(fn (array $document): string => 'No thanks.');
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument(['https://evil.example/cb']))->toBe('No thanks.');

    Passport::validateClientMetadataDocumentsUsing(null);
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument(['https://evil.example/cb']))->toBeNull();
});

it('lets a localhost redirect through only when a localhost domain is listed', function (): void {
    Registrar::ensureClientMetadataDocumentPolicy();

    config()->set('mcp.redirect_domains', ['https://allowed.example']);
    expect(checkDocument(['http://localhost:5555/cb']))->not->toBeNull();

    config()->set('mcp.redirect_domains', ['https://allowed.example', 'http://localhost']);
    expect(checkDocument(['http://localhost:5555/cb']))->toBeNull();
});

it('refuses a redirect that is not a valid URL before it asks about the domain', function (string $uri): void {
    config()->set('mcp.redirect_domains', ['https://allowed.example']);
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument([$uri]))->toBe("The redirect URI [{$uri}] is not a valid URL.");
})->with(['HTTPS://evil.example/cb', 'HTTP://localhost:5555/cb', 'not-a-url']);

it('asks the application only when the policy passes, and returns its refusal', function (): void {
    $calls = 0;
    config()->set('mcp.redirect_domains', ['https://allowed.example']);
    Passport::validateClientMetadataDocumentsUsing(function (array $document, string $url) use (&$calls): string {
        $calls++;

        return "Refused {$url}.";
    });
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument(['https://evil.example/cb']))
        ->toBe('The redirect URI [https://evil.example/cb] is not a permitted redirect domain.')
        ->and($calls)->toBe(0)
        ->and(checkDocument(['https://allowed.example/cb']))->toBe('Refused https://client.example/doc.json.')
        ->and($calls)->toBe(1);
});

it('treats a previous answer that is not a non-empty string as no refusal', function (mixed $answer): void {
    config()->set('mcp.redirect_domains', ['*']);
    Passport::validateClientMetadataDocumentsUsing(fn (): mixed => $answer);
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument(['https://allowed.example/cb']))->toBeNull();
})->with([false, '', 0, null]);

it('checks every listed redirect and names the first one refused', function (): void {
    config()->set('mcp.redirect_domains', ['https://allowed.example']);
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument(['https://allowed.example/cb', 'https://evil.example/cb', 'https://other.example/cb']))
        ->toBe('The redirect URI [https://evil.example/cb] is not a permitted redirect domain.');
});

it('leaves one wrapper when it boots twice', function (): void {
    $calls = 0;
    config()->set('mcp.redirect_domains', ['*']);
    Passport::validateClientMetadataDocumentsUsing(function () use (&$calls): ?string {
        $calls++;

        return null;
    });

    Registrar::ensureClientMetadataDocumentPolicy();
    $wrapper = Passport::$clientMetadataDocumentValidator;
    Registrar::ensureClientMetadataDocumentPolicy();
    $this->refreshApplication();

    expect(Passport::$clientMetadataDocumentValidator)->toBe($wrapper);

    checkDocument(['https://allowed.example/cb']);

    expect($calls)->toBe(1);
});

it('applies a change of the redirect domains made after boot', function (): void {
    config()->set('mcp.redirect_domains', ['https://allowed.example']);
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument(['https://later.example/cb']))->not->toBeNull();

    config()->set('mcp.redirect_domains', ['https://later.example']);

    expect(checkDocument(['https://later.example/cb']))->toBeNull();
});

it('wraps a validator that a later provider sets while booting', function (): void {
    WorkbenchServiceProvider::$documentValidator = fn (): string => 'Refused by the application.';

    $this->refreshApplication();
    config()->set('mcp.redirect_domains', ['https://allowed.example']);

    expect(checkDocument(['https://evil.example/cb']))
        ->toBe('The redirect URI [https://evil.example/cb] is not a permitted redirect domain.')
        ->and(checkDocument(['https://allowed.example/cb']))->toBe('Refused by the application.');
});

it('wraps again a validator the application sets after boot', function (): void {
    config()->set('mcp.redirect_domains', ['https://allowed.example']);
    Registrar::ensureClientMetadataDocumentPolicy();
    Passport::validateClientMetadataDocumentsUsing(fn (): string => 'Mine.');
    Registrar::ensureClientMetadataDocumentPolicy();

    expect(checkDocument(['https://evil.example/cb']))->toContain('not a permitted redirect domain')
        ->and(checkDocument(['https://allowed.example/cb']))->toBe('Mine.');
});
