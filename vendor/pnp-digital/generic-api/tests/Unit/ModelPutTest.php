<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\DatabaseTestCase;

class ModelPutTest extends DatabaseTestCase
{
    public function test_update_user(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $this->putJson('/api/v1/user', [
            'id' => $user->id,
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ])
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'id' => $user->id,
                        'name' => 'Bob',
                        'email' => 'bob@example.com',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'name' => 'Alice',
        ]);
    }

    public function test_update_user_does_not_create_another_record(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $this->putJson('/api/v1/user', [
            'id' => $user->id,
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ])->assertOk();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_update_multiple_users(): void
    {
        $alice = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $charlie = User::query()->create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'password',
        ]);

        $this->putJson('/api/v1/multiple/user', [
            [
                'id' => $alice->id,
                'name' => 'Bob',
                'email' => 'bob@example.com',
            ],
            [
                'id' => $charlie->id,
                'name' => 'Dave',
                'email' => 'dave@example.com',
            ],
        ])
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'id' => $alice->id,
                        'name' => 'Bob',
                        'email' => 'bob@example.com',
                    ],
                    [
                        'id' => $charlie->id,
                        'name' => 'Dave',
                        'email' => 'dave@example.com',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $alice->id,
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $charlie->id,
            'name' => 'Dave',
            'email' => 'dave@example.com',
        ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_multiple_update_requires_array_of_objects(): void
    {
        $this->putJson('/api/v1/multiple/user', [
            'foo' => 'bar',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('route')
            ->assertJsonPath('errors.route.0', 'Multiple PUT must contain JSON array of objects');
    }

    public function test_multiple_update_requires_id_for_each_record(): void
    {
        $this->putJson('/api/v1/multiple/user', [
            [
                'name' => 'Bob',
                'email' => 'bob@example.com',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id')
            ->assertJsonPath('errors.id.0', "Each record's ID must be specified for multiple PUT");
    }

    public function test_multiple_update_rejects_top_level_id(): void
    {
        $this->putJson('/api/v1/multiple/user', [
            'id' => 1,
            [
                'id' => 1,
                'name' => 'Bob',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('route')
            ->assertJsonPath('errors.route.0', 'You cannot specify a top level single ID with multiple PUT');
    }
}