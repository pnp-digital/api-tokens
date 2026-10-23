<?php

namespace Tests\Feature;

use App\Models\TypescriptThing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;

class GenerateModelTypescriptTypingsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->useAppPath(dirname(__DIR__) . '/Fixtures/App');

        $resources = dirname(__DIR__) . '/Fixtures/resources';
        $storage = dirname(__DIR__) . '/Fixtures/storage';

        $this->app->instance('path.resources', $resources);
        $this->app->instance('path.storage', $storage);

        @mkdir($resources . '/types', 0777, true);
        @mkdir($storage, 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink(resource_path('types/generated.d.ts'));

        parent::tearDown();
    }

    public function test_generates_typescript_definitions_from_models(): void
    {
        $this->assertTrue(class_exists(TypescriptThing::class));
        $this->assertTrue(Schema::hasTable('typescript_things'));

        $exitCode = Artisan::call('api:definitions');

        $this->assertSame(0, $exitCode);

        $outputFile = resource_path('types/generated.d.ts');

        $this->assertFileExists($outputFile);

        $contents = file_get_contents($outputFile);

        $this->assertIsString($contents);
        $this->assertStringContainsString('TypescriptThing', $contents);
        $this->assertStringContainsString('name', $contents);
        $this->assertStringContainsString('quantity', $contents);
        $this->assertStringContainsString('price', $contents);
        $this->assertStringContainsString('active', $contents);
        $this->assertStringContainsString('available_from', $contents);
        $this->assertStringContainsString('metadata', $contents);

        $this->assertDirectoryDoesNotExist(storage_path('Generated'));
    }
}