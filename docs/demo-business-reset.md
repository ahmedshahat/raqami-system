# Demo business daily reset

This installation is configured for two independent demo businesses:

- Trade and distribution: `business.id = 81`.
- Manufacturing: `business.id = 92`.

Each business has its own baseline snapshot. Both resets are scoped by an
explicit allow-list and are scheduled for 01:00 in `Africa/Cairo`.

## Safety model

- `DEMO_RESET_ENABLED` is off by default.
- Only ids in `DEMO_RESET_BUSINESS_IDS` are accepted by either command.
- The legacy `pos:dummyBusiness` full-database reset is disabled unless the
  separate `LEGACY_FULL_DEMO_RESET_ENABLED` flag is deliberately enabled.
- The snapshot is checksummed and bound to its database name, business id and
  schema. A mismatch aborts before deletion.
- A real restore requires `--force`; `--dry-run` never writes.
- Requests for the business currently being restored receive HTTP 503 while
  its restore is in progress. The other demo business remains available.
- The restore runs in one database transaction, verifies row counts before
  commit, and invalidates the demo users' sessions and OAuth tokens.

## First deployment

Take a full server backup first. Then add both ids while keeping restoration
disabled. First set these values in the server `.env`:

```dotenv
DEMO_RESET_ENABLED=false
DEMO_RESET_BUSINESS_IDS=81,92
```

Then clear the cached configuration and capture business 92 exactly as it
currently exists on the server:

```bash
php artisan config:clear
php artisan demo:snapshot 92
php artisan demo:restore 92 --dry-run
php artisan demo:restore 81 --dry-run
```

Do not use `--force` for business 92 unless intentionally replacing a reviewed
baseline that already exists. Review both dry-run table/count outputs. Then set:

```dotenv
DEMO_RESET_ENABLED=true
DEMO_RESET_BUSINESS_IDS=81,92
DEMO_RESET_TIME=01:00
DEMO_RESET_TIMEZONE=Africa/Cairo
LEGACY_FULL_DEMO_RESET_ENABLED=false
```

Refresh cached configuration:

```bash
php artisan config:cache
php artisan schedule:list
```

The server must invoke Laravel's scheduler every minute:

```cron
* * * * * cd /absolute/path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

The generated baselines are private and must remain at:

```text
storage/app/demo-snapshots/business-81.snapshot.gz
storage/app/demo-snapshots/business-92.snapshot.gz
```

Do not put these files in the public web directory. Re-run `demo:snapshot ID
--force` only when that business's approved baseline itself should be replaced.

## Manual recovery check

Use the non-writing command at any time:

```bash
php artisan demo:restore 81 --dry-run
php artisan demo:restore 92 --dry-run
```

Do not run a real manual restore until its output has been reviewed. A manual
restore is:

```bash
php artisan demo:restore 81 --force
php artisan demo:restore 92 --force
```
