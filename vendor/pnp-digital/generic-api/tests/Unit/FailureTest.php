<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\DatabaseTestCase;

class FailureTest extends DatabaseTestCase
{
    public function test_invalid_route_is_rejected(): void
    {
        $this->getJson('/api/v1/this_does_not_exist')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_enum_rejects_invalid_sort_field(): void
    {
        $this->getJson('/api/v1/test_status_enum?sort=sausages')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_enum_rejects_post(): void
    {
        $this->postJson('/api/v1/test_status_enum')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_encryption_key_authentication_is_rejected(): void
    {
        $this->getJson('/api/v1/user?encryption_key=nope')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_post_rejects_explicit_id(): void
    {
        $this->postJson('/api/v1/user', [
            'id' => 123,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);

        $this->assertDatabaseMissing('users', [
            'email' => 'alice@example.com',
        ]);
    }

    public function test_put_requires_id(): void
    {
        $this->putJson('/api/v1/user', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_delete_requires_id(): void
    {
        $this->deleteJson('/api/v1/user')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_delete_rejects_nonexistent_record(): void
    {
        $this->deleteJson('/api/v1/user', [
            'id' => 999999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_extended_post_is_rejected(): void
    {
        $this->postJson('/api/v1/extended/user', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['route']);

        $this->assertDatabaseMissing('users', [
            'email' => 'alice@example.com',
        ]);
    }

    public function test_extended_put_is_rejected(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $this->putJson('/api/v1/extended/user', [
            'id' => $user->id,
            'name' => 'Changed',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['route']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Alice',
        ]);
    }

    public function test_extended_delete_is_rejected(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $this->deleteJson('/api/v1/extended/user', [
            'id' => $user->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['route']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    public function test_multiple_post_requires_array_of_objects(): void
    {
        $this->postJson('/api/v1/multiple/user', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['route']);
    }

    public function test_multiple_put_rejects_top_level_id(): void
    {
        $this->putJson('/api/v1/multiple/user', [
            'id' => 123,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['route']);
    }

    public function test_multiple_put_requires_array_of_objects(): void
    {
        $this->putJson('/api/v1/multiple/user', [
            'name' => 'Alice',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['route']);
    }

    public function test_multiple_put_requires_id_on_each_record(): void
    {
        $this->putJson('/api/v1/multiple/user', [
            [
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_app_version_below_minimum_is_rejected(): void
    {
        config()->set('app.env', 'testing');

        putenv('MINIMUM_APP_VERSION=2.0.0');

        try {
            $this->getJson('/api/v1/user?app_version=1.0.0')
                ->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        } finally {
            putenv('MINIMUM_APP_VERSION');
        }
    }
}