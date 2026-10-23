<?php

namespace Tests\Unit;

use Tests\TestCase;

class CustomEndpointTest extends TestCase
{
    public function test_get_custom_endpoint(): void
    {
        $this->getJson('/api/v1/test?value=hello')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'method' => 'GET',
                        'value' => 'hello',
                    ],
                ],
            ]);
    }

    public function test_post_custom_endpoint(): void
    {
        $this->postJson('/api/v1/test', ['value' => 'hello'])
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'method' => 'POST',
                        'value' => 'hello',
                    ],
                ],
            ]);
    }

    public function test_put_custom_endpoint(): void
    {
        $this->putJson('/api/v1/test', ['value' => 'hello'])
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'method' => 'PUT',
                        'value' => 'hello',
                    ],
                ],
            ]);
    }

    public function test_delete_custom_endpoint(): void
    {
        $this->deleteJson('/api/v1/test', ['value' => 'hello'])
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'method' => 'DELETE',
                        'value' => 'hello',
                    ],
                ],
            ]);
    }
}