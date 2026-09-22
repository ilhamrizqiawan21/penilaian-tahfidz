<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mushaf_editions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dataset_id')->constrained('quran_datasets')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('version', 80);
            $table->string('status', 20);
            $table->string('checksum', 64);
            $table->text('license_metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('edition_words', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('edition_id')->constrained('mushaf_editions')->restrictOnDelete();
            $table->foreignUlid('ayah_id')->constrained('ayahs')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('source_word_key', 100);
            $table->text('text_or_glyph');
            $table->string('token_kind', 20);
            $table->unique(['edition_id', 'source_word_key']);
            $table->unique(['edition_id', 'ayah_id', 'position']);
        });
        Schema::create('assessment_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('student_id')->constrained('students')->restrictOnDelete();
            $table->ulid('current_final_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'student_id']);
        });
        Schema::create('assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('record_id')->constrained('assessment_records')->restrictOnDelete();
            $table->ulid('active_draft_record_id')->nullable()->unique();
            $table->unsignedInteger('revision_number');
            $table->foreignUlid('previous_assessment_id')->nullable()->constrained('assessments')->restrictOnDelete();
            $table->foreignUlid('enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->foreignUlid('activity_type_id')->constrained('activity_types')->restrictOnDelete();
            $table->string('activity_name_snapshot', 160);
            $table->boolean('counts_toward_progress_snapshot');
            $table->foreignUlid('rubric_version_id')->constrained('rubric_versions')->restrictOnDelete();
            $table->foreignUlid('edition_id')->constrained('mushaf_editions')->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('assessed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignUlid('last_ayah_id')->nullable()->constrained('ayahs')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->text('revision_reason')->nullable();
            $table->decimal('final_score', 18, 8)->nullable();
            $table->boolean('passed')->nullable();
            $table->string('grade_label_snapshot', 160)->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['record_id', 'revision_number']);
            $table->index(['record_id', 'status', 'assessed_at']);
        });
        Schema::table('assessment_records', function (Blueprint $table) {
            $table->foreign('current_final_id')->references('id')->on('assessments')->restrictOnDelete();
        });
        Schema::create('assessment_ranges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->string('kind', 10);
            $table->foreignUlid('start_ayah_id')->constrained('ayahs')->restrictOnDelete();
            $table->foreignUlid('end_ayah_id')->constrained('ayahs')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->unique(['assessment_id', 'kind', 'sort_order']);
        });
        Schema::create('criterion_scores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->foreignUlid('criterion_id')->constrained('criteria')->restrictOnDelete();
            $table->decimal('direct_input', 12, 4)->nullable();
            $table->decimal('computed_raw', 18, 8)->nullable();
            $table->decimal('override_raw', 12, 4)->nullable();
            $table->text('override_reason')->nullable();
            $table->decimal('normalized_score', 18, 8)->nullable();
            $table->decimal('weighted_score', 18, 8)->nullable();
            $table->unique(['assessment_id', 'criterion_id']);
        });
        Schema::create('annotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->string('client_event_id', 64);
            $table->foreignUlid('ayah_id')->constrained('ayahs')->restrictOnDelete();
            $table->foreignUlid('edition_word_id')->nullable()->constrained('edition_words')->restrictOnDelete();
            $table->string('kind', 10);
            $table->foreignUlid('mistake_rule_id')->nullable()->constrained('mistake_rules')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('retracted_at')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'client_event_id']);
            $table->index(['assessment_id', 'retracted_at']);
            $table->index(['ayah_id', 'mistake_rule_id']);
        });
        Schema::create('mutation_receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('mutation_id', 64);
            $table->string('resource_type', 40);
            $table->ulid('resource_id');
            $table->string('request_hash', 64);
            $table->unsignedInteger('result_revision');
            $table->json('response_payload');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['owner_id', 'mutation_id']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('entity_type', 40);
            $table->ulid('entity_id');
            $table->string('action', 40);
            $table->json('safe_metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('mutation_receipts');
        Schema::dropIfExists('annotations');
        Schema::dropIfExists('criterion_scores');
        Schema::dropIfExists('assessment_ranges');
        Schema::table('assessment_records', function (Blueprint $table) {
            $table->dropForeign(['current_final_id']);
        });
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_records');
        Schema::dropIfExists('edition_words');
        Schema::dropIfExists('mushaf_editions');
    }
};
