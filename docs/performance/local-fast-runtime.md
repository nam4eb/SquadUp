# Local fast API runtime

Docker Desktop on Windows makes Laravel's per-request bootstrap slow when the
application and its dependencies are read from a bind mount. The regular API
service remains useful for live PHP editing, while this opt-in service packages
the current source into a Linux image and serves it with Octane/RoadRunner.

Build and start it after backend changes:

```powershell
docker build -f backend/Dockerfile.production -t squadup-api-production:latest backend
docker build --build-arg BASE_IMAGE=squadup-api-production:latest -f backend/Dockerfile.octane -t squadup-api-octane:latest backend
docker compose -f docker-compose.yml -f docker-compose.fast.yml up -d api-fast
```

Run Flutter against the fast endpoint:

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8001/api/v1
```

The repository also includes a shortcut for Android emulators:

```powershell
.\scripts\run-flutter-fast.ps1
```

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
