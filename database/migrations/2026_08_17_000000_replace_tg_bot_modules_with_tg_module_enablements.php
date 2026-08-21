<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the dead, schema-drifted tg_bot_modules table (model/migration
 * mismatch, zero runtime readers) with tg_module_enablements.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('tg_bot_modules');

        Schema::create('tg_module_enablements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // NULL bot_id = platform-level override; NULL chat_id = bot-level default
            $table->string('bot_id', 20)->nullable();
            $table->bigInteger('chat_id')->nullable();
            $table->string('module_id', 100);
            $table->boolean('is_enabled')->default(true);
            $table->json('module_settings')->nullable();

            $table->foreign('bot_id')
                ->references('bot_id')->on('tg_bots')
                ->cascadeOnDelete();

            $table->unique(['bot_id', 'chat_id', 'module_id']);
            $table->index(['bot_id', 'chat_id']);
            $table->index('module_id');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tg_module_enablements');

        Schema::create('tg_bot_modules', function (Blueprint $table) {
            $table->id();
            $table->string('bot_id', 20);
            $table->bigInteger('chat_id');
            $table->bigInteger('message_thread_id')->nullable();
            $table->unique(['bot_id', 'chat_id', 'message_thread_id']);
            $table->foreign('bot_id')->references('bot_id')->on('tg_bots')->cascadeOnDelete();
            $table->timestampsTz();
        });
    }
};
