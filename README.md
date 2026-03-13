## Email S3 Migration – Laravel Project

This project demonstrates a scalable email migration pipeline from a PostgreSQL database to S3-compatible storage (MinIO), orchestrated via RabbitMQ and Laravel.

It includes:

- Doctrine ORM (no Eloquent)
- RabbitMQ-based publisher/worker migration flow
- MinIO as S3 replacement
- Dockerized infrastructure
- **Web UI** for **viewing and searching emails** and monitoring migration progress (see [Web UI](#6-web-ui--view--search-emails) below).

### Web UI at a glance

| What | URL | Port |
|------|-----|------|
| **Email search & migration dashboard** (view and search emails) | **http://localhost:8080** | **8080** |
| RabbitMQ management | http://localhost:15672 | 15672 |
| MinIO console | http://localhost:9001 | 9001 |
| Reverb (WebSocket) | ws://localhost:6001 | 6001 |
| MinIO API (attachment downloads) | http://localhost:9000 | 9000 |

The Laravel app is served by **Nginx**; the web server listens on **port 8080** (host) and proxies to PHP-FPM inside Docker. Open **http://localhost:8080** in your browser to use the email search page and live migration stats.

### Ports to open (firewall / security group)

For the app to work when accessed from another machine (or from the internet), open these **TCP** ports on the host or in your cloud/VM security group:

| Port | Service | Required for |
|------|---------|--------------|
| **8080** | Nginx (web UI) | Email search & migration dashboard in the browser |
| **6001** | Reverb (WebSocket) | Live stats updates on the dashboard without reload |
| **9000** | MinIO API | Download links for migrated attachments (presigned URLs) |

Optional (admin UIs, not needed for end users):

| Port | Service |
|------|---------|
| 9001 | MinIO console |
| 15672 | RabbitMQ management |

Example (Linux with ufw):

```bash
ufw allow 8080/tcp
ufw allow 6001/tcp
ufw allow 9000/tcp
ufw reload
```

---

## Quick setup (clean machine)

On a fresh clone, from the project root:

1. **Start the stack and run migrations**

   ```bash
   ./run.sh
   ```

   This builds and starts the Docker stack, runs DB schema setup, and starts the migration worker, stats broadcaster, and migration publisher.

2. **Seed the database with test emails (optional)**

   ```bash
   docker compose exec php-fpm php artisan emails:seed --records=100000
   ```

   Then open **http://localhost:8080** to view and search emails and monitor migration progress.

---

## 1. Docker setup

### Prerequisites

- Docker & Docker Compose installed
- Bash (for running `scripts/create_network.sh`) – Git Bash / WSL is fine on Windows

### Steps

1. **Create the shared Docker network (once)**

   From the project root:

   ```bash
   bash scripts/create_network.sh
   ```

   This creates a bridge network:

   - Name: `email2s3_net`
   - Subnet: `172.25.0.0/24`

2. **Start the containers**

   From the project root:

   ```bash
   docker compose up -d --build
   ```

   This brings up:

   - `nginx` (app entrypoint, exposed on `http://localhost:8080`)
   - `php-fpm` (Laravel app container)
   - `postgres`
   - `minio` (S3-compatible storage)
   - `rabbitmq` (AMQP + management UI)

3. **Useful URLs**

   - **Email search & dashboard (web UI):** **http://localhost:8080** (port 8080) – view and search emails, see migration stats.
   - MinIO console: `http://localhost:9001`
   - RabbitMQ management UI: `http://localhost:15672`

4. **Viewing Laravel logs**

   To follow the application log file (`storage/logs/laravel.log`) inside the PHP container, use the **container name** `email2s3_php` (works from any directory):

   ```bash
   docker exec email2s3_php tail -f storage/logs/laravel.log
   ```

   Alternatively, from the **project root** (where `docker-compose.yml` is):  
   `docker compose exec php-fpm tail -f storage/logs/laravel.log`  
   (`docker compose logs php-fpm` only shows container stdout/stderr, not the Laravel log file.)

5. **Access from outside the host**

   Nginx and Reverb are bound to **0.0.0.0** (all IPv4 interfaces) on ports **8080** and **6001**, so the web UI can be reached from other machines at `http://YOUR_SERVER_IP:8080`.

   **Required for external access:** In `.env` on the server, set the **public** URL and host (not localhost), so redirects and the WebSocket work from a browser on another machine:

   - `APP_URL=http://YOUR_SERVER_IP:8080`
   - `REVERB_HOST=YOUR_SERVER_IP`

   **Easier:** run with `PUBLIC_URL` so one command sets both and starts the stack:

   ```bash
   PUBLIC_URL=http://YOUR_SERVER_IP:8080 ./run.sh
   ```

   If you keep `APP_URL=http://localhost:8080` and `REVERB_HOST=localhost`, the app may redirect or try to connect the browser to the visitor’s own machine instead of your server.

   If you still cannot connect from outside:

   - **Host firewall:** allow TCP **8080**, **6001**, and **9000** (see [Ports to open](#ports-to-open-firewall--security-group) above). Example: `ufw allow 8080/tcp && ufw allow 6001/tcp && ufw allow 9000/tcp && ufw reload`.
   - **Cloud / VM:** open ports **8080**, **6001**, and **9000** in the instance’s security group or network ACL.

6. **Debugging HTTP 500**

   If you see a generic "HTTP ERROR 500" with no details:

   - Ensure **APP_KEY** is set: run `docker compose exec php-fpm php artisan key:generate` and restart.
   - **Permission denied on storage/logs/laravel.log:** run  
     `docker compose exec php-fpm bash -lc "touch storage/logs/laravel.log && chown -R www-data:www-data storage bootstrap/cache 2>/dev/null; chmod -R 777 storage bootstrap/cache"`  
     then reload the page.
   - Set **APP_DEBUG=true** in `.env` (temporarily), then reload the page – Laravel will show the exception and stack trace.
   - Or, with APP_DEBUG=true, open **http://YOUR_SERVER_IP:8080/log-tail** to see the last 100 lines of `storage/logs/laravel.log` (the real error is usually at the bottom).
   - Ensure `storage/` and `storage/framework/sessions/` are writable by the PHP process in the container.

---

## 1.1. One-command startup with `run.sh`

For a full local setup (build containers, install dependencies, run migrations, optionally seed, and start all long-running processes), you can use the helper script.

From the project root:

```bash
chmod +x run.sh   # first time only
./run.sh
```

What this script does:

- Creates the Docker network (via `scripts/create_network.sh`).
- Builds and starts all containers (`docker compose up -d --build`).
- Installs **all PHP dependencies** inside the `php-fpm` container (`composer install`).
- Runs database migrations.
- Does **not** run seeding; run it manually so the WebSocket dashboard can show progress while seeding and during migration:
  - `docker compose exec php-fpm php artisan emails:seed --records=100000`
  - Open the dashboard; stats (total, remaining, migrated) update in real time.
- Starts:
  - Reverb WebSocket server (`php artisan reverb:start --port=6001`)
  - Migration stats broadcaster (`php artisan migration:stats-broadcaster`)
  - Migration workers (`php artisan migration:worker`)
  - Migration publisher in loop mode (`php artisan emails:migrate-to-s3 --loop`)

You can run `run.sh` from a regular user or from `root` (e.g. in some Docker/WSL setups); the script takes care of installing dependencies and wiring everything inside the containers.

---

## 2. Environment variables

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Key variables:

- **Database (PostgreSQL)**
  - `DB_CONNECTION=pgsql`
  - `DB_HOST=postgres`
  - `DB_PORT=5432`
  - `DB_DATABASE=email2s3`
  - `DB_USERNAME=email2s3`
  - `DB_PASSWORD=secret`

- **MinIO (S3 replacement)**
  - `MINIO_ROOT_USER=minioadmin`
  - `MINIO_ROOT_PASSWORD=minioadminpassword`
  - `MINIO_ENDPOINT=http://minio:9000`
  - `MINIO_BUCKET=email2s3`

- **RabbitMQ** (use a non-guest user so publisher/workers can connect from Docker; `run.sh` sets these if you had `guest`/`guest`)
  - `RABBITMQ_HOST=rabbitmq`
  - `RABBITMQ_PORT=5672`
  - `RABBITMQ_USER=email2s3`
  - `RABBITMQ_PASSWORD=secret`
  - `RABBITMQ_VHOST=/`

- **Laravel Reverb (WebSocket dashboard)**
  - `BROADCAST_DRIVER=reverb`
  - `REVERB_APP_ID=email2s3-app`
  - `REVERB_APP_KEY=email2s3-key`
  - `REVERB_APP_SECRET=email2s3-secret`
  - `REVERB_HOST=localhost` (host the browser uses to connect; for external access use the server’s public IP or hostname)
  - `REVERB_PORT=6001`
  - `REVERB_SCHEME=http`

You can adjust these if you change ports or credentials in `docker-compose.yml`.

---

## 3. Installing dependencies & DB setup

All app commands should be run **inside the PHP container**:

```bash
docker-compose exec php-fpm bash
```

### Install Composer dependencies

Inside the container:

```bash
composer install
```

### Run migrations

```bash
php artisan migrate
```

This creates:

- `emails`
- `files`
- `migration_offsets` (if you add it) and other tables as defined.

### Database seeding

The `DatabaseSeeder` can generate a large dataset of emails with body + attachments.

Example (100,000 emails):

```bash
php artisan emails:seed --records=100000
```

**Fill the database without using the `SEED_RECORDS` env var** — run the Artisan command directly with `--records`:

```bash
# With Docker
docker compose exec php-fpm php artisan emails:seed --records=100000

# Or locally (from project root)
php artisan emails:seed --records=100000
```

You can use any number for `--records`; the default is 100,000 if omitted.

**Fill the database without a `.env` file** — pass DB config (and `APP_KEY`) as env vars when running the command. The schema must already exist (e.g. after `php artisan db:ensure-schema` run with the same config):

```bash
docker compose exec -e APP_KEY=base64:your-key-here \
  -e DB_CONNECTION=pgsql \
  -e DB_HOST=postgres \
  -e DB_PORT=5432 \
  -e DB_DATABASE=email2s3 \
  -e DB_USERNAME=email2s3 \
  -e DB_PASSWORD=secret \
  php-fpm php artisan emails:seed --records=10000
```

Replace `APP_KEY` and DB values with your real settings.

Seeder responsibilities:

- Generate HTML email bodies (≥ 10KB each)
- Generate 1–3 attachment files per email, with random sizes (10KB–1MB)
- Store files physically under `storage/app/email_attachments`
- Insert into `files` table and populate `emails.file_ids` JSON with associated file IDs

> Note: Seeding can take some time and disk space given the volume and file sizes.

---

## 4. Running the migration

The migration pipeline is split into:

- **Publisher** – reads IDs from `emails` and publishes them to RabbitMQ
- **Workers** – consume IDs, migrate body and attachments to MinIO, and update DB state

### 4.1. Start publisher

Start a one-shot batch:

```bash
php artisan emails:migrate-to-s3
```

Or run continuously until all emails are queued:

```bash
php artisan emails:migrate-to-s3 --loop
```

You only need **one** publisher process (with or without `--loop`). To speed up migration, add **more workers** (see [4.2. Start workers](#42-start-workers)).

Publisher behavior:

- Queries `emails` by ID in batches (default batch size 1000; configurable via `MIGRATION_PUBLISH_BATCH_SIZE`).
- Only publishes rows where `is_migrated_s3 = 0`.
- Publishes each email ID individually to RabbitMQ queue `email_migration_queue`.
- Tracks `last_published_id` in `migration_offsets` so it can resume after restarts.

### 4.2. Start workers

Workers consume from `email_migration_queue` and call `EmailMigrationService`.

```bash
php artisan migration:worker
```

**Start more workers (scale out):** run multiple worker processes. Each process runs one consumer. From the **project root** (the directory where `docker-compose.yml` is, e.g. where you ran `./run.sh`), run (e.g. 3 workers in the background; `&` returns the shell):

```bash
docker compose exec php-fpm bash -lc "php artisan migration:worker" &
docker compose exec php-fpm bash -lc "php artisan migration:worker" &
docker compose exec php-fpm bash -lc "php artisan migration:worker" &
```

Or in separate terminals for foreground:

```bash
docker compose exec php-fpm php artisan migration:worker
# In another terminal:
docker compose exec php-fpm php artisan migration:worker
# etc.
```

Options:

- `--workers=` (currently logged only; horizontal scaling is done by running multiple `migration:worker` processes as above).

Worker behavior:

1. Reads a message, decodes `{"email_id": <id>}`.
2. Calls `EmailMigrationService::migrate($emailId)` which:
   - Sets `is_migrated_s3 = 1` (migrating).
   - Uploads email body and attachments to MinIO (`/{email_id}/{filename.ext}`).
   - Updates `body_s3_path` and `file_s3_paths` in `emails`.
   - Deletes local attachment files on success.
   - Sets `is_migrated_s3 = 2` (migrated).
3. On error:
   - Logs structured error.
   - Resets `is_migrated_s3 = 0`.
   - Leaves local files intact.
   - NACKs and requeues the message for retry.

---

## 5. WebSocket stats broadcaster & Laravel Reverb

The migration dashboard receives live statistics via **Laravel Reverb** (Laravel’s first-party WebSocket server). The frontend uses **Laravel Echo** with the Reverb broadcaster.

### 5.1. Reverb WebSocket server

Start the Reverb server inside the PHP container:

```bash
php artisan reverb:start --port=6001
```

Options:

- `--port=` – override the default port (6001).
- `--host=` – bind address (default `0.0.0.0`).

Behavior:

- Listens for WebSocket connections on the given port (Pusher protocol).
- Delivers events broadcast by your Laravel app to connected clients.

### 5.2. Migration stats broadcaster

Run the stats broadcaster so it pushes a `MigrationStatsUpdated` event every second to Reverb:

```bash
php artisan migration:stats-broadcaster
```

Options:

- `--interval=` – seconds between broadcasts (default 1).

Behavior:

- Every interval:
  - Uses `MigrationStatsService` to aggregate counts from `emails` (pending, migrating, migrated, total).
  - Dispatches `App\Events\MigrationStatsUpdated` (broadcast on channel `migration-stats`).
  - Logs and prints the stats to the console.

Reverb then sends the event to all clients subscribed to the `migration-stats` channel.

### 5.3. Frontend (Laravel Echo)

The email search page uses Laravel Echo (loaded from CDN) with the Reverb broadcaster. It subscribes to the public channel `migration-stats` and listens for `.MigrationStatsUpdated`. Ensure `REVERB_APP_KEY`, `REVERB_HOST`, and `REVERB_PORT` are set so the browser can connect (e.g. `REVERB_HOST=localhost`, `REVERB_PORT=6001` when using Docker with port 6001 exposed).

---

## 6. Web UI – view & search emails

The **web server** is **Nginx**; it serves the Laravel app on **port 8080** (mapped from the host). Use it to **view and search emails** and to see the migration dashboard.

**URL:** **http://localhost:8080** (port **8080**)

Features:

- **Email search page**:
  - Filters:
    - `sender_email`
    - `receiver_email`
    - Created-at date range (`date_from` / `date_to`)
  - Paginated results with columns:
    - ID, sender, receiver, subject, created_at, migration status.

- **Migration dashboard widget**:
  - Shows:
    - Migrated
    - Migrating
    - Remaining (pending)
  - Initialized from database counts, then updated live via Laravel Reverb every ~1 second.

---

## 7. Tests

Run the test suite inside the PHP container:

```bash
phpunit
```

Included:

- **Unit tests**:
  - `S3StorageService` – key generation and S3 client calls.
  - `EmailMigrationService` – per-email migration behavior and file cleanup.
  - `MigrationStatsUpdated` – broadcast event payload and channel.
  - Basic repository-style usage test.
- **Feature test**:
  - Full migration flow with mocked MinIO and RabbitMQ, ensuring that:
    - An email + file in the DB is migrated.
    - S3 paths are set.
    - `is_migrated_s3` is updated.

