<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\DatabaseTestCase;

class ModelDeleteTest extends DatabaseTestCase
{
    public function test_delete_user(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $this->deleteJson('/api/v1/user', [
            'id' => $user->id,
        ])->assertOk();

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    public function test_deleted_user_is_not_returned_by_get(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $this->deleteJson('/api/v1/user', [
            'id' => $user->id,
        ])->assertOk();

        $this->getJson('/api/v1/user?id=' . $user->id)
            ->assertOk()
            ->assertJson([
                'data' => [],
            ]);
    }

    public function test_deleting_same_user_twice_is_rejected(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $this->deleteJson('/api/v1/user', [
            'id' => $user->id,
        ])->assertOk();

        $this->deleteJson('/api/v1/user', [
            'id' => $user->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_delete_rejected_by_transformer(): void
    {
        $record = \App\Models\RejectDeleteTest::query()->create([
            'name' => 'Protected record',
        ]);

        $this->deleteJson('/api/v1/reject-delete-test', [
            'id' => $record->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);

        $this->assertDatabaseHas('transformed_tests', [
            'id' => $record->id,
            'name' => 'Protected record',
        ]);
    }
}