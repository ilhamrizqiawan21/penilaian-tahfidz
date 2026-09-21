<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('contact', 160)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['owner_id', 'code']);
            $table->index(['owner_id', 'archived_at', 'name']);
        });

        Schema::create('study_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 160);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'archived_at']);
        });

        Schema::create('group_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('group_id')->constrained('study_groups')->restrictOnDelete();
            $table->foreignUlid('student_id')->constrained('students')->restrictOnDelete();
            $table->ulid('active_student_id')->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unique(['group_id', 'active_student_id']);
            $table->index(['student_id', 'left_at']);
        });

        Schema::create('activity_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 160);
            $table->boolean('counts_toward_progress');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_types');
        Schema::dropIfExists('group_memberships');
        Schema::dropIfExists('study_groups');
        Schema::dropIfExists('students');
    }
};
