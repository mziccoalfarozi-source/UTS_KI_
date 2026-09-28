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
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 32);
            $table->string('result_code', 40);
            $table->string('token_prefix', 8);
            $table->timestamp('created_at', 0)->useCurrent();

            $table->index('document_id');
            $table->index('created_at');
        });

        $this->addPostgresCheck(
            'verification_logs_method_check',
            "method IN ('TOKEN', 'UPLOAD', 'UPLOAD_CUSTOM_KEY')",
        );
        $this->addPostgresCheck(
            'verification_logs_result_code_check',
            "result_code IN ('VALID', 'INCOMPLETE', 'INVALID_DOCUMENT_MODIFIED', 'INVALID_SIGNATURE', 'INVALID_PUBLIC_KEY', 'REJECTED_TOKEN_NOT_FOUND')",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
    }

    private function addPostgresCheck(string $name, string $expression): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE verification_logs ADD CONSTRAINT %s CHECK (%s)',
            $name,
            $expression,
        ));
    }
};
