# SquadUP product gap and performance audit — 2026-09-13

## Current completion state

| Area | State | Remaining work |
| --- | --- | --- |
| Authentication and onboarding | Core complete | Document token/session renewal and add mobile route tests |
| Sports, skill profiles and venues | Core complete | Add venue administration and production geocoding provider |
| Explore list/map | Functional | Server-side geospatial clustering, cursor pagination and PostGIS rollout |
| Activity create/join/lifecycle | Functional | Isolated PostgreSQL parallel HTTP capacity test and post-match handoff |
| Invitations, QR and share links | Functional | Test cold-start/deferred deep links on physical Android/iOS devices |
| Chat | P0-E substantially complete | Real-device reconnect and large-upload interruption verification |
| Push notifications | Implemented, environment dependent | Install and verify real Firebase credentials on staging/production |
| Stories | Core complete | Video/object-storage processing and admin moderation workflow |
| Ratings and reputation | Core implemented | Broader mobile profile presentation and moderation appeal UX |
| Friends discovery | Core complete | Improve ranking using compatibility and mutual activity history |
| Moderation | Partial | Add media preview, bulk triage, complete IDOR matrix and admin health detail |
| Offline behavior | Partial | Broaden persistent caches beyond home/chat and add user-facing cache controls |
| Analytics | Partial | Add named product funnel events and an opt-in export/diagnostics UI |
| Accessibility | Partial | Complete semantics/tap-target and screen-reader audit |
| CI/release | Partial | CI workflow, Android release build, production migration and canary checklist |

## Performance findings and changes in this pass

1. `Conversation::latestMessage()` previously used an ordered has-one relation.
   During eager loading this could hydrate many historical messages for the
   first 30 conversations. It now uses a timestamp aggregate subquery, which
   asks the database for one latest row per conversation.
2. Added covering indexes for conversation, clan and activity membership
   access paths and for latest/unread message lookups.
3. Chat conversation list now uses stale-while-revalidate. A per-session cached
   list renders immediately, while the API refresh happens in the background.
4. Realtime subscriptions recover on app resume and message history is fetched
   again, closing gaps caused by background suspension.
5. Push opens now route directly to conversation or activity detail.
6. The default home activity feed now reads a bounded per-account persistent
   cache first and revalidates in the background.
7. Mobile API duration/status/cache-hit samples are sanitized, limited to 100,
   and written in batches to avoid per-response disk overhead.
8. Profile history filters no longer refetch identity and reputation or replace
   the whole screen with a loading spinner on every tab change.

## Next performance work, ordered by expected impact

1. Capture staging p50/p95/p99 for `/conversations`, message history, Explore,
   home feed and activity detail with realistic data volume. Do not optimize
   endpoints solely from synthetic empty-database results.
2. Run `EXPLAIN (ANALYZE, BUFFERS)` for the slowest PostgreSQL queries after the
   new indexes are deployed. Remove indexes that are not selected.
3. Move Explore to PostGIS GiST geography indexes and server-side clustering
   when the production image supports the extension.
4. Replace page-number Explore pagination with a stable `(rank, starts_at, id)`
   cursor to avoid increasing offset cost and duplicate rows.
5. Validate the bounded media upload outbox under process death, low storage and
   interrupted large uploads on physical devices.
6. Extend existing API telemetry with screen-ready time, reconnect count and
   image decode failures without recording message bodies or coordinates.
7. Verify Octane worker memory and recycle limits during a multi-hour canary;
   keep the existing PHP-FPM rollback path.

## Release blockers

- Rating report, evidence review and reversible score invalidation are complete;
  an operational appeal policy is still required before launch.
- Story video/object-storage processing is not complete.
- Firebase delivery has not been verified with production credentials.
- No isolated PostgreSQL concurrency suite is available yet.
- Android/iOS release builds and real-device golden flows are not signed off.
