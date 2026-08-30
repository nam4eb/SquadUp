# SquadUP target architecture

Status: approved implementation baseline, 2026-08-27.

## System context

```text
Flutter Android/iOS
        |
   HTTPS + WSS
        |
Laravel 12 modular monolith ---- Filament admin
        |
   +----+-------------------+
   |                        |
PostgreSQL 16          Redis 7
system of record       cache, queue, presence
        |
Object storage abstraction (local in development, S3/R2 in production)
```

The backend starts as a modular monolith. Domain boundaries are kept explicit
so high-volume modules can be extracted later without paying distributed-system
costs during product discovery.

## Backend modules

- Identity: users, social identities, sessions and account lifecycle.
- Social graph: requests, friendships and blocks.
- Activities: taxonomy, activities, lifecycle, participants and invitations.
- Clans: clans, membership, roles, permissions, events and statistics.
- Conversations: direct, group, event and clan conversations.
- Notifications: creation, preferences and asynchronous delivery.
- Moderation: reports, reasons, decisions and immutable audit records.
- Analytics: product events and pre-aggregated leaderboard snapshots.

Controllers only translate HTTP requests. Validation belongs in Form Requests,
authorization in Policies, orchestration in Actions, and reusable domain rules
in Services/value objects. Eloquent models do not become service containers.

## Runtime boundaries

- REST endpoints are versioned under `/api/v1`.
- Laravel Sanctum personal access tokens authenticate mobile requests.
- Laravel Reverb transports realtime events; authorization uses private and
  presence channels.
- PostgreSQL is the source of truth. Redis data is always reconstructable.
- Queued jobs handle notification delivery, media processing, analytics and
  leaderboard refreshes.
- All externally visible entities use UUIDs. Internal sequence IDs are avoided.

## Quality gates

A vertical slice is complete only with migration, model, validation, policy,
action/service, API resource, endpoint tests, seed data and documentation.
Backend tests use PostgreSQL behavior for concurrency-sensitive slices; SQLite
may only be used for isolated tests that do not rely on PostgreSQL semantics.

