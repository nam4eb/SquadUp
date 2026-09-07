# Phase 4 - Endpoint latency optimization

## Changes

- Activity feed resolves the viewer's participation status with a correlated
  scalar subquery instead of eager-loading a separate participant relation.
- Story visibility uses a friendship `EXISTS` predicate instead of two ID
  pluck queries.
- Story read state uses `withExists` instead of eager-loading story-view rows.
- Production PHP-FPM uses a configurable dynamic pool with 40 workers by
  default. This profile needs roughly 1.5-2 GB when fully utilized.
- Sequential and concurrent benchmark scripts revoke their temporary Sanctum
  tokens and never persist token values in result artifacts.

## Verification

The isolated SQLite feature suite passed 8 tests and 37 assertions for Activity
and Story behavior.

The optimized Linux-volume benchmark used PostgreSQL, Redis, OPcache, three
warm-up calls, and 20 measured calls per endpoint:

| Endpoint | DB queries | p50 | p95 |
| --- | ---: | ---: | ---: |
| Activities | 7 | 40.37 ms | 48.66 ms |
| Stories | 1 | 22.83 ms | 25.54 ms |
| Conversations | 7 | 36.74 ms | 47.14 ms |
| Presence | 0 | 18.99 ms | 19.68 ms |

Activity previously used 8 queries. Story previously used 3 queries for the
same empty-story benchmark response. The machine-readable result is stored in
`phase-4-query-latency.json`.

## Capacity conclusion

Individual endpoints now remain below 50 ms p95 in the warm single-worker
benchmark. The Phase 3 production load test reached 145 application requests
per second with no errors, but its aggregate p95 was 529 ms at the 100-VU
ramp. The remaining high-concurrency latency is therefore primarily instance
queueing/saturation rather than a single slow database query.

The next capacity step should test two API replicas behind a load balancer and
multiple queue workers. If one-host PHP-FPM must sustain the full target alone,
Laravel Octane/RoadRunner should be benchmarked as an alternative runtime
before undertaking a backend language rewrite.
