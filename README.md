
# Laravel Activity Log

A lightweight and extensible Laravel package for logging application activity. It captures and stores HTTP requests, responses, and model changes, with support for middleware logging, Guzzle macros, and customizable database connections.

---

## 📦 Installation

Add this package to your Laravel project via Composer:

```bash
composer require alifcoder/activity-log
```

> If your package is not on Packagist, add a repository to your `composer.json`:
>
> ```json
> "repositories": [
>   {
>     "type": "vcs",
>     "url": "https://github.com/alifcoder/activity-log"
>   }
> ],
> ```

---

## 🛠 Configuration

1. **Publish the config file (optional):**

```bash
php artisan vendor:publish --tag=activity-log-config
```

2. **Configure the connection (optional):**

In `config/activity-log.php`:

```php
return [
    'connection' => env('ACTIVITY_LOG_DB_CONNECTION', env('DB_CONNECTION')),
];
```

> You can define a separate database for logging activities.

---

## 🧬 Migrations

To create the activity logs table, run:

```bash
php artisan migrate --path=vendor/alifcoder/activity-log/database/migrations
```

> ⚠️ Make sure your logging database has a `migrations` table or create one manually if needed.

---

## 🌐 Middleware Logging

The package provides a global middleware to log:

- All `POST` and `PUT` requests
- Payloads and responses
- Auto-generated request tracking ID

Register the middleware globally in your `App\Http\Kernel.php`:

```php
protected $middleware = [
    \ShukhratjonYuldashev\ActivityLog\Http\Middleware\LogActivityMiddleware::class,
];
```

---

## 🧱 Model Events Tracking

This package logs `created` and `updated` events on models. In your model:

```php
use ShukhratjonYuldashev\ActivityLog\Traits\LogsActivity;

class Post extends Model
{
    use LogsActivity;

    // Optional: Customize logged attributes or model name
}
```

The middleware passes a unique request ID, so the model events can be linked to the original HTTP request.

---

## 🌍 HTTP Client Logging (Guzzle)

To log outgoing requests (using Laravel's `Http` facade), register the macro:

```php
Http::macro('loggable', function () {
    return Http::withMiddleware(function ($handler) {
        return function ($request, array $options) use ($handler) {
            \Log::info('Outgoing Request cURL: ' . \ShukhratjonYuldashev\ActivityLog\Helpers\CurlGenerator::generateCurl($request));
            return $handler($request, $options);
        };
    });
});
```

Then use it like this:

```php
$response = Http::loggable()->post('https://example.com/api', [
    'name' => 'Test'
]);
```

---

## ⚙️ Async Logging with Jobs

Activity entries are queued using Laravel’s `dispatch()` system. Make sure you have a queue worker running:

```bash
php artisan queue:work
```

---

## 📁 Directory Structure

```
src/
├── ActivityLogServiceProvider.php
├── Http/
│   └── Middleware/LogActivityMiddleware.php
├── Jobs/StoreActivityLog.php
├── Models/ActivityLog.php
├── Traits/LogsActivity.php
├── Helpers/CurlGenerator.php
config/
└── activity-log.php
database/
└── migrations/xxxx_xx_xx_create_activity_logs_table.php
```

---

## 🏷 Versioning

Tag your releases in Git:

```bash
git tag v1.0.0
git push origin v1.0.0
```

---

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

## 🧑‍💻 Author

**Shukhratjon Yuldashev**  
GitHub: [@alifcoder](https://github.com/alifcoder)
