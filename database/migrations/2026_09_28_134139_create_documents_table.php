<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('institution');
            $table->date('document_date');
            $table->string('original_filename');
            $table->text('file_path');
            $table->bigInteger('file_size');
            $table->char('document_hash', 64);
            $table->char('verification_token', 43)->unique();
            $table->string('status', 32)->default('WAITING_SIGNATURE');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 0)->useCurrent();

            $table->index('created_by');
        });

        $this->addPostgresCheck('documents_file_size_check', 'file_size >= 0');
        $this->addPostgresCheck('documents_document_hash_check', "document_hash ~ '^[0-9a-f]{64}$'");
        $this->addPostgresCheck('documents_verification_token_check', "verification_token ~ '^[A-Za-z0-9_-]{43}$'");
        $this->addPostgresCheck(
            'documents_status_check',
            "status IN ('WAITING_SIGNATURE', 'PARTIALLY_SIGNED', 'COMPLETED')",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }

    private function addPostgresCheck(string $name, string $expression): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE documents ADD CONSTRAINT %s CHECK (%s)',
            $name,
            $expression,
        ));
    }
};
