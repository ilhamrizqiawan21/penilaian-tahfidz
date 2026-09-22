<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('operation', 20);
            $table->string('status', 20);
            $table->string('storage_key', 255)->nullable();
            $table->json('manifest')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->foreignUlid('pre_restore_backup_id')->nullable()->constrained('backup_runs')->nullOnDelete();
            $table->index(['owner_id', 'operation', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
