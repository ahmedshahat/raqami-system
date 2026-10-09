<?php

namespace App\Console\Commands;

use App\Services\DemoBusinessSnapshotService;
use Illuminate\Console\Command;
use Throwable;

class RestoreDemoBusiness extends Command
{
    protected $signature = 'demo:restore {business_id=81 : Allowed demo business id}
                            {--dry-run : Show the reset plan without changing data}
                            {--force : Required confirmation for a real restore}';
    protected $description = 'Restore one allow-listed demo business from its baseline snapshot';

    public function handle(DemoBusinessSnapshotService $service): int
    {
        $businessId = (int) $this->argument('business_id');

        try {
            if ($this->option('dry-run')) {
                $plan = $service->plan($businessId);
                $this->info("Dry run for {$plan['business_name']} (business {$businessId}). No data was changed.");
                $tables = array_unique(array_merge(array_keys($plan['current_counts']), array_keys($plan['snapshot_counts'])));
                sort($tables);
                $this->table(['Table', 'Current', 'Snapshot'], array_map(fn ($table) => [
                    $table, $plan['current_counts'][$table] ?? 0, $plan['snapshot_counts'][$table] ?? 0,
                ], $tables));

                return self::SUCCESS;
            }

            if (! $this->option('force')) {
                $this->error('A real restore requires --force. Run with --dry-run first.');
                return self::FAILURE;
            }
            if (! config('demo_reset.enabled')) {
                $this->error('Demo reset is disabled. Set DEMO_RESET_ENABLED=true only on the demo installation.');
                return self::FAILURE;
            }

            $result = $service->restore($businessId);
            $this->info("Restored {$result['business_name']} (business {$businessId}) successfully.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
