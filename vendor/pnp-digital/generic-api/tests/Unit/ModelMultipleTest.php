<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\DatabaseTestCase;

class ModelMultipleTest extends DatabaseTestCase
{
    public function test_multiple_post_creates_users(): void
    {
        $this->postJson('/api/v1/multiple/user', [
            [
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'password' => 'password',
            ],
            [
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'password' => 'password',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_multiple_put_updates_users(): void
    {
        $alice = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $bob = User::query()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password',
        ]);

        $this->putJson('/api/v1/multiple/user', [
            [
                'id' => $alice->id,
                'name' => 'Alice Updated',
                'email' => 'alice@example.com',
            ],
            [
                'id' => $bob->id,
                'name' => 'Bob Updated',
                'email' => 'bob@example.com',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $alice->id,
            'name' => 'Alice Updated',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $bob->id,
            'name' => 'Bob Updated',
        ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_multiple_delete_deletes_users(): void
    {
        $alice = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $bob = User::query()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password',
        ]);

        $charlie = User::query()->create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'password',
        ]);

        $this->deleteJson('/api/v1/multiple/user', [
            'id' => [
                $alice->id,
                $bob->id,
            ],
        ])->assertOk();

        $this->assertSoftDeleted('users', [
            'id' => $alice->id,
        ]);

        $this->assertSoftDeleted('users', [
            'id' => $bob->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $charlie->id,
            'deleted_at' => null,
        ]);
    }
}