<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\DatabaseTestCase;

class ModelPostTest extends DatabaseTestCase
{
    public function test_create_user(): void
    {
        $this->postJson('/api/v1/user', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);
    }
}