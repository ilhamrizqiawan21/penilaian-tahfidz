<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_datasets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('source_name');
            $table->string('source_url')->nullable();
            $table->string('version');
            $table->string('riwayah');
            $table->text('license_metadata')->nullable();
            $table->string('checksum', 64);
            $table->string('validation_status', 20);
            $table->timestamps();
        });
        Schema::create('surahs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dataset_id')->constrained('quran_datasets')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name_local');
            $table->unsignedSmallInteger('ayah_count');
            $table->unique(['dataset_id', 'number']);
        });
        Schema::create('ayahs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dataset_id')->constrained('quran_datasets')->restrictOnDelete();
            $table->foreignUlid('surah_id')->constrained('surahs')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedInteger('global_order');
            $table->text('text_uthmani')->nullable();
            $table->unique(['surah_id', 'number']);
            $table->unique(['dataset_id', 'global_order']);
        });
        Schema::create('juz_ranges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dataset_id')->constrained('quran_datasets')->restrictOnDelete();
            $table->unsignedTinyInteger('juz_number');
            $table->foreignUlid('start_ayah_id')->constrained('ayahs')->restrictOnDelete();
            $table->foreignUlid('end_ayah_id')->constrained('ayahs')->restrictOnDelete();
            $table->unique(['dataset_id', 'juz_number']);
        });
        Schema::create('programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 160);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'archived_at']);
        });
        Schema::create('program_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_id')->constrained('programs')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignUlid('dataset_id')->constrained('quran_datasets')->restrictOnDelete();
            $table->string('name_snapshot', 160);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status', 20);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'version']);
            $table->index(['program_id', 'status']);
        });
        Schema::create('program_ranges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_version_id')->constrained('program_versions')->restrictOnDelete();
            $table->foreignUlid('start_ayah_id')->constrained('ayahs')->restrictOnDelete();
            $table->foreignUlid('end_ayah_id')->constrained('ayahs')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->unique(['program_version_id', 'sort_order']);
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUlid('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignUlid('program_version_id')->constrained('program_versions')->restrictOnDelete();
            $table->foreignUlid('previous_enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->ulid('active_student_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unique(['program_id', 'active_student_id']);
            $table->index(['student_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        foreach (['enrollments', 'program_ranges', 'program_versions', 'programs', 'juz_ranges', 'ayahs', 'surahs', 'quran_datasets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
