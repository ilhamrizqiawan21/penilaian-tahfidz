<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 160);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'archived_at']);
        });
        Schema::create('rubric_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rubric_id')->constrained('rubrics')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('name_snapshot', 160);
            $table->string('status', 20);
            $table->decimal('pass_threshold', 12, 4);
            $table->unsignedSmallInteger('calculation_version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['rubric_id', 'version']);
            $table->index(['rubric_id', 'status']);
        });
        Schema::create('criteria', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rubric_version_id')->constrained('rubric_versions')->restrictOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('method', 20);
            $table->decimal('min_score', 12, 4);
            $table->decimal('max_score', 12, 4);
            $table->decimal('weight', 12, 4);
            $table->decimal('min_pass_normalized', 12, 4)->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->unique(['rubric_version_id', 'sort_order']);
        });
        Schema::create('mistake_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('criterion_id')->constrained('criteria')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('severity_label', 80)->nullable();
            $table->decimal('deduction_points', 12, 4);
        });
        Schema::create('grade_bands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rubric_version_id')->constrained('rubric_versions')->restrictOnDelete();
            $table->string('label', 160);
            $table->decimal('lower_bound', 12, 4);
            $table->decimal('upper_bound', 12, 4);
            $table->unsignedSmallInteger('sort_order');
            $table->unique(['rubric_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        foreach (['grade_bands', 'mistake_rules', 'criteria', 'rubric_versions', 'rubrics'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
