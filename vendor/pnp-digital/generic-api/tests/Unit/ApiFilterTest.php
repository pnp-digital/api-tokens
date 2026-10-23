<?php

namespace Tests\Unit;

use App\Models\FilteredTest;
use Tests\DatabaseTestCase;

class ApiFilterTest extends DatabaseTestCase
{
    public function test_api_filter_restricts_returned_records(): void
    {
        FilteredTest::query()->create([
            'name' => 'Visible',
            'visible' => true,
        ]);

        FilteredTest::query()->create([
            'name' => 'Hidden',
            'visible' => false,
        ]);

        $this->getJson('/api/v1/filtered_test')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'VISIBLE',
                        'visible' => 1,
                    ],
                ],
            ])
            ->assertJsonMissing([
                'name' => 'HIDDEN',
            ]);
    }

    public function test_api_post_process_changes_returned_data_without_changing_database(): void
    {
        $record = FilteredTest::query()->create([
            'name' => 'Alice',
            'visible' => true,
        ]);

        $this->getJson('/api/v1/filtered_test?id=' . $record->id)
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'id' => $record->id,
                        'name' => 'ALICE',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('filtered_tests', [
            'id' => $record->id,
            'name' => 'Alice',
        ]);
    }
}