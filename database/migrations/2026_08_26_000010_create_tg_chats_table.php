<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform chat registry (RFC §9.1): passive, event-fed inventory of chats
 * known to each bot. Schema is verbatim from the RFC — do not drift.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tg_chats', function (Blueprint $table): void {
            $table->string('bot_id', 20);
            $table->bigInteger('chat_id');
            $table->string('type', 16);
            $table->string('title', 255)->nullable();
            $table->string('username', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('deactivate_reason', 24)->nullable();
            $table->bigInteger('member_count')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->foreign('bot_id')
                ->references('bot_id')->on('tg_bots')
                ->cascadeOnDelete();

            $table->primary(['bot_id', 'chat_id']);
            $table->index(['bot_id', 'is_active']);
            $table->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tg_chats');
    }
};
