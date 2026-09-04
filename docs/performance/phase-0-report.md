# Phase 0 - Laravel performance baseline

Date: 2026-09-03

## Scope

The same seeded account and database were used to measure four mobile API paths sequentially:

- `GET /api/v1/activities`
- `GET /api/v1/stories`
- `GET /api/v1/conversations`
- `POST /api/v1/presence/heartbeat`

The development runtime uses `artisan serve`, `APP_DEBUG=true`, uncached configuration/routes, and a Windows bind mount. The optimized runtime uses the same PHP 8.3 image and application code copied to a Linux named volume, `APP_ENV=production`, `APP_DEBUG=false`, Laravel `artisan optimize`, and CLI OPcache.

## Results

| Endpoint | Dev p50 | Optimized p50 | p50 improvement | Dev p95 | Optimized p95 | p95 improvement |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| activities | 2896.86 ms | 42.41 ms | 68.3x | 3140.14 ms | 51.34 ms | 61.2x |
| stories | 2545.69 ms | 24.58 ms | 103.6x | 2606.98 ms | 36.10 ms | 72.2x |
| conversations | 2774.00 ms | 38.27 ms | 72.5x | 5436.68 ms | 41.48 ms | 131.1x |
| presence | 2675.28 ms | 20.80 ms | 128.6x | 3086.20 ms | 21.75 ms | 141.9x |

All measured requests returned HTTP 200. The development sample contains 5 measured requests per endpoint; the optimized sample contains 20 per endpoint after 3 warm-up requests.

Raw results:

- `baseline-artisan-serve.json`
- `optimized-linux-volume.json`

## Finding

The dominant Phase 0 bottleneck is the development deployment topology, especially Laravel/PHP filesystem access through the Windows bind mount combined with uncached bootstrap metadata. The evidence does not support rewriting the backend in Go at this stage: the existing Laravel endpoints reach 21-42 ms p50 and 22-51 ms p95 once run from a Linux filesystem with production caches and OPcache.

## Recommended next action

Promote the production-like container topology (immutable application image or Linux volume, production caches, OPcache) into the normal non-development deployment path. Then run concurrent load tests and database query profiling. Consider Octane only if sustained concurrency misses the agreed SLO; consider a Go extraction only for a measured hotspot that remains after query/index/cache work.

## Reproduction

```powershell
docker compose --profile benchmark up -d --force-recreate benchmark-init api-benchmark
./scripts/benchmark-api.ps1 -BaseUrl http://127.0.0.1:8001/api/v1 -Password '<seed-password>' -Warmup 3 -Requests 20
```
