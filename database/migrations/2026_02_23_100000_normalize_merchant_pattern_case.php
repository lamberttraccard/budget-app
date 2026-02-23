<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicates that would result from lowercasing (keep most recently updated)
        DB::statement('
            DELETE FROM merchant_rules
            WHERE id NOT IN (
                SELECT DISTINCT ON (LOWER(merchant_pattern)) id
                FROM merchant_rules
                ORDER BY LOWER(merchant_pattern), updated_at DESC
            )
        ');

        // Lowercase all existing patterns
        DB::statement('UPDATE merchant_rules SET merchant_pattern = LOWER(merchant_pattern)');

        // Drop existing unique index and re-add (values are now lowercase)
        Schema::table('merchant_rules', function ($table) {
            $table->dropUnique(['merchant_pattern']);
            $table->unique('merchant_pattern');
        });
    }

    public function down(): void
    {
        // Cannot reverse the lowercasing, but the index is the same structure
    }
};
