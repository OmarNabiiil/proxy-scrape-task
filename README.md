# Laravel backend — scraping + products API

Two jobs: it scrapes product pages on a schedule and serves the stored products to the Next.js
catalog. There is **no public scrape endpoint** — scraping only happens through the scheduler and
the queue.

Redis is required (queue + scrape throttle). Horizon is not used.

## 1. Requirements

- PHP 8.3+ and Composer
- MySQL 8+ with a database named `laravel`
- Redis 7+
- The Go proxy service running (see `../microservices/README.md`) — Laravel asks it for a proxy
  and User-Agent before every scrape

If you have no local MySQL/Redis, start them from this folder:

```bash
docker compose up -d
```

- MySQL: `127.0.0.1:3306` (database `laravel`, user `root`, empty password)
- Redis: `127.0.0.1:6379`

## 2. Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Then set these in `.env`:

| Variable | Meaning |
| --- | --- |
| `DB_CONNECTION=mysql` | products are stored in MySQL |
| `QUEUE_CONNECTION=redis` | scrape jobs and the throttle use Redis |
| `GO_PROXY_BASE_URL` | Go proxy service, e.g. `http://127.0.0.1:8081` |
| `PRODUCTS_API_KEY` | shared secret for `GET /api/products`; must match the frontend `NEXT_PUBLIC_API_KEY` |
| `FRONTEND_URL` | browser origin allowed by CORS, e.g. `http://localhost:3000` |
| `CORS_ALLOWED_ORIGINS` | extra allowed origins, comma separated |
| `SCRAPE_TLS_VERIFY` | local-only escape hatch for a broken CA bundle; always on in production |
| `SCRAPE_CONNECT_TIMEOUT` | seconds to wait for the proxy connection (default 5); keep it low so dead proxies are abandoned fast |
| `SCRAPE_TIMEOUT` | seconds to wait for the whole page (default 20) |
| `SCRAPE_PROXY_MAX_RETRIES` | how many proxy hops one scrape may try after a 429, 403, or missing title (default 5) |
| `SCRAPE_PROXY_EMPTY_RELEASE_SECONDS` | how long to delay the job when Go has no healthy proxy (default 60) |

Optional demo rows so the catalog is not empty before the first scrape:

```bash
php artisan db:seed --class=ProductSeeder
```

## 3. Run it

From the repo root, one script starts Laravel, the worker, the scheduler, the Go proxy, and Next.js:

```bash
./dev.sh
./dev.sh --seed    # also load the demo product rows
```

Ctrl+C stops every process the script started. To run the pieces yourself:

```bash
php artisan serve              # API on http://localhost:8000
php artisan queue:work         # runs the scrape jobs
php artisan schedule:work      # fires scrape:scheduled every minute
cd ../microservices && go run ./cmd/api   # proxy service on :8081
```

In production, replace `schedule:work` with one cron entry:

```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Trigger a scrape round by hand:

```bash
php artisan scrape:scheduled
```

It queues one `ScrapeProductData` job per URL in `config/scraping.php` (`scraping.urls`). Add or
change target URLs there. Only Amazon, Jumia, and Noon hosts are supported; any other host throws.

With a free proxy list, most attempts fail to connect, so give the worker several tries — each
retry takes the next proxy from the pool:

```bash
php artisan queue:work --tries=200 --backoff=0
```

Each job: get proxy + User-Agent from Go → fetch the page → parse title, price, image → save.
Saving matches on `title`, so an existing product is updated in place and its `created_at` never
changes. The whole app is limited to 20 scrapes per 60 seconds; jobs over the limit go back on the
queue after 30 seconds.

If the shop returns **429**, **403**, or a page with **no product title**, Laravel tells Go to sit
that proxy out (`POST /internal/proxy/bench`) and immediately retries the same shop URL on a
different proxy, up to `SCRAPE_PROXY_MAX_RETRIES` hops. Connect timeouts and other HTTP errors do
**not** bench. If Go has no healthy proxy (HTTP 503), the job waits
`SCRAPE_PROXY_EMPTY_RELEASE_SECONDS` and is released back to the queue. It never fetches the shop
without a proxy. Do not log raw proxy URLs (they may contain passwords). Horizon is not used.

## 4. Products API

```
GET /api/products
GET /api/products?search=galaxy
```

Requires the `X-API-Key` header with your `PRODUCTS_API_KEY`; anything else returns 401. The
response is `{ "data": [ { id, title, price, image_url, created_at } ] }`, newest first.
`search` filters on title. A list request never starts a scrape.

```bash
curl -sS -H "X-API-Key: $PRODUCTS_API_KEY" http://localhost:8000/api/products
```

Browser requests are allowed only from `FRONTEND_URL` / `CORS_ALLOWED_ORIGINS`, methods
`GET`, `HEAD`, `OPTIONS`.

## 5. Logs

Component lines (`[ProductController]`, `[ScrapeProductData]`, and so on) go to
`storage/logs/laravel.log` unless `LOG_PATH` is set. `./dev.sh` writes Laravel, the worker, and
the scheduler into `logs/laravel.log` at the repo root.

## 6. Tests

```bash
php artisan test
```

Runs on SQLite in memory, so no MySQL or Redis needed. The live scrape checks are skipped by
default because they use the public internet:

```bash
LIVE_SCRAPE_TESTS=1 php artisan test --filter=LiveScrapeTest
```

They fetch the real Amazon, Jumia, and Noon URLs from `config/scraping.php` and fail if a shop
changed its markup. Latest results:
`../features-docs/features/ecommerce-scraping/test-results.md`.
