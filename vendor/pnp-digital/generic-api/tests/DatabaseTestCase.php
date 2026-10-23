<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class DatabaseTestCase extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_enabled')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::dropIfExists('read_only_tests');

        Schema::create('read_only_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::dropIfExists('public_tests');

        Schema::create('public_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::dropIfExists('filtered_tests');

        Schema::create('filtered_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });

        Schema::dropIfExists('transformed_tests');

        Schema::create('transformed_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('after_value')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('books');
        Schema::dropIfExists('authors');

        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('authors');
            $table->string('title');
            $table->timestamps();
        });

        Schema::dropIfExists('filterable_things');

        Schema::create('filterable_things', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->unsignedInteger('score');
            $table->timestamps();
        });

        Schema::dropIfExists('typescript_things');

        Schema::create('typescript_things', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->boolean('active');
            $table->date('available_from')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
}