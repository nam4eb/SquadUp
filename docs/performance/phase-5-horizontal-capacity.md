# Phase 5 - Horizontal API and queue capacity

## Implementation

- Added an Nginx gateway on the public API port with least-connections routing
  and upstream keep-alive connections.
- API containers now expose port 8080 only to the internal Docker network.
- The production stack supports `docker compose --scale api=N --scale queue=N`.
- Reverb and all replicas continue to share PostgreSQL, Redis, and the Laravel
  storage volume.
- The load-test summary filename is configurable, allowing phase results to be
  retained independently.

## Test topology

- One Nginx gateway.
- Two API replicas.
- Two queue workers.
- One Reverb process.
- One PostgreSQL and one Redis instance.
- Same 10 -> 50 -> 100 VU ramp used in Phase 3.
- API limiter raised only in the disposable capacity environment.

## Results

| Topology | Total PHP-FPM workers | API req/s | Overall p95 | Errors |
| --- | ---: | ---: | ---: | ---: |
| Phase 3: one API | 40 | 145.18 | 529.39 ms | 0 |
| Two API replicas, untuned | 80 | 131.03 | 739.22 ms | 0 |
| Two API replicas, tuned | 40 (20 each) | 132.60 | 607.49 ms | 0 |

The tuned run completed 9,380 application requests and 2,345 iterations. The
gateway distributed requests evenly: 4,733 to replica 1 and 4,649 to replica
2, including setup/teardown traffic. Queue failed jobs and temporary benchmark
tokens were both zero.

Endpoint p95 in the tuned run:

| Endpoint | p95 |
| --- | ---: |
| Activities | 681.62 ms |
| Stories | 501.75 ms |
| Conversations | 629.22 ms |
| Presence | 463.45 ms |

## Decision

Horizontal routing is working correctly, but adding replicas on this single
Docker Desktop host does not add CPU or database capacity. It increases
contention and is slower than the one-replica result. The production gateway is
therefore retained for deployment flexibility, while one API replica remains
the recommended topology for this host.

For actual horizontal gains, place replicas on separate compute hosts and use
an external managed load balancer. For improving one-host capacity, the next
phase should benchmark Laravel Octane with RoadRunner against the current FPM
baseline before considering a rewrite in another language.

Machine-readable tuned results are in `phase-5-capacity-summary.json`.
