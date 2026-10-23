<?php

namespace Tests\Unit;

use Tests\TestCase;

class BackedEnumTest extends TestCase
{
    public function test_get_backed_enum(): void
    {
        $response = $this->getJson('/api/v1/test_status_enum');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'ACTIVE',
                        'unit' => 'TestStatusEnum::ACTIVE',
                        'value' => 2,
                    ],
                    [
                        'name' => 'DISABLED',
                        'unit' => 'TestStatusEnum::DISABLED',
                        'value' => 3,
                    ],
                    [
                        'name' => 'PENDING',
                        'unit' => 'TestStatusEnum::PENDING',
                        'value' => 1,
                    ],
                ],
            ]);
    }

    public function test_get_backed_enum_sorted_by_value(): void
    {
        $response = $this->getJson('/api/v1/test_status_enum?sort=value');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'PENDING',
                        'unit' => 'TestStatusEnum::PENDING',
                        'value' => 1,
                    ],
                    [
                        'name' => 'ACTIVE',
                        'unit' => 'TestStatusEnum::ACTIVE',
                        'value' => 2,
                    ],
                    [
                        'name' => 'DISABLED',
                        'unit' => 'TestStatusEnum::DISABLED',
                        'value' => 3,
                    ],
                ],
            ]);
    }

    public function test_get_backed_enum_filtered_by_value(): void
    {
        $response = $this->getJson('/api/v1/test_status_enum?value=2');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'ACTIVE',
                        'unit' => 'TestStatusEnum::ACTIVE',
                        'value' => 2,
                    ],
                ],
            ]);
    }

    public function test_get_backed_enum_filtered_by_name(): void
    {
        $response = $this->getJson('/api/v1/test_status_enum?name=DISABLED');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'DISABLED',
                        'unit' => 'TestStatusEnum::DISABLED',
                        'value' => 3,
                    ],
                ],
            ]);
    }

    public function test_get_backed_enum_paginated(): void
    {
        $response = $this->getJson(
            '/api/v1/test_status_enum?page=2&page_size=1'
        );

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'DISABLED',
                        'unit' => 'TestStatusEnum::DISABLED',
                        'value' => 3,
                    ],
                ],
                'pagination' => [
                    'page_size' => 1,
                    'page' => 2,
                    'total_pages' => 3,
                ],
            ]);
    }

    public function test_backed_enum_rejects_post(): void
    {
        $response = $this->postJson('/api/v1/test_status_enum');
        $response->assertStatus(422);
    }
}