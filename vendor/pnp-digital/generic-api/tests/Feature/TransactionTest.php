<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\DatabaseTestCase;

class TransactionTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_transaction_can_be_started(): void
    {
        $response = $this->postJson('/api/v1/transaction/start');

        $response->assertSuccessful()
            ->assertJsonPath('data.response', 'Transaction started');
    }

    public function test_post_is_staged_until_transaction_is_committed(): void
    {
        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->postJson('/api/v1/user', [
            'name' => 'Staged User',
            'email' => 'staged@example.com',
            'password' => 'password',
        ])->assertSuccessful()
            ->assertJsonPath('data.response', 'Queued, commit transaction to apply');

        $this->assertDatabaseMissing('users', [
            'email' => 'staged@example.com',
        ]);

        $this->postJson('/api/v1/transaction/commit')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'name' => 'Staged User',
            'email' => 'staged@example.com',
        ]);
    }

    public function test_put_is_staged_until_transaction_is_committed(): void
    {
        $user = User::query()->create([
            'name' => 'Before',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->putJson('/api/v1/user', [
            'id' => $user->id,
            'name' => 'After',
        ])->assertSuccessful()
            ->assertJsonPath('data.response', 'Queued, commit transaction to apply');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Before',
        ]);

        $this->postJson('/api/v1/transaction/commit')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'After',
        ]);
    }

    public function test_post_and_put_are_committed_together(): void
    {
        $existing = User::query()->create([
            'name' => 'Before',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->postJson('/api/v1/user', [
            'name' => 'Created',
            'email' => 'created@example.com',
            'password' => 'password',
        ])->assertSuccessful();

        $this->putJson('/api/v1/user', [
            'id' => $existing->id,
            'name' => 'Updated',
        ])->assertSuccessful();

        // Neither mutation has happened yet.
        $this->assertDatabaseMissing('users', [
            'email' => 'created@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'name' => 'Before',
        ]);

        $this->postJson('/api/v1/transaction/commit')->assertSuccessful();

        // Both mutations have now happened.
        $this->assertDatabaseHas('users', [
            'name' => 'Created',
            'email' => 'created@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'name' => 'Updated',
        ]);
    }

    public function test_cannot_start_transaction_when_one_is_already_active(): void
    {
        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->postJson('/api/v1/transaction/start')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction']);
    }

    public function test_cannot_commit_when_no_transaction_is_active(): void
    {
        $this->postJson('/api/v1/transaction/commit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction']);
    }

    public function test_cannot_rollback_when_no_transaction_is_active(): void
    {
        $this->postJson('/api/v1/transaction/rollback')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction']);
    }

    public function test_rollback_discards_staged_post(): void
    {
        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->postJson('/api/v1/user', [
            'name' => 'Never Created',
            'email' => 'never-created@example.com',
            'password' => 'password',
        ])->assertSuccessful();

        $this->postJson('/api/v1/transaction/rollback')
            ->assertSuccessful()
            ->assertJsonPath('data.response', 'Transaction rolled back');

        $this->assertDatabaseMissing('users', [
            'email' => 'never-created@example.com',
        ]);

        $this->postJson('/api/v1/transaction/commit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction']);
    }

    public function test_rollback_discards_staged_put(): void
    {
        $user = User::query()->create([
            'name' => 'Original',
            'email' => 'rollback@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->putJson('/api/v1/user', [
            'id' => $user->id,
            'name' => 'Never Applied',
        ])->assertSuccessful();

        $this->postJson('/api/v1/transaction/rollback')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Original',
        ]);
    }

    public function test_commit_is_rejected_when_staged_model_has_changed(): void
    {
        $user = User::query()->create([
            'name' => 'Original',
            'email' => 'revision@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->putJson('/api/v1/user', [
            'id' => $user->id,
            'name' => 'Staged Change',
        ])->assertSuccessful();

        // The staged PUT hasn't happened.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Original',
        ]);

        $otherUser = User::query()->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($otherUser);

        $this->putJson('/api/v1/user', [
            'id' => $user->id,
            'name' => 'Intervening Change',
        ])->assertSuccessful();

        $this->actingAs(User::query()->findOrFail(1));

        $this->postJson('/api/v1/transaction/commit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction']);

        // The intervening mutation remains, but the staged one was not applied.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Intervening Change',
        ]);
    }

    public function test_failed_commit_rolls_back_all_staged_changes(): void
    {
        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->postJson('/api/v1/user', [
            'name' => 'First',
            'email' => 'duplicate@example.com',
            'password' => 'password',
        ])->assertSuccessful();

        $this->postJson('/api/v1/user', [
            'name' => 'Second',
            'email' => 'duplicate@example.com',
            'password' => 'password',
        ])->assertSuccessful();

        $this->assertDatabaseMissing('users', [
            'email' => 'duplicate@example.com',
        ]);

        try {
            $this->postJson('/api/v1/transaction/commit');
            $this->fail('Expected commit to fail');
        } catch (\Throwable) {
        }

        $this->assertDatabaseMissing('users', [
            'email' => 'duplicate@example.com',
        ]);

        // A failed commit must leave the staged transaction intact.
        $this->postJson('/api/v1/transaction/start')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction']);

        $this->postJson('/api/v1/transaction/rollback')->assertSuccessful();

        // Rollback removed it.
        $this->postJson('/api/v1/transaction/start')->assertSuccessful();
    }

    public function test_delete_is_staged_until_transaction_is_committed(): void
    {
        $user = User::query()->create([
            'name' => 'Delete Me',
            'email' => 'delete-transaction@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/v1/transaction/start')->assertSuccessful();

        $this->deleteJson('/api/v1/user', [
            'id' => $user->id,
        ])->assertSuccessful()
            ->assertJsonPath('data.response', 'Queued, commit transaction to apply');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);

        $this->postJson('/api/v1/transaction/commit')->assertSuccessful();

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    public function test_multiple_put_inside_transaction_is_applied_on_commit(): void
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

        $this->postJson('/api/v1/transactionStart')
            ->assertOk();

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
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $alice->id,
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $charlie->id,
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
        ]);

        $this->postJson('/api/v1/transactionCommit')
            ->assertOk();

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
    }

    public function test_multiple_post_inside_transaction_is_applied_on_commit(): void
    {
        $this->postJson('/api/v1/transactionStart')
            ->assertOk();

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

        $this->assertDatabaseMissing('users', [
            'email' => 'alice@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'bob@example.com',
        ]);

        $this->postJson('/api/v1/transactionCommit')
            ->assertOk();

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

    public function test_multiple_delete_inside_transaction_is_applied_on_commit(): void
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

        $this->postJson('/api/v1/transactionStart')
            ->assertOk();

        $this->deleteJson('/api/v1/multiple/user', [
            'id' => [
                $alice->id,
                $bob->id,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $alice->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $bob->id,
        ]);

        $this->postJson('/api/v1/transactionCommit')
            ->assertOk();

        $this->assertSoftDeleted('users', [
            'id' => $alice->id,
        ]);

        $this->assertSoftDeleted('users', [
            'id' => $bob->id,
        ]);
    }
}