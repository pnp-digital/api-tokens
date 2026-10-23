<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use Tests\DatabaseTestCase;

class RelationTest extends DatabaseTestCase
{
    public function test_basic_get_does_not_include_has_many_relation(): void
    {
        $author = Author::query()->create([
            'name' => 'Douglas Adams',
        ]);

        Book::query()->create([
            'author_id' => $author->id,
            'title' => 'The Hitchhiker\'s Guide to the Galaxy',
        ]);

        $response = $this->getJson('/api/v1/author');

        $response->assertSuccessful();

        $data = $response->json('data.0');

        $this->assertSame('Douglas Adams', $data['name']);
        $this->assertArrayNotHasKey('books', $data);
    }

    public function test_extended_get_includes_has_many_relation(): void
    {
        $author = Author::query()->create([
            'name' => 'Douglas Adams',
        ]);

        Book::query()->create([
            'author_id' => $author->id,
            'title' => 'The Hitchhiker\'s Guide to the Galaxy',
        ]);

        Book::query()->create([
            'author_id' => $author->id,
            'title' => 'The Restaurant at the End of the Universe',
        ]);

        $response = $this->getJson('/api/v1/extended/author');

        $response->assertSuccessful()
            ->assertJsonPath('data.0.name', 'Douglas Adams')
            ->assertJsonPath('data.0.books.0.title', 'The Hitchhiker\'s Guide to the Galaxy')
            ->assertJsonPath('data.0.books.1.title', 'The Restaurant at the End of the Universe');
    }

    public function test_extended_get_includes_belongs_to_relation(): void
    {
        $author = Author::query()->create([
            'name' => 'Douglas Adams',
        ]);

        Book::query()->create([
            'author_id' => $author->id,
            'title' => 'The Hitchhiker\'s Guide to the Galaxy',
        ]);

        $response = $this->getJson('/api/v1/extended/book');

        $response->assertSuccessful()
            ->assertJsonPath('data.0.title', 'The Hitchhiker\'s Guide to the Galaxy')
            ->assertJsonPath('data.0.author.name', 'Douglas Adams');
    }
}