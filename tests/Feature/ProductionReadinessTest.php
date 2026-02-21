<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Story 1.4: Production deployment readiness (AC1-AC4)
 *
 * Verifies TrustProxies configuration, Nightwatch installation,
 * and production-ready infrastructure settings.
 */
class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function trust_proxies_detects_https_from_forwarded_header(): void
    {
        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'budget-app.laravel.cloud',
            'HTTP_X_FORWARDED_PORT' => '443',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        // Process through the kernel to apply TrustProxies middleware
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);

        $this->assertTrue($request->isSecure(), 'Request should be detected as secure via X-Forwarded-Proto');
        $this->assertSame('https', $request->getScheme());
    }

    #[Test]
    public function nightwatch_package_is_installed(): void
    {
        $this->assertTrue(
            class_exists(\Laravel\Nightwatch\NightwatchServiceProvider::class),
            'Laravel Nightwatch service provider class should exist',
        );
    }

    #[Test]
    public function queue_config_defaults_to_database_driver(): void
    {
        // phpunit.xml overrides QUEUE_CONNECTION=sync for testing.
        // Verify the .env.example sets the production default to 'database'.
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $envExample);
    }

    #[Test]
    public function logging_stack_channel_supports_env_based_injection(): void
    {
        // Verify config/logging.php uses explode() on LOG_STACK env var
        // Critical for Laravel Cloud which sets LOG_STACK=laravel-cloud-socket,nightwatch
        $configFile = file_get_contents(config_path('logging.php'));
        $this->assertMatchesRegularExpression(
            "/explode\s*\(\s*','\s*,.*env\s*\(\s*'LOG_STACK'/",
            $configFile,
            'Stack channel must use explode() on LOG_STACK env var for Laravel Cloud compatibility',
        );
    }

    #[Test]
    public function destructive_db_commands_prohibited_in_production(): void
    {
        // Verify AppServiceProvider actually calls DB::prohibitDestructiveCommands
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString(
            'DB::prohibitDestructiveCommands',
            $source,
            'AppServiceProvider must call DB::prohibitDestructiveCommands',
        );
        $this->assertStringContainsString(
            'isProduction()',
            $source,
            'DB::prohibitDestructiveCommands must be conditional on production environment',
        );
    }

    #[Test]
    public function jobs_table_migration_exists(): void
    {
        // Verify the jobs table exists in the database (created by migrations)
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('jobs'),
            'The jobs table should exist for the database queue driver',
        );
    }

    #[Test]
    public function health_endpoint_is_accessible(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }
}
