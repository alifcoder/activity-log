# 📘 Alif Activity Log

A simple, customizable Laravel package to log and store user activity across your application. Perfect for auditing, tracking changes, and monitoring actions within modules.

---

## ✨ Features

- Logs authenticated user actions and request details
- Stores activity in a dedicated `activity_logs` table
- Supports parent-child log relationships
- Customizable logging behavior
- Easily extendable and minimalistic

---

## 📦 Requirements

- **PHP** `>=8.2`
- **Laravel** `^11.0 || ^12.0 || ^13.0`

---

## 🚀 Installation

```bash
composer require alifcoder/activity-log
```

---

## ⚙️ Configuration & Migration

Publish the configuration and migration with:

```bash
php artisan vendor:publish --tag=activity-log
```

This will publish:
- `config/activity-log.php`
- `database/migrations/xxxx_xx_xx_xxxxxx_create_activity_logs_table.php`

Then run the migration:

```bash
php artisan migrate
```

---

## 🧑‍💻 Usage

### Logging a manual activity

```php


ActivityLogger::log(new ActivityLogCreateDTO(
                log_type: 'custom',         // optional: log type
                user_id: 1,                 // optional: user_id
                url: 'http://example.com',  // optional: URL
                method: 'GET',              // optional: HTTP method
                request_body: '{}',         // optional: request body
                response_body: '{}',        // optional: response body
                ...                         // other parameters
        ));
```

## 🔀 Logging Requests (Route Middleware)

Request logging is opt-in per route or route group via the `log.activity` alias:

```php
Route::middleware(['api', 'log.activity'])->group(function () {
    Route::post('/example', ExampleController::class);
});
```

The package does **not** register the middleware globally. Each request is logged once, by the routes that use the alias.

---

## 🔐 Encrypted Request & Response Storage

All logged request and response bodies are stored as **encrypted files** for maximum data protection.

To read an encrypted stored file:

```php
use Alif\ActivityLog\Facades\FileStorage;

$content = FileStorage::readEncrypted($log->request_body);
```

**Format.** New files use AES-256-GCM (authenticated) with a `v2:` prefix. Files written by older versions (AES-256-CBC, no prefix) are still read, so no data migration is needed. A tampered or wrongly keyed file raises an error, and `readEncrypted()` returns `null`.

**Names.** Files are named after the `additional_id` (`request_<sha1>.log`, and so on). A retried job overwrites its own files, so it does not leave orphans behind.
---
## 🔑 Generating an Encryption Secret Key

Since request/response data is encrypted, the package requires a secret key.

Generate it using:

```bash
php artisan activity-log:generate-key
```

The key is written to `.env`. If a key already exists, the command refuses to change it. Use `--force` only when you intentionally discard the old key: logs encrypted with it can no longer be read.

---
## 🌍 IP Address Details Sync

The package supports collecting additional information about user IP addresses, such as:

- Country
- City
- Coordinates
- ISP
- ASN
- and more

After installation, write scheduler code to sync IP details:

```php
$schedule->command('activity-log:sync-ip-details')->hourly();
```

- Lookups run in parallel batches of 10.
- IPs from `ip_ignore.exact` (e.g. `127.0.0.1`, `::1`) are filtered in SQL, so they are never selected.
- Private and reserved addresses (`10.x`, `192.168.x`, …) are marked with `{"success": false}` without any request, so they never leave the server.
- Each unique public IP is requested once. Bad IPs get the same marker, so they are not requested again.
- Failed requests (timeouts, connection errors) keep `ip_details` empty and are retried on the next run.
---

## 🧹 Pruning Old Logs

Delete logs older than `log_keep_days`, together with their encrypted files:

```php
$schedule->command('activity-log:prune')->daily();
```

The command works in chunks (`--chunk=1000`): one DB delete and one file delete per chunk. Newer logs that pointed at a deleted parent are detached (`parent_id` set to `null`), so no dangling links remain. Date folders (`activity-log/YYYY-MM-DD/`) older than the retention window are removed as well, with one day of margin. It is much faster than `model:prune` on large tables (for example, 3000 rows: ~0.35 s with ~14 queries, vs ~1 s with ~6000 queries for `model:prune`).

---

## 🛡️ Failure Behavior

- Logging never breaks the request. Errors (missing key, queue or DB failure) are reported through Laravel's exception handler and the request continues.
- If the encryption key changed, stored files can no longer be decrypted. `FileStorage::readEncrypted()` reports the error and returns `null` instead of an empty string.

---

## 🧩 One Row per Request

Each `additional_id` maps to exactly one row. Writes are a single atomic `INSERT ... ON CONFLICT`, so the middleware row and the model rows of one request merge safely, even when several queue workers write at the same time. Manual logs without an `additional_id` get their own id and their own row.

The unique index on `additional_id` is part of the `activity_logs` migration, so fresh installs have it. Tables created by an older version need it added by hand, and only after their duplicates are removed:

```sql
-- 1. find the duplicates
SELECT additional_id, COUNT(*) FROM activity_logs
WHERE additional_id IS NOT NULL GROUP BY additional_id HAVING COUNT(*) > 1;

-- 2. after merging or deleting them
ALTER TABLE activity_logs ADD UNIQUE INDEX activity_logs_additional_id_unique (additional_id);
```

