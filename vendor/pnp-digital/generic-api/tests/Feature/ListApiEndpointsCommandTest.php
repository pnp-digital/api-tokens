<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ListApiEndpointsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->useAppPath(dirname(__DIR__) . '/Fixtures/App');
    }

    public function test_lists_model_endpoints(): void
    {
        Artisan::call('api:endpoints');

        $output = Artisan::output();

        $this->assertStringContainsString('endpoint things endpoints:', $output);

        $this->assertStringContainsString('/api/v1/endpoint_things', $output);
        $this->assertStringContainsString('/api/v1/basic/endpoint_things', $output);
        $this->assertStringContainsString('/api/v1/extended/endpoint_things', $output);

        $this->assertStringContainsString('GET', $output);
        $this->assertStringContainsString('POST', $output);
        $this->assertStringContainsString('PUT', $output);
        $this->assertStringContainsString('DELETE', $output);
    }

    public function test_respects_model_allowed_methods(): void
    {
        Artisan::call('api:endpoints');

        $output = Artisan::output();

        $start = strpos($output, 'restricted endpoint things endpoints:');
        $this->assertNotFalse($start);

        $end = strpos($output, ' endpoints:', $start + strlen('restricted endpoint things endpoints:'));
        $section = $end === false ? substr($output, $start) : substr($output, $start, $end - $start);

        $this->assertStringContainsString('/api/v1/restricted_endpoint_things', $section);
        $this->assertStringContainsString('/api/v1/basic/restricted_endpoint_things', $section);
        $this->assertStringContainsString('/api/v1/extended/restricted_endpoint_things', $section);

        $this->assertStringContainsString('GET', $section);
        $this->assertStringContainsString('PUT', $section);
        $this->assertStringNotContainsString('POST', $section);
        $this->assertStringNotContainsString('DELETE', $section);
    }

    public function test_lists_enum_endpoints(): void
    {
        Artisan::call('api:endpoints');

        $output = Artisan::output();

        $this->assertStringContainsString('endpoint statuses endpoints:', $output);
        $this->assertStringContainsString('/api/v1/basic/endpoint_statuses', $output);
    }

    public function test_lists_authentication_endpoints(): void
    {
        Artisan::call('api:endpoints');

        $output = Artisan::output();

        $this->assertStringContainsString('Authentication endpoints:', $output);
        $this->assertStringContainsString('/api/v1/login', $output);
        $this->assertStringContainsString('/api/v1/logout', $output);
        $this->assertStringContainsString('/api/v1/register', $output);
        $this->assertStringContainsString('/api/v1/resetpassword', $output);
    }
}