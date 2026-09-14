# Local fast API runtime

Docker Desktop on Windows makes Laravel's per-request bootstrap slow when the
application and its dependencies are read from a bind mount. The regular API
service remains useful for live PHP editing, while this opt-in service packages
the current source into a Linux image and serves it with Octane/RoadRunner.

Build and start it after backend changes:

```powershell
docker build -f backend/Dockerfile.production -t squadup-api-production:latest backend
docker build --build-arg BASE_IMAGE=squadup-api-production:latest -f backend/Dockerfile.octane -t squadup-api-octane:latest backend
docker compose -f docker-compose.yml -f docker-compose.fast.yml up -d --wait api-fast scheduler
docker compose -f docker-compose.yml -f docker-compose.fast.yml exec api-fast php artisan migrate --force
```

Run Flutter against the fast endpoint:

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8001/api/v1
```

The repository also includes a shortcut for Android emulators:

```powershell
.\scripts\run-flutter-fast.ps1
```

For the local web target with Reverb forwarded on 8081:

```powershell
.\scripts\run-flutter-fast.ps1 -DeviceId web-server -ReverbPort 8081
```

The fast service reads `backend/.env` for the same application key as the
bind-mounted services. Its `storage/app` is shared with the host backend;
uploaded files survive recreating the fast container. Only storage is mounted,
so application/vendor bootstrap remains inside the Linux image.

To serve a built web release in Docker:

```powershell
flutter build web --no-web-resources-cdn --dart-define=API_BASE_URL=http://127.0.0.1:8001/api/v1 --dart-define=BROADCAST_AUTH_URL=http://127.0.0.1:8001/api/broadcasting/auth --dart-define=REVERB_PORT=8081
docker compose -f docker-compose.yml -f docker-compose.fast.yml --profile web up -d web
```

Open `http://127.0.0.1:3000`. Rebuild web after Dart changes. The normal Compose
stack now includes a scheduler for activity reminders and operational pruning.

## Authenticated measurements on 2026-09-14

Five sequential samples per endpoint after one warm-up, same local seeded
PostgreSQL database. These are median latencies, not capacity or p95 results.

| Endpoint | Bind mount :8000 | Packaged Octane :8001 |
| --- | ---: | ---: |
| auth/me | 1955.88 ms | 14.91 ms |
| explore | 2041.14 ms | 47.13 ms |
| conversations | 2122.87 ms | 24.39 ms |

Raw samples: `local-authenticated-2026-09-14.json`. Reproduce with
`scripts/measure-local-api.ps1` and `BENCHMARK_PASSWORD` in the environment.
The script revokes its temporary token after measuring. A PostgreSQL UUID
aggregation failure found during the first pass was fixed before this run.

Use `127.0.0.1` instead of `10.0.2.2` for a desktop target. Rebuild/recreate
`api-fast` after PHP source changes. Database and Redis are shared with the
regular local stack.

Measured on 2026-09-13 after the rating/story migrations:

- Windows bind-mounted `artisan serve`: approximately 5.5 seconds per warm
  health request.
- Octane worker warm-up: approximately 0.6 seconds for the first request per
  worker.
- Warm Octane requests: approximately 4-18 ms for an authenticated-route 401
  response, compared with multiple seconds on the bind-mounted runtime.
