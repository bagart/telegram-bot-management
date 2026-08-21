<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

use BAGArt\TelegramBot\Modules\TgModuleRegistry;
use BAGArt\TelegramBotManagement\Models\TgModuleEnablement;
use Illuminate\Console\Command;

/**
 * Diagnoses module configuration:
 * - R-9: every enablement row must reference a discovered module (typo in
 *   module_id, or a module removed while its enablements remained);
 * - Q7 static checks: declared dependencies discovered with a satisfied
 *   semver constraint ("module_id" or "module_id@>=1.2.0"), and no pair of
 *   mutually conflicting modules discovered at the same time.
 */
class TgModulesDoctorCommand extends Command
{
    protected $signature = 'tg:modules:doctor';

    protected $description = 'Check module enablements and static descriptor consistency';

    public function handle(TgModuleRegistry $registry): int
    {
        $problems = [];

        $orphans = TgModuleEnablement::query()
            ->whereNotIn('module_id', $registry->moduleIds())
            ->orderBy('module_id')
            ->get(['module_id', 'bot_id', 'chat_id', 'is_enabled']);

        if ($orphans->isNotEmpty()) {
            $this->table(
                ['module_id', 'level', 'bot_id', 'chat_id', 'is_enabled'],
                $orphans->map(fn (TgModuleEnablement $row): array => [
                    $row->module_id,
                    $row->bot_id === null ? 'platform' : ($row->chat_id === null ? 'bot' : 'chat'),
                    $row->bot_id ?? '-',
                    $row->chat_id ?? '-',
                    $row->is_enabled ? 'yes' : 'no',
                ])->all(),
            );
            $problems[] = 'Enablement rows reference undiscovered modules (typo in module_id, or module removed).';
        }

        $problems = [
            ...$problems,
            ...$this->checkRequirements($registry),
            ...$this->checkConflicts($registry),
        ];

        if ($problems === []) {
            $this->info('Module configuration is healthy: enablements, requirements and conflicts are consistent.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function checkRequirements(TgModuleRegistry $registry): array
    {
        $problems = [];

        foreach ($registry->all() as $descriptor) {
            foreach ($descriptor->requiresModules as $requirement) {
                [$requiredId, $constraint] = $this->parseRequirement($requirement);
                $required = $registry->get($requiredId);

                if ($required === null) {
                    $problems[] = "Module '{$descriptor->id}' requires '{$requiredId}' which is not discovered.";

                    continue;
                }

                if ($constraint !== null
                    && !self::versionSatisfies($required->version, $constraint)
                ) {
                    $problems[] = sprintf(
                        "Module '%s' requires '%s@%s' but version %s is discovered.",
                        $descriptor->id,
                        $requiredId,
                        $constraint,
                        $required->version,
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function checkConflicts(TgModuleRegistry $registry): array
    {
        $problems = [];

        foreach ($registry->all() as $descriptor) {
            foreach ($descriptor->conflictsWith as $conflictId) {
                if ($registry->has($conflictId)) {
                    $problems[] = "Module '{$descriptor->id}' conflicts with '{$conflictId}' which is also discovered.";
                }
            }
        }

        return $problems;
    }

    /**
     * @return array{0: string, 1: string|null} [moduleId, constraint]
     */
    private function parseRequirement(string $requirement): array
    {
        $parts = explode('@', $requirement, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    /**
     * Minimal semver check for constraints of the "op version" form
     * (>=1.2.0, >1.0, =2.0.0, <=1.9). No range logic — modules declare
     * a single constraint.
     */
    private static function versionSatisfies(string $version, string $constraint): bool
    {
        if (!preg_match('/^(>=|<=|>|<|=)\s*(.+)$/', trim($constraint), $m)) {
            return false;
        }

        [$op, $required] = [$m[1], trim($m[2])];

        return match ($op) {
            '>=' => version_compare($version, $required, '>='),
            '<=' => version_compare($version, $required, '<='),
            '>' => version_compare($version, $required, '>'),
            '<' => version_compare($version, $required, '<'),
            '=' => version_compare($version, $required, '=='),
            default => false,
        };
    }
}
