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
        Schema::create('document_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->integer('sign_order');
            $table->string('position_title');
            $table->string('status', 20)->default('PENDING');
            $table->foreignUuid('key_id')->nullable()->constrained('signing_keys')->restrictOnDelete();
            $table->string('signature', 344)->nullable();
            $table->timestamp('signed_at', 0)->nullable();
            $table->timestamp('created_at', 0)->useCurrent();

            $table->unique(['document_id', 'user_id']);
            $table->unique(['document_id', 'sign_order']);
            $table->index('user_id');
            $table->index('key_id');
        });

        $this->addPostgresCheck('document_signers_sign_order_check', 'sign_order > 0');
        $this->addPostgresCheck(
            'document_signers_status_check',
            "status IN ('PENDING', 'SIGNED')",
        );
        $this->addPostgresCheck(
            'document_signers_state_check',
            "(status = 'SIGNED' AND signature IS NOT NULL AND key_id IS NOT NULL AND signed_at IS NOT NULL) OR (status = 'PENDING' AND signature IS NULL AND key_id IS NULL AND signed_at IS NULL)",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signers');
    }

    private function addPostgresCheck(string $name, string $expression): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE document_signers ADD CONSTRAINT %s CHECK (%s)',
            $name,
            $expression,
        ));
    }
};
