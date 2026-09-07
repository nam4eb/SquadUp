# Phase 1 - Production runtime

## Architecture

- Gateway: Nginx exposes the public API port and balances requests across API replicas.
- API: PHP 8.3 FPM + Nginx, immutable application image, internal port only.
- Queue and Reverb: the same immutable image and dependency set.
- PostgreSQL, Redis, and Laravel storage: persistent Docker volumes.
- Laravel migration uses an isolation lock; seeding is always disabled.
- Config, event, route, and view caches are generated at API container startup from the real runtime environment.
- OPcache is enabled with timestamp validation disabled.
- Queue and Reverb disable the web-server healthcheck inherited from the shared image; process restarts are handled by Docker.

No application source is bind-mounted in production.

## Configure

Copy `.env.production.example` to a secret-managed environment file and replace every placeholder. Generate the Laravel key without modifying a file:

```powershell
docker compose run --rm api php artisan key:generate --show
```

Redis authentication is enabled and `REDIS_PASSWORD` is passed to every application process.

## Validate configuration

```powershell
docker compose -p squadup-production --env-file .env.production -f docker-compose.production.yml config --quiet
```

## Build and start

```powershell
docker compose -p squadup-production --env-file .env.production -f docker-compose.production.yml build api
docker compose -p squadup-production --env-file .env.production -f docker-compose.production.yml up -d
```

To run multiple API and queue replicas on hosts with sufficient CPU, memory,
and database capacity:

```powershell
docker compose -p squadup-production --env-file .env.production -f docker-compose.production.yml up -d --scale api=2 --scale queue=2
```

Set the PHP-FPM worker limits per replica so the total worker budget remains
appropriate for the host. Do not multiply replicas and retain the full
per-container worker limit without capacity testing.

## Verify

```powershell
curl http://127.0.0.1:8000/up
curl http://127.0.0.1:8000/api/v1/ready
docker compose -p squadup-production --env-file .env.production -f docker-compose.production.yml exec api php artisan about
```

The API must report production mode, debug disabled, and all Laravel caches enabled before traffic is routed to it.
