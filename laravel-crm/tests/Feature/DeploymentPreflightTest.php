<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeploymentPreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_succeeds_without_printing_secrets(): void
    {
        Config::set('app.key', 'base64:testing-key');
        Config::set('app.env', 'testing');
        Config::set('app.debug', false);
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        $this->artisan('ops:preflight')
            ->expectsOutput('OK  APP_KEY configured')
            ->expectsOutput('OK  Storage writable')
            ->assertExitCode(0)
            ->doesntExpectOutput('API_SECRET')
            ->doesntExpectOutput('APP_KEY=');
    }
}
