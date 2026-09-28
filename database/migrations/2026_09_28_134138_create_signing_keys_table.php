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
        Schema::create('signing_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('algorithm', 32)->default('RSA-2048-PSS-SHA256');
            $table->text('public_key');
            $table->text('encrypted_private_key');
            $table->string('salt', 24);
            $table->string('nonce', 16);
            $table->string('auth_tag', 24);
            $table->string('kdf', 32)->default('ARGON2ID13');
            $table->bigInteger('kdf_opslimit');
            $table->bigInteger('kdf_memlimit');
            $table->timestamp('created_at', 0)->useCurrent();
        });

        $this->addPostgresCheck('signing_keys_algorithm_check', "algorithm = 'RSA-2048-PSS-SHA256'");
        $this->addPostgresCheck('signing_keys_kdf_check', "kdf = 'ARGON2ID13'");
        $this->addPostgresCheck('signing_keys_kdf_opslimit_check', 'kdf_opslimit > 0');
        $this->addPostgresCheck('signing_keys_kdf_memlimit_check', 'kdf_memlimit > 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signing_keys');
    }

    private function addPostgresCheck(string $name, string $expression): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE signing_keys ADD CONSTRAINT %s CHECK (%s)',
            $name,
            $expression,
        ));
    }
};
