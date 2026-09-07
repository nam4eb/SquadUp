# Phase 3 - Concurrent load testing

The k6 scenario exercises the four main mobile reads/writes in every iteration:
activities, stories, conversations, and presence heartbeat.

## Load shape

- Ramp to 10 virtual users for 20 seconds.
- Ramp to 50 virtual users for 20 seconds.
- Ramp to 100 virtual users for 20 seconds.
- Ramp down for 10 seconds.

## Service-level thresholds

- Error rate below 1%.
- Overall p95 below 200 ms.
- Overall p99 below 400 ms.
- Per-endpoint p95 below 200 ms.

## Run

Start a production PHP-FPM stack under an isolated project name, seed its
disposable database, and raise only that stack's API limiter for the capacity
test:

```powershell
$env:API_PORT='8002'
$env:API_RATE_LIMIT_PER_MINUTE='100000'
docker compose -p squadup-load --env-file .env.production -f docker-compose.production.yml up -d --scale api=2 --scale queue=2 gateway api queue reverb
docker compose -p squadup-load --env-file .env.production -f docker-compose.production.yml exec api php artisan db:seed --force
$env:BENCHMARK_PASSWORD='<seed-password>'
$env:LOAD_TEST_BASE_URL='http://host.docker.internal:8002/api/v1'
docker compose --profile loadtest run --rm load-test
```

The machine-readable result is written to `phase-3-load-summary.json`.

## Result (2026-09-05)

The official run used the production image, PostgreSQL, Redis, one API
container with 40 dynamic PHP-FPM workers, one queue worker, and one Reverb
process. The API rate limit was raised only in the disposable load-test stack.

| Metric | Result |
| --- | ---: |
| Completed scenario iterations | 2,559 |
| Completed API requests | 10,236 |
| API throughput | 145.18 requests/second |
| API errors / HTTP 5xx / HTTP 429 / network errors | 0 / 0 / 0 / 0 |
| Overall average / p95 / max | 217.13 / 529.39 / 1,071.44 ms |
| Activities average / p95 | 272.90 / 604.16 ms |
| Stories average / p95 | 186.18 / 453.12 ms |
| Conversations average / p95 | 246.33 / 558.50 ms |
| Presence average / p95 | 163.10 / 406.71 ms |
| Queue backlog / failed jobs after the run | 0 / 0 |

The reliability threshold passed: all 10,236 application requests succeeded.
The 200 ms p95 latency target did not pass under the 100-VU ramp, so latency
optimization remains follow-up work. This result is still a substantial change
from the invalid first run, where synchronous broadcasting caused one HTTP 500
per iteration and p95 latency reached 9-18 seconds.

Realtime events now implement queued `ShouldBroadcast`; REST requests no longer
depend on Reverb being reachable during the request. The load script also logs
out its temporary token and strips setup data from the persisted JSON summary.
