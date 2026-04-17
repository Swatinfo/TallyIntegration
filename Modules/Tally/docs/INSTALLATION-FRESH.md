# Installation — Fresh Laravel 13 Project

Zero-to-running-module in a brand-new Laravel 13 project.

> Already have a Laravel app? Use [INSTALLATION-EXISTING.md](INSTALLATION-EXISTING.md) instead.

---

## 1. Prerequisites (local machine)

| Tool | Minimum version |
|---|---|
| PHP | 8.4 |
| Composer | 2.7 |
| Node + npm | 20 LTS |
| MariaDB / MySQL / PostgreSQL | any recent |
| TallyPrime | reachable on its HTTP port (see [TALLY-SETUP.md](TALLY-SETUP.md)) |

---

## 2. Create the Laravel project

```bash
composer create-project laravel/laravel:^13.0 my-tally-app
cd my-tally-app
php artisan serve   # sanity check http://127.0.0.1:8000
```

---

## 3. Install required packages

```bash
composer require nwidart/laravel-modules:^13.0
composer require laravel/sanctum
php artisan install:api          # publishes Sanctum + api routes
```

If `install:api` asks whether to add the `HasApiTokens` trait to `User`, say **yes**.

---

## 4. Copy the Tally module into the project

From the `TallyIntegration` repo, copy the entire `Modules/Tally/` directory into `my-tally-app/Modules/Tally/`.

Cross-platform commands (run from the repo root):

```bash
# macOS / Linux
cp -R /path/to/TallyIntegration/Modules/Tally /path/to/my-tally-app/Modules/Tally

# Windows PowerShell
Copy-Item "F:\G Drive\Projects\TallyIntegration\Modules\Tally" "C:\path\to\my-tally-app\Modules\Tally" -Recurse
```

You should now have `my-tally-app/Modules/Tally/module.json`.

---

## 5. Wire up PSR-4 autoload

Open `my-tally-app/composer.json` and add to `autoload.psr-4`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/",
        "Modules\\": "Modules/",
        "Modules\\Tally\\": "Modules/Tally/app/",
        "Modules\\Tally\\Database\\Factories\\": "Modules/Tally/database/factories/",
        "Modules\\Tally\\Database\\Seeders\\": "Modules/Tally/database/seeders/"
    }
}
```

Then:

```bash
composer dump-autoload
```

---

## 6. Register and enable the module

`nwidart/laravel-modules` auto-discovers `Modules/Tally/module.json`. Just enable it:

```bash
php artisan module:list
php artisan module:enable Tally
```

If `module:list` is empty, run `php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"` and re-run.

---

## 7. Add the `tally_permissions` column to `users`

The Tally module ships a migration that adds a `tally_permissions` JSON column to the host app's `users` table. If your fresh project already ran Laravel's default `users` migration, this just works. Otherwise, run migrations first:

```bash
php artisan migrate
```

You should see all 10 Tally migrations run in addition to the default Laravel ones.

---

## 8. Configure `.env`

Add to `my-tally-app/.env`:

```env
TALLY_HOST=localhost
TALLY_PORT=9000
TALLY_COMPANY=
TALLY_TIMEOUT=30
TALLY_LOG_REQUESTS=true
TALLY_CACHE_ENABLED=true
TALLY_CACHE_TTL=300
TALLY_CIRCUIT_BREAKER=true

QUEUE_CONNECTION=database      # or redis / sqs in production
```

Full reference: [CONFIGURATION.md](CONFIGURATION.md).

---

## 9. Create queue + schedule infrastructure

```bash
# Database queue table (skip if using redis/sqs)
php artisan queue:table
php artisan migrate

# Run one queue worker for local dev
php artisan queue:work

# Run the scheduler (production uses cron — see below)
php artisan schedule:work
```

For production, add a single cron entry:

```
* * * * * cd /path/to/my-tally-app && php artisan schedule:run >> /dev/null 2>&1
```

---

## 10. Create your first user + Sanctum token

```bash
php artisan tinker
```

```php
use App\Models\User;
use Modules\Tally\Enums\TallyPermission;

$user = User::factory()->create([
    'email' => 'admin@example.com',
    'password' => bcrypt('secret123'),
]);

$user->tally_permissions = [
    TallyPermission::ViewMasters->value,
    TallyPermission::ManageMasters->value,
    TallyPermission::ViewVouchers->value,
    TallyPermission::ManageVouchers->value,
    TallyPermission::ViewReports->value,
    TallyPermission::ManageConnections->value,
];
$user->save();

echo $user->createToken('dev')->plainTextToken;
```

Save the printed token — every API request needs `Authorization: Bearer {token}`.

---

## 11. Smoke-test the install

```bash
# Terminal 1
php artisan serve

# Terminal 2 — must match your token
TOKEN="your-token-here"

# The default health check (uses TALLY_HOST/TALLY_PORT from .env)
curl http://127.0.0.1:8000/api/tally/health -H "Authorization: Bearer $TOKEN"
```

Expected response:
```json
{
  "success": true,
  "data": { "connected": true, "url": "http://localhost:9000", "companies": ["ABC Enterprises"] },
  "message": "Tally is reachable"
}
```

If `connected` is `false`, see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

---

## 12. Next steps

- Register your first connection: [QUICK-START.md](QUICK-START.md)
- Full API reference: [API-USAGE.md](API-USAGE.md)
- Configure queue priorities, logging channel: [CONFIGURATION.md](CONFIGURATION.md)

---

## Summary of files installed

```
my-tally-app/
├── Modules/Tally/          # the entire module
├── database/migrations/    # default Laravel migrations
├── .env                    # + TALLY_* keys
└── composer.json           # + PSR-4 autoload entries
```

No main-app PHP code is touched — the module is fully self-contained.
