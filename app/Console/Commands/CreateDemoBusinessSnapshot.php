<?php

namespace App\Console\Commands;

use App\Services\DemoBusinessSnapshotService;
use Illuminate\Console\Command;
use Throwable;

class CreateDemoBusinessSnapshot extends Command
{
    protected $signature = 'demo:snapshot {business_id=81 : Allowed demo business id} {--force : Replace an existing snapshot}';
    protected $description = 'Create the immutable baseline snapshot for one demo business';

    public function handle(DemoBusinessSnapshotService $service): int
    {
        try {
            $snapshot = $service->create((int) $this->argument('business_id'), (bool) $this->option('force'));
            $this->info("Snapshot created for {$snapshot['business_name']} (business {$snapshot['business_id']}).");
            $this->table(['Table', 'Rows'], collect($snapshot['counts'])->map(fn ($count, $table) => [$table, $count])->values()->all());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
