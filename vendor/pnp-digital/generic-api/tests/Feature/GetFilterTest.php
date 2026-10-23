<?php

namespace Tests\Feature;

use App\Models\FilterableThing;
use Tests\DatabaseTestCase;

class GetFilterTest extends DatabaseTestCase
{
    public function test_get_supports_in_filter(): void
    {
        FilterableThing::query()->create(['name' => 'One', 'category' => 'alpha', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Two', 'category' => 'beta', 'score' => 20]);
        FilterableThing::query()->create(['name' => 'Three', 'category' => 'gamma', 'score' => 30]);

        $response = $this->getJson('/api/v1/filterable_thing?category(in)=alpha,beta');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'One')
            ->assertJsonPath('data.1.name', 'Two');
    }

    public function test_get_supports_notin_filter(): void
    {
        FilterableThing::query()->create(['name' => 'One', 'category' => 'alpha', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Two', 'category' => 'beta', 'score' => 20]);
        FilterableThing::query()->create(['name' => 'Three', 'category' => 'gamma', 'score' => 30]);

        $response = $this->getJson('/api/v1/filterable_thing?category(notin)=alpha,gamma');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Two');
    }

    public function test_get_supports_less_than_filter(): void
    {
        FilterableThing::query()->create(['name' => 'Low', 'category' => 'test', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'High', 'category' => 'test', 'score' => 20]);

        $response = $this->getJson('/api/v1/filterable_thing?score(lt)=20');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Low');
    }

    public function test_get_supports_greater_than_filter(): void
    {
        FilterableThing::query()->create(['name' => 'Low', 'category' => 'test', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'High', 'category' => 'test', 'score' => 20]);

        $response = $this->getJson('/api/v1/filterable_thing?score(gt)=10');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'High');
    }

    public function test_get_supports_less_than_or_equal_filter(): void
    {
        FilterableThing::query()->create(['name' => 'Ten', 'category' => 'test', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Twenty', 'category' => 'test', 'score' => 20]);
        FilterableThing::query()->create(['name' => 'Thirty', 'category' => 'test', 'score' => 30]);

        $response = $this->getJson('/api/v1/filterable_thing?score(lte)=20');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data');
    }

    public function test_get_supports_greater_than_or_equal_filter(): void
    {
        FilterableThing::query()->create(['name' => 'Ten', 'category' => 'test', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Twenty', 'category' => 'test', 'score' => 20]);
        FilterableThing::query()->create(['name' => 'Thirty', 'category' => 'test', 'score' => 30]);

        $response = $this->getJson('/api/v1/filterable_thing?score(gte)=20');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data');
    }

    public function test_get_supports_not_equal_filter(): void
    {
        FilterableThing::query()->create(['name' => 'One', 'category' => 'alpha', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Two', 'category' => 'beta', 'score' => 20]);

        $response = $this->getJson('/api/v1/filterable_thing?category(neq)=alpha');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Two');
    }

    public function test_get_supports_like_filter(): void
    {
        FilterableThing::query()->create(['name' => 'Alpha One', 'category' => 'test', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Beta Two', 'category' => 'test', 'score' => 20]);

        $response = $this->getJson('/api/v1/filterable_thing?name(like)=%25Alpha%25');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha One');
    }

    public function test_get_supports_notlike_filter(): void
    {
        FilterableThing::query()->create(['name' => 'Alpha One', 'category' => 'test', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Beta Two', 'category' => 'test', 'score' => 20]);

        $response = $this->getJson('/api/v1/filterable_thing?name(notlike)=%25Alpha%25');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Beta Two');
    }

    public function test_get_supports_multiple_fields_as_or_filter(): void
    {
        FilterableThing::query()->create(['name' => 'match', 'category' => 'alpha', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Other', 'category' => 'match', 'score' => 20]);
        FilterableThing::query()->create(['name' => 'Neither', 'category' => 'beta', 'score' => 30]);

        $response = $this->getJson('/api/v1/filterable_thing?name,category=match');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data');
    }

    public function test_get_supports_wildcard_filter_across_fillable_fields(): void
    {
        FilterableThing::query()->create(['name' => 'match', 'category' => 'alpha', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Other', 'category' => 'match', 'score' => 20]);
        FilterableThing::query()->create(['name' => 'Neither', 'category' => 'beta', 'score' => 30]);

        $response = $this->getJson('/api/v1/filterable_thing?*=match');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data');
    }

    public function test_get_supports_multiple_fields_with_operator(): void
    {
        FilterableThing::query()->create(['name' => 'Alpha One', 'category' => 'other', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Other', 'category' => 'Alpha Two', 'score' => 20]);
        FilterableThing::query()->create(['name' => 'Neither', 'category' => 'other', 'score' => 30]);

        $response = $this->getJson('/api/v1/filterable_thing?name,category(like)=%25Alpha%25');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data');
    }

    public function test_get_supports_wildcard_filter_with_operator(): void
    {
        FilterableThing::query()->create(['name' => 'Alpha One', 'category' => 'other', 'score' => 10]);
        FilterableThing::query()->create(['name' => 'Other', 'category' => 'Alpha Two', 'score' => 20]);
        FilterableThing::query()->create(['name' => 'Neither', 'category' => 'other', 'score' => 30]);

        $response = $this->getJson('/api/v1/filterable_thing?*(like)=%25Alpha%25');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data');
    }
}