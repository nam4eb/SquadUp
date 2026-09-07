# Phase 2 - API and database profiling

## Instrumentation

`RequestTelemetry` can expose per-request database query counts and timing when
`PERFORMANCE_PROFILING_ENABLED=true`. The feature is disabled by default. When
enabled, responses include `X-DB-Query-Count` and `Server-Timing`; structured
request logs also include database duration and query count.

## Profile results

| Endpoint | Queries before | Queries after | Current p50 | Current p95 |
| --- | ---: | ---: | ---: | ---: |
| activities | 16 | 8 | 53.83 ms | 75.40 ms |
| stories | 3 | 3 | 33.78 ms | 41.49 ms |
| conversations | 7 | 7 | 43.67 ms | 74.79 ms |
| presence | 0 | 0 | 22.20 ms | 23.60 ms |

The Activity feed now uses correlated `EXISTS` predicates for friendships,
blocks, invitations, participation, clan membership, and hidden activities.
This removes eight preliminary ID-fetch queries and avoids large PHP-side ID
collections as the social graph grows.

## Index changes

- Remove the duplicate `(conversation_id, created_at, id)` messages index while
  retaining `messages_cursor_index`.
- Add `(last_message_at, updated_at)` for the conversation list ordering.

## Raw data

- `phase-2-query-profile.json`
- `phase-2-query-profile-after.json`

The next profiling step is concurrent load testing and query-plan validation
with a larger synthetic dataset; tiny seed datasets are not sufficient to
judge planner behavior at production cardinality.

## Test isolation

Backend tests must run through the dedicated Compose service so Docker's local
PostgreSQL and Reverb variables cannot override PHPUnit settings:

```powershell
docker compose --profile test run --rm test
```

The service always uses SQLite in-memory, null broadcasting, and array-backed
cache/session/presence. Running `php artisan test` directly inside the API
container is intentionally avoided because it can inherit development service
environment variables before PHPUnit boots.
