<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('google_tokens')) {
            Schema::table('google_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('google_tokens', 'name')) {
                    $table->string('name')->nullable()->after('email');
                }
                if (!Schema::hasColumn('google_tokens', 'avatar')) {
                    $table->string('avatar', 500)->nullable()->after('name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('google_tokens')) {
            Schema::table('google_tokens', function (Blueprint $table) {
                if (Schema::hasColumn('google_tokens', 'avatar')) {
                    $table->dropColumn('avatar');
                }
                if (Schema::hasColumn('google_tokens', 'name')) {
                    $table->dropColumn('name');
                }
            });
        }
    }
};
