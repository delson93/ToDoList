<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_identities', function (Blueprint $table) {
            $table->id();
            $table->string('credential', 64)->unique();
            $table->timestamps();
        });
        Schema::create('lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('category', 30);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_identity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('role', 16);
            $table->unique(['list_id', 'guest_identity_id']);
            $table->unique(['list_id', 'user_id']);
        });
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('list_id')->constrained()->cascadeOnDelete();
            $table->string('token_digest', 64)->unique();
            $table->string('role', 16);
            $table->string('name', 60)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps(6);
        });
        Schema::create('list_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('list_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 3)->nullable();
            $table->decimal('purchased', 10, 3)->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('estimated_price', 10, 2)->nullable();
            $table->decimal('actual_price', 10, 2)->nullable();
            $table->string('priority', 10)->nullable();
            $table->text('notes')->nullable();
            $table->string('alternative', 150)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index(['list_id', 'deleted_at', 'created_at']);
        });
        Schema::create('assignments', function (Blueprint $table) {
            $table->foreignUuid('item_id')->primary()->constrained('list_items')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
        });
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->unique()->constrained('list_items')->cascadeOnDelete();
            $table->string('path');
        });
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('item_id')->constrained('list_items')->cascadeOnDelete();
            $table->string('author', 60);
            $table->text('body');
            $table->timestamp('created_at');
        });
        Schema::create('activity_history', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('list_id')->constrained()->cascadeOnDelete();
            $table->string('actor', 60);
            $table->string('action', 40);
            $table->timestamp('created_at');
        });
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->foreignUuid('list_id')->constrained()->cascadeOnDelete();
            $table->uuid('operation_id');
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->string('digest', 64);
            $table->primary(['list_id', 'operation_id']);
            $table->timestamp('created_at');
        });
        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->foreignUuid('list_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('frequency', 12);
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->timestamp('next_run')->index();
        });
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 30);
            $table->json('items');
        });
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('theme', 10)->default('system');
            $table->string('locale', 10)->default('en');
        });
    }

    public function down(): void
    {
        foreach (['user_preferences', 'templates', 'recurring_schedules', 'sync_operations', 'activity_history', 'comments', 'attachments', 'assignments', 'list_items', 'invitations', 'participants', 'lists', 'guest_identities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
