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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 20);
            $table->string('position_title')->nullable();
            $table->string('institution')->nullable();
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
        });

        $this->addPostgresCheck(
            'users_role_check',
            "role IN ('ADMIN', 'SIGNER')",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }

    private function addPostgresCheck(string $name, string $expression): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE users ADD CONSTRAINT %s CHECK (%s)',
            $name,
            $expression,
        ));
    }
};