## ⚡ Model Change Logging

Models using `ActivityLogTrait` are logged on create, delete, and on update **only when an attribute actually changed**. The first model saved in a request is linked to that request's log row. Each model change is linked to the previous change of the same model through `parent_id`.

## 🧱 Table Structure: `activity_logs`

| Column               | Type      | Description                                              |
|----------------------|-----------|----------------------------------------------------------|
| `id`                 | UUID      | Primary key                                              |
| `parent_id`          | UUID      | Link to the previous log of the same model (optional)    |
| `additional_id`      | String    | Request ID, groups all logs of one request/HTTP call     |
| `log_type`           | String    | `middleware`, `http`, or custom type                     |
| `user_id`            | String    | Authenticated user ID                                    |
| `module`             | String    | App module, taken from the controller namespace          |
| `route`              | String    | Controller action name                                   |
| `url`                | Text      | Full URL accessed                                        |
| `model_id`           | String    | Related model ID (optional)                              |
| `model_type`         | String    | Related model class (optional)                           |
| `user_agent`         | String    | Browser/user-agent string                                |
| `app_type`           | String    | `App-Type` request header                                |
| `app_version`        | String    | `App-Version` request header                             |
| `ip`                 | String    | Client IP address                                        |
| `method`             | String    | HTTP method                                              |
| `status_code`        | String    | HTTP status code                                         |
| `request_body`       | String    | Path to the encrypted request file (not the body itself) |
| `response_body`      | String    | Path to the encrypted response file                      |
| `curl`               | String    | Path to the encrypted curl file                          |
| `description`        | Text      | Log description                                          |
| `request_duration`   | Decimal   | Request duration in milliseconds                         |
| `peak_memory_usage`  | BigInt    | Peak memory in bytes                                     |
| `ip_details`         | JSON      | IP geo/ISP data filled by `activity-log:sync-ip-details` |
| `created_at`         | DateTime  | When the log was created                                 |
| `updated_at`         | DateTime  | When the log was last updated                            |

Indexes: every column except the encrypted file paths (`request_body`, `response_body`, `curl`) is indexed, because the ERP backend filters by the others. `additional_id` is UNIQUE, `created_at` has its own index, and `(model_id, created_at)` is composite.

---

## ⚙️ Environment Variables

| Variable                          | Default              | Description                              |
|-----------------------------------|----------------------|------------------------------------------|
| `ACTIVITY_LOG_ENABLED`            | `true`               | Turn logging on/off                      |
| `ACTIVITY_LOG_DB_CONNECTION`      | `DB_CONNECTION`      | Connection for `activity_logs`           |
| `ACTIVITY_LOG_USE_QUEUE`          | `true`               | Write logs through a queued job          |
| `ACTIVITY_LOG_QUEUE_NAME`         | `default`            | Queue name for log jobs                  |
| `ACTIVITY_LOG_FILE_STORAGE_DISK`  | `local`              | Filesystem disk for encrypted files      |
| `ACTIVITY_LOG_ENCRYPT_KEY`        | —                    | Encryption key (`activity-log:generate-key`) |
| `ACTIVITY_LOG_KEEP_DAYS`          | `90`                 | Days to keep logs before pruning         |

---

## 🧹 Remove Package

```bash
composer remove alifcoder/activity-log
```

---

## 🤝 Contributing

Pull requests are welcome! For major changes, please open an issue first.

---

## 🪪 License

MIT License © [Shukhratjon Yuldashev](https://t.me/alif_coder)


---

## 📡 Logging Incoming Requests and Outgoing HTTP Calls

### ✅ 1. Incoming requests

Add the `log.activity` alias to the routes you want logged (see [Logging Requests](#-logging-requests-route-middleware)). Logged requests include route, URL, method, user info, status code (including failed requests: `HttpException` status or `500`), and request/response bodies.

To turn logging off everywhere, set `ACTIVITY_LOG_ENABLED=false`.

### ✅ 2. Log Outgoing HTTP Requests

The package extends Laravel’s `Http` client with a `loggable()` macro.

Example usage:

```php
use Illuminate\Support\Facades\Http;

$response = Http::loggable()->get('https://example.com/api/data');
```

This logs outgoing HTTP calls made by your application.

---

## 🧩 Customization

You can customize what gets logged, ignored routes/methods, and the database connection via:

```
config/activity-log.php
```

---

## 📫 Support

If you need help, feel free to contact [Shukhratjon Yuldashev on Telegram](https://t.me/alif_coder).

---
## 🧪 Development

```bash
composer format       # Laravel Pint: apply code style
composer format:test  # Laravel Pint: check only
composer phpstan      # PHPStan level 5 over src/
```

Git hooks are not versioned. After cloning, install the pre-commit hook once:

```bash
printf '#!/bin/sh\nset -eu\nexec "$(git rev-parse --show-toplevel)/scripts/pre-commit"\n' > .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

On commit it formats the staged PHP files with Pint and then runs PHPStan. Any error stops the commit.
