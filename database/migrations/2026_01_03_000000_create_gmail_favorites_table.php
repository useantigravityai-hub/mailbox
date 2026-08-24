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
        if (!Schema::hasTable('gmail_favorites')) {
            Schema::create('gmail_favorites', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('email')->index();
                $table->string('name')->nullable();
                $table->boolean('notify_incoming')->default(true);
                $table->boolean('notify_outgoing')->default(true);
                $table->timestamp('last_notified_at')->nullable();
                $table->string('last_message_id')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_favorites');
    }
};
