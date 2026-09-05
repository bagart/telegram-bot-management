<?php

declare(strict_types=1);

use BAGArt\TelegramBotManagement\Models\TgModuleEnablement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-shot dedupe for the NULL-distinct legacy unique index
 * (bot_id, chat_id, module_id) (menu RFC §8.6 storage realities, §19.2):
 * concurrent first-writes could create several rows for one logical scope.
 * Collapses each duplicate group into its newest row, merging older settings
 * underneath; must run BEFORE the guarded writer goes live so the writer's
 * collapse path only ever sees fresh damage, not history.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = TgModuleEnablement::query()
                ->orderBy('updated_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'bot_id', 'chat_id', 'module_id', 'module_settings']);

            $byLogicalKey = $rows->groupBy(
                fn (TgModuleEnablement $r): string => implode("\x1f", [
                    $r->bot_id ?? '',
                    (string)($r->chat_id ?? ''),
                    $r->module_id,
                ]),
            );

            foreach ($byLogicalKey as $group) {
                if ($group->count() < 2) {
                    continue;
                }

                /** @var TgModuleEnablement $keeper */
                $keeper = $group->last();
                $merged = [];

                foreach ($group->take($group->count() - 1) as $old) {
                    /** @var TgModuleEnablement $old */
                    $merged = array_merge($merged, is_array($old->module_settings) ? $old->module_settings : []);
                    $old->delete();
                }

                // saveQuietly: a data repair must not masquerade as a write
                // (no updated_at touch, no invalidation/epoch side effects).
                $keeper->module_settings = array_merge($merged, is_array($keeper->module_settings) ? $keeper->module_settings : []);
                $keeper->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Collapsed duplicates cannot be restored — deliberate no-op.
    }
};
