# Development and deployment

Development uses local PHP/Composer plus Docker PostgreSQL, Redis and Reverb. The
Laravel application reads all connection details from `.env`; only
`.env.example` is committed.

Production requires PHP 8.3+, PostgreSQL backups with tested restoration,
managed Redis, TLS termination, queue workers, Reverb workers, scheduled jobs,
object storage, centralized logs and health checks. Application migrations run
as a controlled release step. Mobile secrets are limited to public client
identifiers; server credentials never ship in Flutter assets.

Use `/up` as the process liveness probe and `/api/v1/ready` as the readiness
probe. Readiness verifies both database and cache access and returns HTTP 503
without exposing connection details when a dependency is unavailable. API logs
include an `X-Request-ID` correlation value and request duration; ingress and
downstream services should preserve this header.

Chat media uses the Laravel filesystem disk selected by `MEDIA_DISK`. Responses
expose short-lived signed application URLs, and downloads re-check conversation
membership. Production should use private object storage and set
`MEDIA_URL_TTL_MINUTES` to the shortest usable client window. Malware scanning,
quarantine and lifecycle rules remain required at the storage/worker boundary.

The API process never runs migrations during container startup. Run
`php artisan migrate --force` as one controlled release step before replacing
or scaling API containers. The development server uses `--no-reload` to avoid
reloader process growth in the bind-mounted Docker environment.
