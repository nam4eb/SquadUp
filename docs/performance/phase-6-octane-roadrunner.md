# Phase 6 - Laravel Octane with RoadRunner

## Goal

Measure whether keeping the Laravel application resident in memory improves the
mobile API enough to postpone a high-risk backend rewrite.

## Implementation

- Added Laravel Octane 2.19.1 with RoadRunner 2025.1.15.
- Added a dedicated `Dockerfile.octane` that derives from the production image.
- Added `docker-compose.octane.yml` as an opt-in production overlay. The normal
  PHP-FPM deployment remains unchanged and can be used for immediate rollback.
- Configured eight RoadRunner workers with a 500-request recycle limit.
- Added the PHP `intl` and `sockets` extensions to the production runtime and
  aligned the test image with the required `gd`, `intl`, and `sockets`
  extensions.
- Kept queue workers and Reverb as separate processes.

Example startup command:

```powershell
docker compose --env-file .env.production -f docker-compose.production.yml -f docker-compose.octane.yml up -d
```

## Verification

- Production and Octane images build successfully.
- The Octane API became healthy behind the existing Nginx gateway.
- The complete backend suite passes in the rebuilt test image: 132 tests and
  985 assertions.
- A two-user isolation smoke test alternated 20 authenticated `/auth/me`
  requests between two tokens; every response returned the correct user.
- Load-test cleanup left zero benchmark access tokens.
- PostgreSQL contained zero failed queue jobs and Redis contained zero queued
  jobs after the run.
- No application exception, fatal error, or worker crash was found in the API,
  queue, or Reverb logs.

## Benchmark

Both runs used the same single-host Docker environment and the same
10 -> 50 -> 100 VU ramp. Each iteration calls activities, stories,
conversations, and presence heartbeat.

| Metric | PHP-FPM baseline | Octane/RoadRunner | Change |
| --- | ---: | ---: | ---: |
| Application requests | 10,236 | 15,552 | +51.9% |
| API requests/second | 145.18 | 219.30 | +51.1% |
| Completed iterations | 2,559 | 3,888 | +51.9% |
| Average response time | 217.13 ms | 124.57 ms | -42.6% |
| Overall p95 | 529.39 ms | 321.33 ms | -39.3% |
| HTTP/server/network errors | 0 | 0 | unchanged |

Endpoint p95:

| Endpoint | PHP-FPM | Octane/RoadRunner | Change |
| --- | ---: | ---: | ---: |
| Activities | 604.16 ms | 336.49 ms | -44.3% |
| Stories | 453.12 ms | 306.69 ms | -32.3% |
| Conversations | 558.50 ms | 327.54 ms | -41.4% |
| Presence | 406.71 ms | 292.48 ms | -28.1% |

The strict 200 ms p95 target is still not met at the peak 100-VU load, so the
k6 command correctly exits with a threshold failure. This is a latency capacity
signal, not an availability failure: all 15,552 application requests completed
successfully.

The Octane API container used about 574 MiB after the run, compared with about
228 MiB observed for the FPM API container on the same host. This is expected
from resident workers and should be included in production capacity planning.

## Decision

Octane/RoadRunner provides a material improvement without changing the API
contract or rewriting business logic. Use the Octane overlay for a staged
canary, while retaining PHP-FPM as the rollback path. A rewrite to another
language is not justified by the current evidence.

Before broad production rollout:

1. Send a small percentage of traffic to one Octane instance.
2. Track p95/p99, worker memory growth, restarts, failed jobs, and authentication
   isolation for at least 24 hours.
3. Run a longer soak test to detect memory retained by application singletons or
   third-party packages.
4. Profile PostgreSQL during the 100-VU plateau; the remaining latency affects
   all four database-backed endpoints and is no longer dominated by Laravel
   bootstrap cost.

Machine-readable results are in `phase-6-octane-summary.json`.
