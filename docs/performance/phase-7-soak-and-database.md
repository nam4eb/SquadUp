# Phase 7 - Octane soak test and database profile

## Scope

Validate Octane under sustained traffic, measure worker resource behavior, and
separate PostgreSQL time from application and network latency.

## Load-test improvements

- Stage duration, target VUs, ramp-down duration, and iteration sleep are now
  configurable through environment variables.
- The load test can authenticate a comma-separated pool of accounts with
  `BENCHMARK_EMAILS` and assigns tokens across virtual users.
- Teardown logs out every generated token and benchmark summaries continue to
  remove setup data before writing JSON.
- Added `measure-docker-soak.ps1` to sample CPU, memory, PID count, and container
  restarts during a run.
- Production Compose now passes the opt-in
  `PERFORMANCE_PROFILING_ENABLED` setting to application processes.

## Rate-limit diagnostic

The first sustained run intentionally exposed an issue in the old load model:
all VUs shared one account while the production limiter allowed 120 requests per
minute. It produced 364 successful responses followed by 77,664 HTTP 429
responses. There were no server or network errors.

This proves the limiter works, but it is not an Octane capacity result. The raw
run is retained as `phase-7-rate-limit-diagnostic.json`. The valid isolated
capacity run used a benchmark-only limit of 100,000 requests per minute. The
production default remains 120.

## Valid sustained run

Topology and workload:

- One Octane/RoadRunner API container with eight workers.
- One Nginx gateway, PostgreSQL, Redis, queue worker, and Reverb process.
- 20-second ramp to 30 VUs, 140 seconds at 30 VUs, then 10-second ramp down.
- Four application requests per iteration: activities, stories, conversations,
  and presence heartbeat.

Results:

| Metric | Result |
| --- | ---: |
| Application requests | 50,456 |
| Completed iterations | 12,614 |
| API requests/second | 295.37 |
| Average latency | 41.97 ms |
| Overall p95 | 105.30 ms |
| Activities p95 | 118.73 ms |
| Stories p95 | 96.80 ms |
| Conversations p95 | 112.41 ms |
| Presence p95 | 87.30 ms |
| HTTP/server/network/rate-limit errors | 0 |
| Interrupted iterations | 0 |

All configured latency and error thresholds passed.

## Worker stability

The sampler collected 28 resource snapshots:

- Memory increased from 296.4 MiB during warm-up to 568.7 MiB at the final
  sample.
- Average memory was 6.78% of the host limit in the first half and 7.08% in the
  second half; peak usage was 7.31%.
- Peak CPU was 604.41%, consistent with several RoadRunner workers executing in
  parallel.
- PID count peaked at 34.
- Container restarts, failed queue jobs, and queue backlog were all zero.
- No application exception, fatal error, or worker crash appeared in logs.

The memory curve is close to a plateau after worker warm-up, but a three-minute
run cannot rule out a slow leak. Production canary monitoring or a multi-hour
test is still required.

## Database profile

Fifty sequential samples were collected per endpoint after the sustained run.
Database query logging was enabled only for this profiling pass.

| Endpoint | Queries | DB avg | DB p95 | App avg | App p95 |
| --- | ---: | ---: | ---: | ---: | ---: |
| Activities | 7 | 5.84 ms | 6.85 ms | 11.21 ms | 12.59 ms |
| Stories | 1 | 0.80 ms | 1.01 ms | 1.41 ms | 1.83 ms |
| Conversations | 7 | 4.51 ms | 5.28 ms | 8.80 ms | 10.32 ms |
| Presence | 0 | 0 ms | 0 ms | 0.75 ms | 1.07 ms |

On the current seed dataset, PostgreSQL is not the dominant source of the
remaining p95 under concurrent load. Activities and conversations remain the
best candidates for query consolidation because each still performs seven
queries, but their database p95 is below 7 ms.

## Multi-user smoke test

The new token-pool mode authenticated four demo accounts and completed 83
iterations with no errors. All four temporary access tokens were removed by
teardown.

## Decision

The short soak gate passes and supports moving Octane to a low-percentage
canary. Do not disable or globally raise the production limiter; production-like
load tests should use a sufficiently large account pool instead.

Before increasing canary traffic, run the same resource sampler for several
hours and alert on memory slope, worker restarts, p95/p99, 5xx rate, failed jobs,
and PostgreSQL connection saturation.

Raw artifacts:

- `phase-7-soak-summary.json`
- `phase-7-octane-resources.json`
- `phase-7-query-profile.json`
- `phase-7-rate-limit-diagnostic.json`
- `phase-7-rate-limit-resources.json`
- `phase-7-multi-user-smoke.json`
