<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\DatabaseTestCase;

class ModelGetTest extends DatabaseTestCase
{
    public function test_get_users(): void
    {
        User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        User::query()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password',
        ]);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'Alice',
                        'email' => 'alice@example.com',
                    ],
                    [
                        'name' => 'Bob',
                        'email' => 'bob@example.com',
                    ],
                ],
            ]);
    }

    public function test_users_can_be_filtered(): void
    {
        User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        User::query()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password',
        ]);

        $this->getJson('/api/v1/user?name=Alice')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'Alice',
                        'email' => 'alice@example.com',
                    ],
                ],
            ])
            ->assertJsonMissing([
                'email' => 'bob@example.com',
            ]);
    }

    public function test_users_can_be_sorted(): void
    {
        User::query()->create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'password',
        ]);

        User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        User::query()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password',
        ]);

        $this->getJson('/api/v1/user?sort=name')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonPath('data.1.name', 'Bob')
            ->assertJsonPath('data.2.name', 'Charlie');
    }

    public function test_users_are_paginated(): void
    {
        foreach (range(1, 5) as $number) {
            User::query()->create([
                'name' => "User {$number}",
                'email' => "user{$number}@example.com",
                'password' => 'password',
            ]);
        }

        $this->getJson('/api/v1/user?page=2&page_size=2&sort=id')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'User 3')
            ->assertJsonPath('data.1.name', 'User 4')
            ->assertJsonPath('pagination.page', 2)
            ->assertJsonPath('pagination.page_size', 2)
            ->assertJsonPath('pagination.total_pages', 3);
    }

    public function test_soft_deleted_users_are_not_returned(): void
    {
        User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $deleted = User::query()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password',
        ]);

        $deleted->delete();

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonMissing([
                'email' => 'bob@example.com',
            ]);
    }
}