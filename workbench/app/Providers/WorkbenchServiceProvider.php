<?php

namespace Workbench\App\Providers;

use Closure;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Passport;
use Tests\Conformance\ConformanceServer;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\ServerWithDynamicTools;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * A client metadata document validator to give Passport on boot, after laravel/mcp's provider.
     */
    public static ?Closure $documentValidator = null;

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadAiRoutes();

        if (self::$documentValidator instanceof Closure && class_exists(Passport::class)) {
            Passport::validateClientMetadataDocumentsUsing(self::$documentValidator);
        }
    }

    public function loadAiRoutes(): void
    {
        // Used in tests
        Mcp::local('test-mcp', ExampleServer::class);
        Mcp::web('test-mcp', ExampleServer::class);
        Mcp::web('test-mcp-dynamic-tools', ServerWithDynamicTools::class);

        Mcp::web('conformance', ConformanceServer::class);
    }
}
