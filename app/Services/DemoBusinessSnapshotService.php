<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DemoBusinessSnapshotService
{
    private const SNAPSHOT_VERSION = 1;

    private array $columns = [];
    private array $primaryKeys = [];
    private array $foreignKeys = [];

    public function create(int $businessId, bool $overwrite = false): array
    {
        $this->assertAllowed($businessId);
        $this->loadMetadata();
        $this->assertBusinessExists($businessId);

        $path = $this->snapshotPath($businessId);
        if (File::exists($path) && ! $overwrite) {
            throw new RuntimeException("Snapshot already exists for business {$businessId}; use --force to replace it.");
        }

        $captured = DB::transaction(function () use ($businessId) {
            return [
                'name' => (string) DB::table('business')->where('id', $businessId)->value('name'),
                'tables' => $this->discoverScope($businessId),
            ];
        });
        $tables = $captured['tables'];
        $snapshot = [
            'version' => self::SNAPSHOT_VERSION,
            'business_id' => $businessId,
            'business_name' => $captured['name'],
            'database' => DB::getDatabaseName(),
            'created_at' => now()->toIso8601String(),
            'schema' => $this->schemaSignature(array_keys($tables)),
            'counts' => $this->counts($tables),
            'tables' => $tables,
        ];

        $payload = serialize($snapshot);
        $envelope = serialize(['checksum' => hash('sha256', $payload), 'payload' => $payload]);
        $compressed = gzencode($envelope, 9);
        if ($compressed === false) {
            throw new RuntimeException('Could not compress the demo snapshot.');
        }

        File::ensureDirectoryExists(dirname($path));
        File::replace($path, $compressed);
        $this->removeSplitSnapshot($path);

        Log::info('Demo business snapshot created.', [
            'business_id' => $businessId, 'path' => $path, 'counts' => $snapshot['counts'],
        ]);

        return $snapshot;
    }

    public function plan(int $businessId): array
    {
        $this->assertAllowed($businessId);
        $this->loadMetadata();
        $snapshot = $this->readSnapshot($businessId);
        $this->assertCompatibleSnapshot($snapshot, $businessId);
        $current = $this->discoverScope($businessId);

        return [
            'business_id' => $businessId,
            'business_name' => $snapshot['business_name'],
            'snapshot_created_at' => $snapshot['created_at'],
            'current_counts' => $this->counts($current),
            'snapshot_counts' => $snapshot['counts'],
        ];
    }

    public function restore(int $businessId): array
    {
        $this->assertAllowed($businessId);
        $this->loadMetadata();
        $snapshot = $this->readSnapshot($businessId);
        $this->assertCompatibleSnapshot($snapshot, $businessId);

        $lockName = 'demo-business-reset:'.$businessId;
        $lock = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new RuntimeException("Another reset is already running for business {$businessId}.");
        }

        $marker = $this->maintenanceKey($businessId);
        try {
            Cache::put($marker, true, now()->addMinutes(15));
            DB::beginTransaction();
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $current = $this->discoverScope($businessId);
                $currentUserIds = $this->columnValues($current['users'] ?? [], 'id');
                $snapshotUserIds = $this->columnValues($snapshot['tables']['users'] ?? [], 'id');
                $userEmails = array_values(array_unique(array_filter(array_merge(
                    $this->columnValues($current['users'] ?? [], 'email'),
                    $this->columnValues($snapshot['tables']['users'] ?? [], 'email')
                ))));

                $this->deleteTables($current);
                $this->insertTables($snapshot['tables']);
                $this->deleteTransientAuthentication(
                    array_values(array_unique(array_merge($currentUserIds, $snapshotUserIds))),
                    $userEmails
                );

                $restored = $this->discoverScope($businessId);
                $actualCounts = $this->counts($restored);
                if ($actualCounts !== $snapshot['counts']) {
                    throw new RuntimeException('Restore row-count verification failed; all changes were rolled back.');
                }
                DB::commit();
            } catch (Throwable $exception) {
                DB::rollBack();
                throw $exception;
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            Log::info('Demo business restored.', ['business_id' => $businessId, 'counts' => $actualCounts]);
            return ['business_id' => $businessId, 'business_name' => $snapshot['business_name'], 'counts' => $actualCounts];
        } finally {
            Cache::forget($marker);
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    public function assertAllowed(int $businessId): void
    {
        $allowed = array_map('intval', config('demo_reset.business_ids', []));
        if ($businessId < 1 || ! in_array($businessId, $allowed, true)) {
            throw new RuntimeException("Business {$businessId} is not in DEMO_RESET_BUSINESS_IDS.");
        }
    }

    public function maintenanceKey(int $businessId): string
    {
        return 'demo-reset:business:'.$businessId;
    }

    private function discoverScope(int $businessId): array
    {
        $excluded = array_flip(config('demo_reset.excluded_tables', []));
        $tables = [];

        foreach ($this->columns as $table => $columns) {
            if (isset($excluded[$table]) || ! in_array('business_id', $columns, true)) {
                continue;
            }
            $tables[$table] = $this->fetchRows($table, 'business_id', [$businessId]);
        }
        $tables['business'] = $this->fetchRows('business', 'id', [$businessId]);

        $relations = array_merge($this->foreignKeys, config('demo_reset.relations', []));
        for ($pass = 0; $pass < 25; $pass++) {
            $changed = false;
            foreach ($relations as $relation) {
                $parent = $relation['parent'];
                $child = $relation['child'];
                $parentColumn = $relation['parent_column'] ?? 'id';
                $childColumn = $relation['child_column'];

                if (isset($excluded[$child]) || empty($tables[$parent])) {
                    continue;
                }
                if (! isset($this->columns[$child]) || ! in_array($childColumn, $this->columns[$child], true)) {
                    continue;
                }

                $values = $this->columnValues($tables[$parent], $parentColumn);
                if (empty($values)) {
                    continue;
                }
                $rows = $this->fetchRows($child, $childColumn, $values, $relation['where'] ?? []);
                $before = count($tables[$child] ?? []);
                $tables[$child] = $this->mergeRows($child, $tables[$child] ?? [], $rows);
                $changed = $changed || count($tables[$child]) > $before;
            }
            if (! $changed) {
                break;
            }
        }

        $tables = array_filter($tables, fn (array $rows) => ! empty($rows));
        ksort($tables);
        return $tables;
    }

    private function loadMetadata(): void
    {
        if (! empty($this->columns)) {
            return;
        }

        $schema = DB::getDatabaseName();
        $columns = DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$schema]
        );
        foreach ($columns as $column) {
            $this->columns[$column->TABLE_NAME][] = $column->COLUMN_NAME;
        }

        $keys = DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY TABLE_NAME, ORDINAL_POSITION",
            [$schema]
        );
        foreach ($keys as $key) {
            $this->primaryKeys[$key->TABLE_NAME][] = $key->COLUMN_NAME;
        }

        $foreignKeys = DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$schema]
        );
        foreach ($foreignKeys as $key) {
            $this->foreignKeys[] = [
                'child' => $key->TABLE_NAME,
                'child_column' => $key->COLUMN_NAME,
                'parent' => $key->REFERENCED_TABLE_NAME,
                'parent_column' => $key->REFERENCED_COLUMN_NAME,
            ];
        }
    }

    private function fetchRows(string $table, string $column, array $values, array $where = []): array
    {
        $rows = [];
        $values = array_values(array_unique(array_filter($values, fn ($value) => $value !== null)));
        foreach (array_chunk($values, 500) as $chunk) {
            $query = DB::table($table)->whereIn($column, $chunk);
            foreach ($where as $whereColumn => $value) {
                $query->where($whereColumn, $value);
            }
            foreach ($query->get() as $row) {
                $rows[] = (array) $row;
            }
        }
        return $rows;
    }

    private function mergeRows(string $table, array $existing, array $additional): array
    {
        $merged = [];
        foreach (array_merge($existing, $additional) as $row) {
            $parts = [];
            foreach ($this->primaryKeys[$table] ?? [] as $key) {
                $parts[] = $row[$key] ?? null;
            }
            $identity = ! empty($parts) ? serialize($parts) : hash('sha256', serialize($row));
            $merged[$identity] = $row;
        }
        return array_values($merged);
    }

    private function deleteTables(array $tables): void
    {
        foreach (array_reverse(array_keys($tables)) as $table) {
            $rows = $tables[$table];
            $keys = $this->primaryKeys[$table] ?? [];
            if (count($keys) === 1) {
                foreach (array_chunk($this->columnValues($rows, $keys[0]), 500) as $ids) {
                    DB::table($table)->whereIn($keys[0], $ids)->delete();
                }
                continue;
            }

            foreach ($rows as $row) {
                $query = DB::table($table);
                $matchColumns = ! empty($keys) ? $keys : array_keys($row);
                foreach ($matchColumns as $column) {
                    is_null($row[$column]) ? $query->whereNull($column) : $query->where($column, $row[$column]);
                }
                $query->delete();
            }
        }
    }

    private function insertTables(array $tables): void
    {
        foreach ($tables as $table => $rows) {
            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }

    private function deleteTransientAuthentication(array $userIds, array $emails): void
    {
        if (empty($userIds)) {
            return;
        }
        $accessTokenIds = isset($this->columns['oauth_access_tokens'])
            ? DB::table('oauth_access_tokens')->whereIn('user_id', $userIds)->pluck('id')->all()
            : [];
        if (! empty($accessTokenIds) && isset($this->columns['oauth_refresh_tokens'])) {
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $accessTokenIds)->delete();
        }

        $clientIds = isset($this->columns['oauth_clients'])
            ? DB::table('oauth_clients')->whereIn('user_id', $userIds)->pluck('id')->all()
            : [];
        if (! empty($clientIds) && isset($this->columns['oauth_personal_access_clients'])) {
            DB::table('oauth_personal_access_clients')->whereIn('client_id', $clientIds)->delete();
        }

        foreach (['sessions', 'oauth_access_tokens', 'oauth_auth_codes', 'oauth_clients'] as $table) {
            if (isset($this->columns[$table]) && in_array('user_id', $this->columns[$table], true)) {
                DB::table($table)->whereIn('user_id', $userIds)->delete();
            }
        }

        if (isset($this->columns['notifications'])) {
            DB::table('notifications')
                ->where('notifiable_type', 'App\\User')
                ->whereIn('notifiable_id', $userIds)
                ->delete();
        }
        if (! empty($emails) && isset($this->columns['password_resets'])) {
            DB::table('password_resets')->whereIn('email', $emails)->delete();
        }
    }

    private function readSnapshot(int $businessId): array
    {
        $path = $this->snapshotPath($businessId);
        if (! File::exists($path) && ! File::exists($path.'.manifest.json')) {
            throw new RuntimeException("No snapshot exists for business {$businessId}. Run demo:snapshot first.");
        }

        $decoded = gzdecode($this->readSnapshotBytes($path));
        if ($decoded === false) {
            throw new RuntimeException('The demo snapshot is not a valid gzip file.');
        }
        $envelope = unserialize($decoded, ['allowed_classes' => false]);
        if (! is_array($envelope) || ! isset($envelope['payload'], $envelope['checksum'])) {
            throw new RuntimeException('The demo snapshot envelope is invalid.');
        }
        if (! hash_equals($envelope['checksum'], hash('sha256', $envelope['payload']))) {
            throw new RuntimeException('The demo snapshot checksum does not match.');
        }

        $snapshot = unserialize($envelope['payload'], ['allowed_classes' => false]);
        if (! is_array($snapshot)) {
            throw new RuntimeException('The demo snapshot payload is invalid.');
        }
        return $snapshot;
    }

    private function assertCompatibleSnapshot(array $snapshot, int $businessId): void
    {
        if (($snapshot['version'] ?? null) !== self::SNAPSHOT_VERSION || (int) ($snapshot['business_id'] ?? 0) !== $businessId) {
            throw new RuntimeException('Snapshot version or business id is invalid.');
        }
        if (($snapshot['database'] ?? null) !== DB::getDatabaseName()) {
            throw new RuntimeException('Snapshot belongs to a different database.');
        }
        if (($snapshot['schema'] ?? null) !== $this->schemaSignature(array_keys($snapshot['tables'] ?? []))) {
            throw new RuntimeException('Database schema changed after the snapshot. Create a new snapshot before restoring.');
        }
    }

    private function assertBusinessExists(int $businessId): void
    {
        if (! DB::table('business')->where('id', $businessId)->exists()) {
            throw new RuntimeException("Business {$businessId} does not exist.");
        }
    }

    private function schemaSignature(array $tables): string
    {
        sort($tables);
        $definition = [];
        foreach ($tables as $table) {
            $definition[$table] = $this->columns[$table] ?? [];
        }
        return hash('sha256', serialize($definition));
    }

    private function counts(array $tables): array
    {
        $counts = array_map('count', $tables);
        ksort($counts);
        return $counts;
    }

    private function columnValues(array $rows, string $column): array
    {
        return array_values(array_unique(array_filter(array_column($rows, $column), fn ($value) => $value !== null)));
    }

    private function snapshotPath(int $businessId): string
    {
        return rtrim(config('demo_reset.snapshot_directory'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR."business-{$businessId}.snapshot.gz";
    }

    private function readSnapshotBytes(string $path): string
    {
        $manifestPath = $path.'.manifest.json';
        if (! File::exists($manifestPath)) {
            return File::get($path);
        }

        $manifest = json_decode(File::get($manifestPath), true);
        $partCount = (int) ($manifest['parts'] ?? 0);
        $expectedSize = (int) ($manifest['size'] ?? 0);
        $expectedHash = (string) ($manifest['sha256'] ?? '');
        if ($partCount < 1 || $partCount > 100 || $expectedSize < 1 || strlen($expectedHash) !== 64) {
            throw new RuntimeException('The split demo snapshot manifest is invalid.');
        }

        $content = '';
        for ($part = 1; $part <= $partCount; $part++) {
            $partPath = $path.'.part'.str_pad((string) $part, 3, '0', STR_PAD_LEFT);
            if (! File::exists($partPath)) {
                throw new RuntimeException("Demo snapshot part {$part} of {$partCount} is missing.");
            }
            $content .= File::get($partPath);
        }

        if (strlen($content) !== $expectedSize || ! hash_equals($expectedHash, hash('sha256', $content))) {
            throw new RuntimeException('The split demo snapshot size or checksum does not match.');
        }

        return $content;
    }

    private function removeSplitSnapshot(string $path): void
    {
        $manifestPath = $path.'.manifest.json';
        if (File::exists($manifestPath)) {
            $manifest = json_decode(File::get($manifestPath), true);
            $partCount = min(max((int) ($manifest['parts'] ?? 0), 0), 100);
            for ($part = 1; $part <= $partCount; $part++) {
                File::delete($path.'.part'.str_pad((string) $part, 3, '0', STR_PAD_LEFT));
            }
            File::delete($manifestPath);
        }
    }
}
