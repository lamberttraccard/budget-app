<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('amount_cents')->comment('Amount in smallest currency unit (cents), never floats');
            $table->date('date');
            $table->string('description');
            $table->string('dedup_hash', 64)->unique()->comment('SHA-256 hash of date+amount_cents+description+account_id');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index('category_id');
            $table->index('account_id');
            $table->index('import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
