<?php

namespace Tests\Unit;

use App\Models\TransformedTest;
use Tests\DatabaseTestCase;

class InputTransformerTest extends DatabaseTestCase
{
    public function test_transformer_transforms_post_input_and_calls_after(): void
    {
        $this->postJson('/api/v1/transformed_test', [
            'name' => 'Alice',
        ])->assertOk();

        $this->assertDatabaseHas('transformed_tests', [
            'name' => 'transformed-Alice',
            'after_value' => 'after-Alice',
        ]);
    }

    public function test_transformer_transforms_put_input_and_calls_after(): void
    {
        $record = TransformedTest::query()->create([
            'name' => 'Alice',
        ]);

        $this->putJson('/api/v1/transformed_test', [
            'id' => $record->id,
            'name' => 'Bob',
        ])->assertOk();

        $this->assertDatabaseHas('transformed_tests', [
            'id' => $record->id,
            'name' => 'transformed-Bob',
            'after_value' => 'after-Bob',
        ]);
    }

    public function test_after_exception_rolls_back_post(): void
    {
        $this->postJson('/api/v1/failing_transformed_test', [
            'name' => 'Alice',
        ])->assertStatus(500);

        $this->assertDatabaseMissing('transformed_tests', [
            'name' => 'transformed-Alice',
        ]);

        $this->assertDatabaseCount('transformed_tests', 0);
    }

    public function test_after_exception_rolls_back_put(): void
    {
        $record = TransformedTest::query()->create([
            'name' => 'Alice',
        ]);

        $this->putJson('/api/v1/failing_transformed_test', [
            'id' => $record->id,
            'name' => 'Bob',
        ])->assertStatus(500);

        $this->assertDatabaseHas('transformed_tests', [
            'id' => $record->id,
            'name' => 'Alice',
        ]);

        $this->assertDatabaseMissing('transformed_tests', [
            'id' => $record->id,
            'name' => 'transformed-Bob',
        ]);
    }

    public function test_multiple_transformers_are_applied_in_order(): void
    {
        $this->postJson('/api/v1/multiple_transformed_test', [
            'name' => 'Alice',
        ])->assertOk();

        $this->assertDatabaseHas('transformed_tests', [
            'name' => 'transformed-Alice-second',
        ]);
    }

    public function test_post_rejects_invalid_transformer(): void
    {
        $this->postJson('/api/v1/invalid_transformed_test', [
            'name' => 'Alice',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['error']);

        $this->assertDatabaseCount('transformed_tests', 0);
    }

    public function test_put_rejects_invalid_transformer(): void
    {
        $record = TransformedTest::query()->create([
            'name' => 'Alice',
        ]);

        $this->putJson('/api/v1/invalid_transformed_test', [
            'id' => $record->id,
            'name' => 'Bob',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['error']);

        $this->assertDatabaseHas('transformed_tests', [
            'id' => $record->id,
            'name' => 'Alice',
        ]);

        $this->assertDatabaseMissing('transformed_tests', [
            'id' => $record->id,
            'name' => 'Bob',
        ]);
    }
}