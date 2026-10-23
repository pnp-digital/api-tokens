<?php

namespace Tests\Unit;

use App\Models\PublicTest;
use App\Models\ReadOnlyTest;
use Tests\DatabaseTestCase;

class AllowedMethodsTest extends DatabaseTestCase
{
    public function test_read_only_model_allows_get(): void
    {
        ReadOnlyTest::query()->create([
            'name' => 'Visible',
        ]);

        $this->getJson('/api/v1/read_only_test')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'Visible',
                    ],
                ],
            ]);
    }

    public function test_read_only_model_rejects_post(): void
    {
        $this->postJson('/api/v1/read_only_test', [
            'name' => 'Nope',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseMissing('read_only_tests', [
            'name' => 'Nope',
        ]);
    }

    public function test_read_only_model_rejects_put(): void
    {
        $record = ReadOnlyTest::query()->create([
            'name' => 'Original',
        ]);

        $this->putJson('/api/v1/read_only_test', [
            'id' => $record->id,
            'name' => 'Changed',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('read_only_tests', [
            'id' => $record->id,
            'name' => 'Original',
        ]);
    }

    public function test_read_only_model_rejects_delete(): void
    {
        $record = ReadOnlyTest::query()->create([
            'name' => 'Keep me',
        ]);

        $this->deleteJson('/api/v1/read_only_test', [
            'id' => $record->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('read_only_tests', [
            'id' => $record->id,
        ]);
    }

    public function test_public_model_can_be_read_without_authentication(): void
    {
        PublicTest::query()->create([
            'name' => 'Public',
        ]);

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/public_test')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'Public',
                    ],
                ],
            ]);
    }
}